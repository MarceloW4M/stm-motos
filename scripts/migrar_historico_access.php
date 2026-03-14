#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/includes/config.php';

function usage(): void
{
    $msg = <<<TXT
Uso:
  php scripts/migrar_historico_access.php --mdb="/ruta/base.mdb" [--tabla="reparaciones"] [--cliente-access-id=815] [--limit=5000] [--apply]

Opciones:
  --mdb                Ruta absoluta al archivo .mdb (obligatorio)
  --tabla              Tabla de Access origen (default: reparaciones)
  --cliente-access-id  Filtra por id cliente Access (opcional)
  --limit              Maximo de filas a evaluar (default: 5000)
  --apply              Aplica INSERT en MySQL (sin esto, simula y hace rollback)
TXT;

    fwrite(STDOUT, $msg . PHP_EOL);
}

function normalizeKey(string $value): string
{
    $value = strtolower(trim($value));
    $value = strtr($value, [
        'á' => 'a',
        'é' => 'e',
        'í' => 'i',
        'ó' => 'o',
        'ú' => 'u',
        'ü' => 'u',
        'ñ' => 'n',
    ]);
    return preg_replace('/[^a-z0-9]+/', '', $value) ?? '';
}

function firstValue(array $row, array $map, array $candidates): string
{
    foreach ($candidates as $candidate) {
        $key = normalizeKey($candidate);
        if (isset($map[$key])) {
            $idx = $map[$key];
            return isset($row[$idx]) ? trim((string)$row[$idx]) : '';
        }
    }
    return '';
}

function parseDate(?string $raw): ?string
{
    $raw = trim((string)$raw);
    if ($raw === '') {
        return null;
    }

    $formats = ['m/d/y H:i:s', 'm/d/Y H:i:s', 'Y-m-d H:i:s', 'd/m/Y H:i:s', 'd/m/y H:i:s'];
    foreach ($formats as $fmt) {
        $dt = DateTime::createFromFormat($fmt, $raw);
        if ($dt instanceof DateTime) {
            return $dt->format('Y-m-d H:i:s');
        }
    }

    return null;
}

$options = getopt('', ['mdb:', 'tabla::', 'cliente-access-id::', 'limit::', 'apply']);
$mdb = $options['mdb'] ?? '';
$tabla = $options['tabla'] ?? 'reparaciones';
$clienteFilter = isset($options['cliente-access-id']) ? trim((string)$options['cliente-access-id']) : null;
$limit = isset($options['limit']) ? max(1, (int)$options['limit']) : 5000;
$apply = array_key_exists('apply', $options);

if ($mdb === '' || !is_file($mdb)) {
    fwrite(STDERR, "Error: Debes indicar --mdb con un archivo existente.\n\n");
    usage();
    exit(1);
}

if (trim((string)shell_exec('command -v mdb-export 2>/dev/null')) === '') {
    fwrite(STDERR, "Error: mdb-export no esta disponible.\n");
    exit(1);
}

$exportCmd = sprintf('mdb-export -e -d "," -q "\"" -X double %s %s', escapeshellarg($mdb), escapeshellarg($tabla));
$csv = shell_exec($exportCmd);
if (!is_string($csv) || trim($csv) === '') {
    fwrite(STDERR, "Error: No se pudo exportar la tabla '$tabla'.\n");
    exit(1);
}

$stream = fopen('php://temp', 'r+');
if ($stream === false) {
    fwrite(STDERR, "Error: No se pudo inicializar buffer CSV.\n");
    exit(1);
}

fwrite($stream, $csv);
rewind($stream);

$headers = fgetcsv($stream);
if ($headers === false) {
    fclose($stream);
    fwrite(STDERR, "Error: No se pudo leer cabecera de '$tabla'.\n");
    exit(1);
}

$map = [];
foreach ($headers as $idx => $h) {
    $map[normalizeKey((string)$h)] = $idx;
}

$db = getDBConnection();
$db->beginTransaction();

$findClienteStmt = $db->prepare('SELECT id FROM clientes WHERE id_ant = :id_ant LIMIT 1');
$existsHistoricoStmt = $db->prepare('SELECT 1 FROM historico WHERE id_cliente_access = :id_cliente_access AND orden = :orden LIMIT 1');
$insertStmt = $db->prepare('INSERT INTO historico (cliente_id, id_cliente_access, orden, codigo_equipo, fecha_ingreso, fecha_egreso, observaciones) VALUES (:cliente_id, :id_cliente_access, :orden, :codigo_equipo, :fecha_ingreso, :fecha_egreso, :observaciones)');

$evaluadas = 0;
$insertadas = 0;
$omitidas = 0;
$preview = [];

try {
    while (($row = fgetcsv($stream)) !== false) {
        if ($evaluadas >= $limit) {
            break;
        }
        if ($row === [null] || $row === []) {
            continue;
        }

        $ordenRaw = firstValue($row, $map, ['orden']);
        $codigoEquipo = mb_substr(firstValue($row, $map, ['codigo equipo', 'código equipo']), 0, 50, 'UTF-8');
        $fechaIngreso = parseDate(firstValue($row, $map, ['fecha ingreso']));
        $fechaEgreso = parseDate(firstValue($row, $map, ['fecha egreso']));
        $observaciones = firstValue($row, $map, ['observaciones']);
        $idClienteAccess = firstValue($row, $map, ['id cliente', 'id_cliente', 'idcliente']);

        if ($idClienteAccess === '' || $ordenRaw === '') {
            continue;
        }

        if ($clienteFilter !== null && $clienteFilter !== '' && $idClienteAccess !== $clienteFilter) {
            continue;
        }

        $evaluadas++;
        $orden = (int)$ordenRaw;

        $existsHistoricoStmt->bindValue(':id_cliente_access', $idClienteAccess);
        $existsHistoricoStmt->bindValue(':orden', $orden, PDO::PARAM_INT);
        $existsHistoricoStmt->execute();
        if ($existsHistoricoStmt->fetchColumn()) {
            $omitidas++;
            continue;
        }

        $findClienteStmt->bindValue(':id_ant', $idClienteAccess);
        $findClienteStmt->execute();
        $clienteIdMysql = $findClienteStmt->fetchColumn();

        $insertStmt->bindValue(':cliente_id', $clienteIdMysql !== false ? (int)$clienteIdMysql : null, $clienteIdMysql !== false ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $insertStmt->bindValue(':id_cliente_access', $idClienteAccess);
        $insertStmt->bindValue(':orden', $orden, PDO::PARAM_INT);
        $insertStmt->bindValue(':codigo_equipo', $codigoEquipo !== '' ? $codigoEquipo : null);
        $insertStmt->bindValue(':fecha_ingreso', $fechaIngreso);
        $insertStmt->bindValue(':fecha_egreso', $fechaEgreso);
        $insertStmt->bindValue(':observaciones', $observaciones !== '' ? $observaciones : null);
        $insertStmt->execute();

        $insertadas++;
        if (count($preview) < 10) {
            $preview[] = [
                'id_cliente_access' => $idClienteAccess,
                'orden' => $orden,
                'codigo_equipo' => $codigoEquipo,
                'fecha_ingreso' => $fechaIngreso,
                'fecha_egreso' => $fechaEgreso,
            ];
        }
    }

    if ($apply) {
        $db->commit();
    } else {
        $db->rollBack();
    }
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    fwrite(STDERR, 'Error en migracion historico: ' . $e->getMessage() . PHP_EOL);
    exit(1);
} finally {
    fclose($stream);
}

fwrite(STDOUT, PHP_EOL . 'Resumen migracion historico:' . PHP_EOL);
fwrite(STDOUT, '- Tabla Access: ' . $tabla . PHP_EOL);
fwrite(STDOUT, '- Filtro id cliente Access: ' . ($clienteFilter ?: 'SIN FILTRO') . PHP_EOL);
fwrite(STDOUT, '- Filas evaluadas: ' . $evaluadas . PHP_EOL);
fwrite(STDOUT, '- Filas insertadas: ' . $insertadas . PHP_EOL);
fwrite(STDOUT, '- Filas omitidas (ya existentes): ' . $omitidas . PHP_EOL);
fwrite(STDOUT, '- Modo: ' . ($apply ? 'APLICADO' : 'SIMULACION (rollback)') . PHP_EOL);

if (!empty($preview)) {
    fwrite(STDOUT, PHP_EOL . 'Vista previa:' . PHP_EOL);
    foreach ($preview as $i => $p) {
        fwrite(STDOUT, sprintf(
            "%d) id_access=%s | orden=%d | cod=%s | ingreso=%s | egreso=%s\n",
            $i + 1,
            $p['id_cliente_access'],
            $p['orden'],
            $p['codigo_equipo'] !== '' ? $p['codigo_equipo'] : 'NULL',
            $p['fecha_ingreso'] ?? 'NULL',
            $p['fecha_egreso'] ?? 'NULL'
        ));
    }
}
