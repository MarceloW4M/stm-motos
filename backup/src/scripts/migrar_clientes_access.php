#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/includes/config.php';

function usage(): void
{
    $msg = <<<TXT
Uso:
  php scripts/migrar_clientes_access.php --mdb="/ruta/servicio tecnico.mdb" [--tabla="Clientes"] [--limit=10] [--apply]

Opciones:
  --mdb     Ruta absoluta al archivo .mdb (obligatorio)
  --tabla   Nombre de la tabla de Access (default: Clientes)
  --limit   Cantidad maxima de registros a procesar (default: 10)
  --apply   Ejecuta INSERT en MySQL. Si no se pasa, solo simula.

Ejemplo (prueba 10):
  php scripts/migrar_clientes_access.php --mdb="/root/servicio tecnico.mdb" --tabla="Clientes" --limit=10 --apply
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
    $value = preg_replace('/[^a-z0-9]+/', '', $value) ?? '';
    return $value;
}

function sentenceCase(string $value): string
{
    $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');
    if ($value === '') {
        return '';
    }

    $lower = mb_strtolower($value, 'UTF-8');
    $first = mb_strtoupper(mb_substr($lower, 0, 1, 'UTF-8'), 'UTF-8');
    $rest = mb_substr($lower, 1, null, 'UTF-8');

    return $first . $rest;
}

function properNameCase(string $value): string
{
    $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');
    if ($value === '') {
        return '';
    }

    return mb_convert_case(mb_strtolower($value, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
}

function truncateUtf8(string $value, int $maxLen): string
{
    return mb_substr($value, 0, $maxLen, 'UTF-8');
}

function firstValue(array $row, array $headersMap, array $candidates): string
{
    foreach ($candidates as $candidate) {
        $key = normalizeKey($candidate);
        if (isset($headersMap[$key])) {
            $index = $headersMap[$key];
            return isset($row[$index]) ? trim($row[$index]) : '';
        }
    }

    return '';
}

$options = getopt('', ['mdb:', 'tabla::', 'limit::', 'apply']);
$mdbPath = $options['mdb'] ?? '';
$table = $options['tabla'] ?? 'Clientes';
$limit = isset($options['limit']) ? max(1, (int)$options['limit']) : 10;
$apply = array_key_exists('apply', $options);

if ($mdbPath === '' || !is_file($mdbPath)) {
    fwrite(STDERR, "Error: Debes indicar --mdb con un archivo existente.\n\n");
    usage();
    exit(1);
}

$which = trim((string)shell_exec('command -v mdb-export 2>/dev/null'));
if ($which === '') {
    fwrite(STDERR, "Error: mdb-export no esta disponible. Instala mdbtools.\n");
    exit(1);
}

$command = sprintf(
    'mdb-export -d "," -q "\"" -X double %s %s',
    escapeshellarg($mdbPath),
    escapeshellarg($table)
);

$csvData = shell_exec($command);
if (!is_string($csvData) || trim($csvData) === '') {
    fwrite(STDERR, "Error: No se pudo leer la tabla '$table' desde el MDB.\n");
    exit(1);
}

$lines = preg_split('/\r\n|\n|\r/', trim($csvData));
if (!$lines || count($lines) < 2) {
    fwrite(STDERR, "Error: La tabla '$table' no contiene datos.\n");
    exit(1);
}

$headers = str_getcsv(array_shift($lines));
$headersMap = [];
foreach ($headers as $idx => $header) {
    $headersMap[normalizeKey((string)$header)] = $idx;
}

$rows = [];
for ($i = 0; $i < count($lines) && count($rows) < $limit; $i++) {
    $line = trim((string)$lines[$i]);
    if ($line === '') {
        continue;
    }
    $rows[] = str_getcsv($line);
}

if (empty($rows)) {
    fwrite(STDERR, "Error: No hay filas utiles para migrar.\n");
    exit(1);
}

$db = getDBConnection();
$db->beginTransaction();

$insertSql = 'INSERT INTO clientes (nombre, telefono, direccion, ciudad, observaciones, id_ant) VALUES (:nombre, :telefono, :direccion, :ciudad, :observaciones, :id_ant)';
$stmt = $db->prepare($insertSql);
$existsStmt = $db->prepare('SELECT 1 FROM clientes WHERE id_ant = :id_ant LIMIT 1');

$inserted = 0;
$skipped = 0;
$preview = [];

try {
    foreach ($rows as $row) {
        $idCliente = firstValue($row, $headersMap, ['id cliente', 'id_cliente', 'idcliente', 'id']);
        $nombres = firstValue($row, $headersMap, ['nombres', 'nombre']);
        $apellidos = firstValue($row, $headersMap, ['apellidos', 'apellido']);
        $direccion = firstValue($row, $headersMap, ['direccion', 'domicilio']);
        $ciudad = firstValue($row, $headersMap, ['ciudad', 'localidad']);
        $telefonos = firstValue($row, $headersMap, ['telefonos', 'telefono', 'tel']);

        $nombresFmt = properNameCase($nombres);
        $apellidosFmt = properNameCase($apellidos);
        $nombreCompleto = truncateUtf8(trim($nombresFmt . ' ' . $apellidosFmt), 100);
        $direccionFmt = sentenceCase($direccion);
        $ciudadFmt = truncateUtf8(sentenceCase($ciudad), 100);
        $telefonoFmt = truncateUtf8(sentenceCase($telefonos), 20);
        $idAnt = $idCliente !== '' ? truncateUtf8($idCliente, 100) : null;
        $obs = $idCliente !== '' ? 'ID cliente Access: ' . $idCliente : null;

        if ($nombreCompleto === '') {
            continue;
        }

        if ($idAnt !== null && $idAnt !== '') {
            $existsStmt->bindValue(':id_ant', $idAnt);
            $existsStmt->execute();
            if ($existsStmt->fetchColumn()) {
                $skipped++;
                continue;
            }
        }

        $preview[] = [
            'id_access' => $idCliente,
            'nombre' => $nombreCompleto,
            'telefono' => $telefonoFmt,
            'direccion' => $direccionFmt,
            'ciudad' => $ciudadFmt,
            'observaciones' => $obs,
        ];

        $stmt->bindValue(':nombre', $nombreCompleto);
        $stmt->bindValue(':telefono', $telefonoFmt);
        $stmt->bindValue(':direccion', $direccionFmt !== '' ? $direccionFmt : null);
        $stmt->bindValue(':ciudad', $ciudadFmt !== '' ? $ciudadFmt : null);
        $stmt->bindValue(':observaciones', $obs);
        $stmt->bindValue(':id_ant', $idAnt);
        $stmt->execute();
        $inserted++;
    }

    if (!$apply) {
        $db->rollBack();
    } else {
        $db->commit();
    }
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    fwrite(STDERR, 'Error en migracion: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, PHP_EOL . 'Resumen:' . PHP_EOL);
fwrite(STDOUT, '- Tabla Access: ' . $table . PHP_EOL);
fwrite(STDOUT, '- Limite: ' . $limit . PHP_EOL);
fwrite(STDOUT, '- Registros procesados: ' . count($rows) . PHP_EOL);
fwrite(STDOUT, '- Registros insertados: ' . $inserted . PHP_EOL);
fwrite(STDOUT, '- Registros omitidos (ya migrados): ' . $skipped . PHP_EOL);
fwrite(STDOUT, '- Modo: ' . ($apply ? 'APLICADO' : 'SIMULACION (rollback)') . PHP_EOL);

fwrite(STDOUT, PHP_EOL . 'Vista previa de insercion:' . PHP_EOL);
foreach ($preview as $idx => $row) {
    if ($idx >= 10) {
        break;
    }
    fwrite(
        STDOUT,
        sprintf(
            "%d) id_access=%s | nombre=%s | telefono=%s | direccion=%s | ciudad=%s | obs=%s\n",
            $idx + 1,
            $row['id_access'] !== '' ? $row['id_access'] : 'NULL',
            $row['nombre'],
            $row['telefono'],
            $row['direccion'] !== '' ? $row['direccion'] : 'NULL',
            $row['ciudad'] !== '' ? $row['ciudad'] : 'NULL',
            $row['observaciones'] ?? 'NULL'
        )
    );
}
