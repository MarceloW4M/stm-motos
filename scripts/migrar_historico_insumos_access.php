#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/includes/config.php';

function usage(): void
{
    $msg = <<<TXT
Uso:
  php scripts/migrar_historico_insumos_access.php --mdb="/ruta/base.mdb" [--tabla="repuestos e insumos"] [--cliente-access-id=815] [--limit=50000] [--apply]

Opciones:
  --mdb                Ruta al archivo Access .mdb (obligatorio)
  --tabla              Tabla Access origen (default: repuestos e insumos)
  --cliente-access-id  Filtra por cliente Access (opcional)
  --limit              Cantidad maxima de filas a evaluar (default: 50000)
  --apply              Aplica INSERT en MySQL. Sin esto, simula y hace rollback.
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

$options = getopt('', ['mdb:', 'tabla::', 'cliente-access-id::', 'limit::', 'apply']);
$mdbPath = $options['mdb'] ?? '';
$tabla = $options['tabla'] ?? 'repuestos e insumos';
$clienteFilter = isset($options['cliente-access-id']) ? trim((string)$options['cliente-access-id']) : null;
$limit = isset($options['limit']) ? max(1, (int)$options['limit']) : 50000;
$apply = array_key_exists('apply', $options);

if ($mdbPath === '' || !is_file($mdbPath)) {
    fwrite(STDERR, "Error: Debes indicar --mdb con un archivo existente.\n\n");
    usage();
    exit(1);
}

if (trim((string)shell_exec('command -v mdb-export 2>/dev/null')) === '') {
    fwrite(STDERR, "Error: mdb-export no esta disponible.\n");
    exit(1);
}

$cmd = sprintf('mdb-export -e -d "," -q "\"" -X double %s %s', escapeshellarg($mdbPath), escapeshellarg($tabla));
$csv = shell_exec($cmd);
if (!is_string($csv) || trim($csv) === '') {
    fwrite(STDERR, "Error: No se pudo exportar la tabla '$tabla'.\n");
    exit(1);
}

$stream = fopen('php://temp', 'r+');
if ($stream === false) {
    fwrite(STDERR, "Error: No se pudo crear buffer temporal CSV.\n");
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

$historicoMapStmt = $db->query('SELECT orden, cliente_id, id_cliente_access, codigo_equipo FROM historico');
$ordenMap = [];
foreach ($historicoMapStmt->fetchAll(PDO::FETCH_ASSOC) as $h) {
    $orden = (int)$h['orden'];
    if (!isset($ordenMap[$orden])) {
        $ordenMap[$orden] = [
            'cliente_id' => $h['cliente_id'] !== null ? (int)$h['cliente_id'] : null,
            'id_cliente_access' => $h['id_cliente_access'],
            'codigo_equipo' => $h['codigo_equipo'],
        ];
    }
}

$existsStmt = $db->prepare('SELECT 1 FROM historico_insumos WHERE orden = :orden AND pieza_insumo = :pieza_insumo AND ((precio_estimado IS NULL AND :precio_n IS NULL) OR precio_estimado = :precio) AND ((abonado IS NULL AND :abonado_n IS NULL) OR abonado = :abonado) AND ((id_cliente_access IS NULL AND :id_cliente_access_n IS NULL) OR id_cliente_access = :id_cliente_access) LIMIT 1');
$insertStmt = $db->prepare('INSERT INTO historico_insumos (cliente_id, id_cliente_access, orden, codigo_equipo, pieza_insumo, precio_estimado, abonado) VALUES (:cliente_id, :id_cliente_access, :orden, :codigo_equipo, :pieza_insumo, :precio_estimado, :abonado)');

$evaluadas = 0;
$insertadas = 0;
$omitidasExistente = 0;
$omitidasSinOrden = 0;
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
        $pieza = mb_substr(firstValue($row, $map, ['pieza o insumo']), 0, 255, 'UTF-8');
        $precioRaw = firstValue($row, $map, ['precio estimado']);
        $abonadoRaw = firstValue($row, $map, ['abonado']);

        if ($ordenRaw === '' || $pieza === '' || !is_numeric($ordenRaw)) {
            continue;
        }

        $orden = (int)$ordenRaw;
        if (!isset($ordenMap[$orden])) {
            $omitidasSinOrden++;
            continue;
        }

        $ref = $ordenMap[$orden];
        if ($clienteFilter !== null && $clienteFilter !== '' && (string)$ref['id_cliente_access'] !== $clienteFilter) {
            continue;
        }

        $evaluadas++;

        $precio = is_numeric($precioRaw) ? (float)$precioRaw : null;
        $abonado = is_numeric($abonadoRaw) ? (float)$abonadoRaw : null;
        $idClienteAccess = $ref['id_cliente_access'] !== '' ? (string)$ref['id_cliente_access'] : null;

        $existsStmt->bindValue(':orden', $orden, PDO::PARAM_INT);
        $existsStmt->bindValue(':pieza_insumo', $pieza);
        $existsStmt->bindValue(':precio', $precio);
        $existsStmt->bindValue(':precio_n', $precio);
        $existsStmt->bindValue(':abonado', $abonado);
        $existsStmt->bindValue(':abonado_n', $abonado);
        $existsStmt->bindValue(':id_cliente_access', $idClienteAccess);
        $existsStmt->bindValue(':id_cliente_access_n', $idClienteAccess);
        $existsStmt->execute();

        if ($existsStmt->fetchColumn()) {
            $omitidasExistente++;
            continue;
        }

        if ($ref['cliente_id'] !== null) {
            $insertStmt->bindValue(':cliente_id', $ref['cliente_id'], PDO::PARAM_INT);
        } else {
            $insertStmt->bindValue(':cliente_id', null, PDO::PARAM_NULL);
        }
        $insertStmt->bindValue(':id_cliente_access', $idClienteAccess);
        $insertStmt->bindValue(':orden', $orden, PDO::PARAM_INT);
        $insertStmt->bindValue(':codigo_equipo', $ref['codigo_equipo'] !== '' ? $ref['codigo_equipo'] : null);
        $insertStmt->bindValue(':pieza_insumo', $pieza);
        $insertStmt->bindValue(':precio_estimado', $precio);
        $insertStmt->bindValue(':abonado', $abonado);
        $insertStmt->execute();
        $insertadas++;

        if (count($preview) < 10) {
            $preview[] = [
                'id_cliente_access' => $idClienteAccess,
                'orden' => $orden,
                'pieza' => $pieza,
                'precio' => $precio,
                'abonado' => $abonado,
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
    fclose($stream);
    fwrite(STDERR, 'Error en migracion historico insumos: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

fclose($stream);

fwrite(STDOUT, PHP_EOL . 'Resumen migracion historico insumos:' . PHP_EOL);
fwrite(STDOUT, '- Tabla Access: ' . $tabla . PHP_EOL);
fwrite(STDOUT, '- Filtro id cliente Access: ' . ($clienteFilter ?: 'SIN FILTRO') . PHP_EOL);
fwrite(STDOUT, '- Filas evaluadas: ' . $evaluadas . PHP_EOL);
fwrite(STDOUT, '- Filas insertadas: ' . $insertadas . PHP_EOL);
fwrite(STDOUT, '- Filas omitidas (ya existentes): ' . $omitidasExistente . PHP_EOL);
fwrite(STDOUT, '- Filas omitidas (sin orden en historico): ' . $omitidasSinOrden . PHP_EOL);
fwrite(STDOUT, '- Modo: ' . ($apply ? 'APLICADO' : 'SIMULACION (rollback)') . PHP_EOL);

if (!empty($preview)) {
    fwrite(STDOUT, PHP_EOL . 'Vista previa:' . PHP_EOL);
    foreach ($preview as $i => $p) {
        fwrite(STDOUT, sprintf(
            "%d) access=%s | orden=%d | pieza=%s | precio=%s | abonado=%s\n",
            $i + 1,
            $p['id_cliente_access'] ?? 'NULL',
            $p['orden'],
            $p['pieza'],
            $p['precio'] !== null ? number_format((float)$p['precio'], 2, '.', '') : 'NULL',
            $p['abonado'] !== null ? number_format((float)$p['abonado'], 2, '.', '') : 'NULL'
        ));
    }
}
