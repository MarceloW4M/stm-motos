#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/includes/config.php';

function usage(): void
{
    $msg = <<<TXT
Uso:
  php scripts/migrar_vehiculos_access.php --mdb="/ruta/base.mdb" [--tabla-reparaciones="reparaciones"] [--tabla-equipos="equipos"] [--cliente-access-id=815] [--apply]

Opciones:
  --mdb                Ruta al archivo Access .mdb (obligatorio)
  --tabla-reparaciones Tabla origen de relacion cliente-equipo (default: reparaciones)
  --tabla-equipos      Tabla origen de datos de equipo (default: equipos)
  --cliente-access-id  Filtra por un cliente Access puntual (opcional)
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

function parseCsvRowsFromMdb(string $mdbPath, string $table): array
{
    $cmd = sprintf('mdb-export -e -d "," -q "\"" -X double %s %s', escapeshellarg($mdbPath), escapeshellarg($table));
    $csv = shell_exec($cmd);
    if (!is_string($csv) || trim($csv) === '') {
        throw new RuntimeException("No se pudo exportar la tabla '$table'.");
    }

    $stream = fopen('php://temp', 'r+');
    if ($stream === false) {
        throw new RuntimeException('No se pudo abrir buffer temporal CSV.');
    }

    fwrite($stream, $csv);
    rewind($stream);

    $headers = fgetcsv($stream);
    if ($headers === false) {
        fclose($stream);
        throw new RuntimeException("No se pudo leer cabecera de '$table'.");
    }

    $map = [];
    foreach ($headers as $idx => $header) {
        $map[normalizeKey((string)$header)] = $idx;
    }

    $rows = [];
    while (($row = fgetcsv($stream)) !== false) {
        if ($row === [null] || $row === []) {
            continue;
        }
        $rows[] = $row;
    }

    fclose($stream);

    return [$map, $rows];
}

$options = getopt('', ['mdb:', 'tabla-reparaciones::', 'tabla-equipos::', 'cliente-access-id::', 'apply']);
$mdbPath = $options['mdb'] ?? '';
$tablaRep = $options['tabla-reparaciones'] ?? 'reparaciones';
$tablaEquipos = $options['tabla-equipos'] ?? 'equipos';
$clienteFilter = isset($options['cliente-access-id']) ? trim((string)$options['cliente-access-id']) : null;
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

try {
    [$mapRep, $rowsRep] = parseCsvRowsFromMdb($mdbPath, $tablaRep);
    [$mapEq, $rowsEq] = parseCsvRowsFromMdb($mdbPath, $tablaEquipos);
} catch (Throwable $e) {
    fwrite(STDERR, 'Error al leer MDB: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

// Mapa codigo_equipo -> datos de equipo
$equiposByCodigo = [];
foreach ($rowsEq as $row) {
    $codigo = mb_substr(firstValue($row, $mapEq, ['codigo equipo', 'código equipo']), 0, 50, 'UTF-8');
    if ($codigo === '') {
        continue;
    }

    $equiposByCodigo[$codigo] = [
        'categoria' => mb_substr(firstValue($row, $mapEq, ['categoria', 'categoría']), 0, 50, 'UTF-8'),
        'marca' => mb_substr(firstValue($row, $mapEq, ['marca']), 0, 50, 'UTF-8'),
        'modelo' => mb_substr(firstValue($row, $mapEq, ['modelo']), 0, 50, 'UTF-8'),
        'anio' => firstValue($row, $mapEq, ['anio', 'año']),
        'color' => mb_substr(firstValue($row, $mapEq, ['color']), 0, 50, 'UTF-8'),
    ];
}

// pares unicos cliente_access + codigo_equipo a partir de reparaciones
$pairs = [];
foreach ($rowsRep as $row) {
    $idClienteAccess = firstValue($row, $mapRep, ['id cliente', 'id_cliente', 'idcliente']);
    $codigo = mb_substr(firstValue($row, $mapRep, ['codigo equipo', 'código equipo']), 0, 50, 'UTF-8');

    if ($idClienteAccess === '' || $codigo === '') {
        continue;
    }

    if ($clienteFilter !== null && $clienteFilter !== '' && $idClienteAccess !== $clienteFilter) {
        continue;
    }

    $pairKey = $idClienteAccess . '|' . $codigo;
    if (!isset($pairs[$pairKey])) {
        $pairs[$pairKey] = [
            'id_cliente_access' => $idClienteAccess,
            'codigo_equipo' => $codigo,
        ];
    }
}

$db = getDBConnection();
$db->beginTransaction();

$findClienteStmt = $db->prepare('SELECT id FROM clientes WHERE id_ant = :id_ant LIMIT 1');
$existsVehiculoStmt = $db->prepare('SELECT 1 FROM vehiculos WHERE cliente_id = :cliente_id AND matricula = :matricula LIMIT 1');
$insertVehiculoStmt = $db->prepare('INSERT INTO vehiculos (cliente_id, categoria, marca, modelo, matricula, anio, vin) VALUES (:cliente_id, :categoria, :marca, :modelo, :matricula, :anio, :vin)');

$evaluados = 0;
$insertados = 0;
$omitidosExistente = 0;
$omitidosSinCliente = 0;
$preview = [];

try {
    foreach ($pairs as $pair) {
        $evaluados++;
        $idClienteAccess = $pair['id_cliente_access'];
        $matricula = $pair['codigo_equipo'];

        $findClienteStmt->bindValue(':id_ant', $idClienteAccess);
        $findClienteStmt->execute();
        $clienteId = $findClienteStmt->fetchColumn();

        if ($clienteId === false) {
            $omitidosSinCliente++;
            continue;
        }

        $existsVehiculoStmt->bindValue(':cliente_id', (int)$clienteId, PDO::PARAM_INT);
        $existsVehiculoStmt->bindValue(':matricula', $matricula);
        $existsVehiculoStmt->execute();
        if ($existsVehiculoStmt->fetchColumn()) {
            $omitidosExistente++;
            continue;
        }

        $eq = $equiposByCodigo[$matricula] ?? null;
        $categoria = $eq['categoria'] ?? null;
        $marca = $eq['marca'] ?? '';
        $modelo = $eq['modelo'] ?? '';
        $anioRaw = $eq['anio'] ?? '';
        $vin = $eq['color'] ?? null;

        if ($marca === '') {
            $marca = 'N/D';
        }
        if ($modelo === '') {
            $modelo = 'N/D';
        }

        $anio = null;
        if ($anioRaw !== '' && is_numeric($anioRaw)) {
            $anioInt = (int)$anioRaw;
            if ($anioInt > 0) {
                $anio = $anioInt;
            }
        }

        $insertVehiculoStmt->bindValue(':cliente_id', (int)$clienteId, PDO::PARAM_INT);
        $insertVehiculoStmt->bindValue(':categoria', $categoria !== '' ? $categoria : null);
        $insertVehiculoStmt->bindValue(':marca', $marca);
        $insertVehiculoStmt->bindValue(':modelo', $modelo);
        $insertVehiculoStmt->bindValue(':matricula', $matricula);
        $insertVehiculoStmt->bindValue(':anio', $anio, $anio !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $insertVehiculoStmt->bindValue(':vin', $vin !== '' ? $vin : null);
        $insertVehiculoStmt->execute();

        $insertados++;
        if (count($preview) < 10) {
            $preview[] = [
                'cliente_access' => $idClienteAccess,
                'cliente_id' => (int)$clienteId,
                'matricula' => $matricula,
                'categoria' => $categoria,
                'marca' => $marca,
                'modelo' => $modelo,
                'anio' => $anio,
                'vin' => $vin,
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
    fwrite(STDERR, 'Error migrando vehiculos: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, PHP_EOL . 'Resumen migracion vehiculos:' . PHP_EOL);
fwrite(STDOUT, '- Cliente Access filtro: ' . ($clienteFilter ?: 'SIN FILTRO') . PHP_EOL);
fwrite(STDOUT, '- Pares cliente/codigo evaluados: ' . $evaluados . PHP_EOL);
fwrite(STDOUT, '- Insertados: ' . $insertados . PHP_EOL);
fwrite(STDOUT, '- Omitidos por existente: ' . $omitidosExistente . PHP_EOL);
fwrite(STDOUT, '- Omitidos sin cliente mapeado: ' . $omitidosSinCliente . PHP_EOL);
fwrite(STDOUT, '- Modo: ' . ($apply ? 'APLICADO' : 'SIMULACION (rollback)') . PHP_EOL);

if (!empty($preview)) {
    fwrite(STDOUT, PHP_EOL . 'Vista previa:' . PHP_EOL);
    foreach ($preview as $i => $p) {
        fwrite(STDOUT, sprintf(
            "%d) access=%s -> cliente_id=%d | mat=%s | cat=%s | %s %s | anio=%s | vin=%s\n",
            $i + 1,
            $p['cliente_access'],
            $p['cliente_id'],
            $p['matricula'],
            $p['categoria'] ?? 'NULL',
            $p['marca'],
            $p['modelo'],
            $p['anio'] !== null ? (string)$p['anio'] : 'NULL',
            $p['vin'] !== null && $p['vin'] !== '' ? $p['vin'] : 'NULL'
        ));
    }
}
