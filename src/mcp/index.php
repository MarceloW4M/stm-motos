<?php

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-KEY, X-MCP-Auth');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';

$database = new Database();
$db = $database->getConnection();

function mcpJsonResponse(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function mcpNormalizedHeaders(): array
{
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $normalized = [];

    foreach ($headers as $name => $value) {
        $normalized[strtolower($name)] = trim((string) $value);
    }

    return $normalized;
}

function mcpConfiguredAuthHeader(): string
{
    return strtolower(trim(MCP_AUTH_HEADER ?: 'Authorization'));
}

function mcpExtractTokenFromValue(?string $value): ?string
{
    if ($value === null || $value === '') {
        return null;
    }

    $scheme = trim(MCP_AUTH_SCHEME ?: 'Bearer');
    if ($scheme !== '' && stripos($value, $scheme . ' ') === 0) {
        return trim(substr($value, strlen($scheme) + 1));
    }

    if (stripos($value, 'Bearer ') === 0) {
        return trim(substr($value, 7));
    }

    return trim($value);
}

function mcpRequestToken(): ?string
{
    $headers = mcpNormalizedHeaders();
    $configuredHeader = mcpConfiguredAuthHeader();
    $candidates = [$configuredHeader, 'authorization', 'x-mcp-auth', 'x-api-key'];

    foreach ($candidates as $headerName) {
        if (!isset($headers[$headerName])) {
            continue;
        }

        $token = mcpExtractTokenFromValue($headers[$headerName]);
        if ($token !== null && $token !== '') {
            return $token;
        }
    }

    return null;
}

function mcpHasValidToken(): bool
{
    if (API_TOKEN === '') {
        return true;
    }

    $providedToken = mcpRequestToken();
    return is_string($providedToken) && hash_equals(API_TOKEN, $providedToken);
}

function mcpJsonRpcResult($id, array $result, int $statusCode = 200): void
{
    mcpJsonResponse([
        'jsonrpc' => '2.0',
        'id' => $id,
        'result' => $result,
    ], $statusCode);
}

function mcpJsonRpcError($id, int $code, string $message, ?array $data = null, int $statusCode = 200): void
{
    $error = [
        'code' => $code,
        'message' => $message,
    ];

    if ($data !== null) {
        $error['data'] = $data;
    }

    mcpJsonResponse([
        'jsonrpc' => '2.0',
        'id' => $id,
        'error' => $error,
    ], $statusCode);
}

function mcpNotificationAccepted(): void
{
    http_response_code(202);
    exit;
}

function mcpDecodeJsonBody(): ?array
{
    $rawBody = file_get_contents('php://input');
    if (!is_string($rawBody) || trim($rawBody) === '') {
        return null;
    }

    $decoded = json_decode($rawBody, true);
    return is_array($decoded) ? $decoded : null;
}

function mcpIsJsonRpcRequest(?array $payload): bool
{
    return is_array($payload)
        && ($payload['jsonrpc'] ?? null) === '2.0'
        && isset($payload['method'])
        && is_string($payload['method']);
}

function mcpQuoteIdentifier(string $identifier): string
{
    return '`' . str_replace('`', '``', $identifier) . '`';
}

function mcpValidateTableName(PDO $db, string $tableName): string
{
    $tableName = trim($tableName);
    if ($tableName === '' || !preg_match('/^[A-Za-z0-9_]+$/', $tableName)) {
        throw new InvalidArgumentException('Nombre de tabla inválido.');
    }

    $stmt = $db->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name'
    );
    $stmt->bindValue(':table_name', $tableName);
    $stmt->execute();

    if ((int) $stmt->fetchColumn() === 0) {
        throw new InvalidArgumentException('La tabla solicitada no existe en la base actual.');
    }

    return $tableName;
}

function mcpExpectedCoreTables(): array
{
    return [
        'usuarios',
        'clientes',
        'vehiculos',
        'repuestos',
        'servicios',
        'turnos',
        'turno_repuestos',
        'historico_insumos',
        'ordenes_reparacion',
    ];
}

function mcpFetchTables(PDO $db, bool $includeViews = false): array
{
    $sql = 'SELECT TABLE_NAME, TABLE_TYPE, ENGINE, TABLE_ROWS, TABLE_COLLATION, CREATE_TIME, UPDATE_TIME
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()';

    if (!$includeViews) {
        $sql .= " AND TABLE_TYPE = 'BASE TABLE'";
    }

    $sql .= ' ORDER BY TABLE_NAME';

    $stmt = $db->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function mcpFetchColumns(PDO $db, string $tableName): array
{
    $stmt = $db->prepare(
        'SELECT COLUMN_NAME, DATA_TYPE, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, COLUMN_KEY, EXTRA, COLUMN_COMMENT
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name
         ORDER BY ORDINAL_POSITION'
    );
    $stmt->bindValue(':table_name', $tableName);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function mcpFetchIndexes(PDO $db, string $tableName): array
{
    $stmt = $db->prepare(
        'SELECT INDEX_NAME, NON_UNIQUE, SEQ_IN_INDEX, COLUMN_NAME, INDEX_TYPE
         FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name
         ORDER BY INDEX_NAME, SEQ_IN_INDEX'
    );
    $stmt->bindValue(':table_name', $tableName);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function mcpFetchForeignKeys(PDO $db, string $tableName): array
{
    $stmt = $db->prepare(
        'SELECT CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
         FROM information_schema.KEY_COLUMN_USAGE
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :table_name
           AND REFERENCED_TABLE_NAME IS NOT NULL
         ORDER BY CONSTRAINT_NAME, ORDINAL_POSITION'
    );
    $stmt->bindValue(':table_name', $tableName);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function mcpFetchTableRowCount(PDO $db, string $tableName): int
{
    $sql = 'SELECT COUNT(*) FROM ' . mcpQuoteIdentifier($tableName);
    return (int) $db->query($sql)->fetchColumn();
}

function mcpFetchCreateTable(PDO $db, string $tableName): ?string
{
    $sql = 'SHOW CREATE TABLE ' . mcpQuoteIdentifier($tableName);
    $row = $db->query($sql)->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row)) {
        return null;
    }

    return $row['Create Table'] ?? null;
}

function mcpFetchTablePreview(PDO $db, string $tableName, int $limit = 25, int $offset = 0, ?string $orderBy = null, string $orderDirection = 'ASC'): array
{
    $limit = max(1, min($limit, 200));
    $offset = max(0, $offset);
    $sql = 'SELECT * FROM ' . mcpQuoteIdentifier($tableName);

    if ($orderBy !== null && $orderBy !== '') {
        $columns = array_column(mcpFetchColumns($db, $tableName), 'COLUMN_NAME');
        if (!in_array($orderBy, $columns, true)) {
            throw new InvalidArgumentException('La columna de orden solicitada no existe en la tabla.');
        }

        $direction = strtoupper($orderDirection) === 'DESC' ? 'DESC' : 'ASC';
        $sql .= ' ORDER BY ' . mcpQuoteIdentifier($orderBy) . ' ' . $direction;
    }

    $sql .= ' LIMIT :limit OFFSET :offset';
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':limit', $limit + 1, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $truncated = count($rows) > $limit;
    if ($truncated) {
        array_pop($rows);
    }

    return [
        'table' => $tableName,
        'limit' => $limit,
        'offset' => $offset,
        'returned_rows' => count($rows),
        'truncated' => $truncated,
        'rows' => $rows,
    ];
}

function mcpNormalizeDate(string $date): string
{
    $date = trim($date);
    $dt = DateTime::createFromFormat('Y-m-d', $date);
    if (!$dt || $dt->format('Y-m-d') !== $date) {
        throw new InvalidArgumentException('La fecha debe estar en formato YYYY-MM-DD.');
    }

    return $date;
}

function mcpNormalizeTime(string $time): string
{
    $time = trim($time);
    if (preg_match('/^\d{2}:\d{2}$/', $time)) {
        $time .= ':00';
    }

    $dt = DateTime::createFromFormat('H:i:s', $time);
    if (!$dt || $dt->format('H:i:s') !== $time) {
        throw new InvalidArgumentException('La hora debe estar en formato HH:MM o HH:MM:SS.');
    }

    return $time;
}

function mcpNormalizeOptionalInt($value): ?int
{
    if ($value === null || $value === '') {
        return null;
    }

    if (filter_var($value, FILTER_VALIDATE_INT) === false) {
        throw new InvalidArgumentException('Se esperaba un valor numérico entero.');
    }

    return (int) $value;
}

function mcpRequireConfirmation(array $arguments): void
{
    if (($arguments['confirm'] ?? false) !== true) {
        throw new InvalidArgumentException('Esta operación modifica datos. Reintenta con confirm=true cuando el usuario confirme explícitamente.');
    }
}

function mcpValidateTurnoEstado(string $estado): string
{
    $estado = trim($estado);
    $validStates = ['programado', 'en_proceso', 'completado', 'cancelado'];
    if (!in_array($estado, $validStates, true)) {
        throw new InvalidArgumentException('Estado inválido. Valores permitidos: programado, en_proceso, completado, cancelado.');
    }

    return $estado;
}

function mcpFindCliente(PDO $db, int $clienteId): array
{
    $stmt = $db->prepare('SELECT * FROM clientes WHERE id = :id');
    $stmt->bindValue(':id', $clienteId, PDO::PARAM_INT);
    $stmt->execute();
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cliente) {
        throw new InvalidArgumentException('Cliente no encontrado.');
    }

    return $cliente;
}

function mcpFindVehiculo(PDO $db, int $vehiculoId, int $clienteId): array
{
    $stmt = $db->prepare('SELECT * FROM vehiculos WHERE id = :id AND cliente_id = :cliente_id');
    $stmt->bindValue(':id', $vehiculoId, PDO::PARAM_INT);
    $stmt->bindValue(':cliente_id', $clienteId, PDO::PARAM_INT);
    $stmt->execute();
    $vehiculo = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$vehiculo) {
        throw new InvalidArgumentException('Vehículo no encontrado para el cliente indicado.');
    }

    return $vehiculo;
}

function mcpFindServicio(PDO $db, ?int $servicioId, ?string $fallbackName = null): array
{
    if ($servicioId === null) {
        return [
            'id' => null,
            'nombre' => trim((string) ($fallbackName ?? 'Servicio')) ?: 'Servicio',
            'duracion_estimada' => 60,
        ];
    }

    $stmt = $db->prepare('SELECT id, nombre, duracion_estimada FROM servicios WHERE id = :id');
    $stmt->bindValue(':id', $servicioId, PDO::PARAM_INT);
    $stmt->execute();
    $servicio = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$servicio) {
        throw new InvalidArgumentException('Servicio no encontrado.');
    }

    $servicio['duracion_estimada'] = (int) ($servicio['duracion_estimada'] ?? 60);
    return $servicio;
}

function mcpCalculateHoraFin(string $horaInicio, int $duracionMinutos): string
{
    $inicio = new DateTime($horaInicio);
    $inicio->modify('+' . $duracionMinutos . ' minutes');
    return $inicio->format('H:i:s');
}

function mcpCountTurnosByHour(PDO $db, string $fecha, string $horaInicio, ?int $excludeTurnoId = null): int
{
    $sql = 'SELECT COUNT(*) FROM turnos WHERE fecha = :fecha AND HOUR(hora_inicio) = HOUR(:hora_inicio)';
    if ($excludeTurnoId !== null) {
        $sql .= ' AND id <> :exclude_turno_id';
    }

    $stmt = $db->prepare($sql);
    $stmt->bindValue(':fecha', $fecha);
    $stmt->bindValue(':hora_inicio', $horaInicio);
    if ($excludeTurnoId !== null) {
        $stmt->bindValue(':exclude_turno_id', $excludeTurnoId, PDO::PARAM_INT);
    }
    $stmt->execute();

    return (int) $stmt->fetchColumn();
}

function mcpVehicleHasHourConflict(PDO $db, int $vehiculoId, string $fecha, string $horaInicio, ?int $excludeTurnoId = null): bool
{
    $sql = 'SELECT COUNT(*) FROM turnos WHERE vehiculo_id = :vehiculo_id AND fecha = :fecha AND HOUR(hora_inicio) = HOUR(:hora_inicio)';
    if ($excludeTurnoId !== null) {
        $sql .= ' AND id <> :exclude_turno_id';
    }

    $stmt = $db->prepare($sql);
    $stmt->bindValue(':vehiculo_id', $vehiculoId, PDO::PARAM_INT);
    $stmt->bindValue(':fecha', $fecha);
    $stmt->bindValue(':hora_inicio', $horaInicio);
    if ($excludeTurnoId !== null) {
        $stmt->bindValue(':exclude_turno_id', $excludeTurnoId, PDO::PARAM_INT);
    }
    $stmt->execute();

    return (int) $stmt->fetchColumn() > 0;
}

function mcpSuggestNextAvailableSlot(PDO $db, string $fecha, string $horaInicio, int $vehiculoId, ?int $excludeTurnoId = null, int $maxDays = 14): ?array
{
    $candidate = new DateTime($fecha . ' ' . $horaInicio);

    for ($day = 0; $day <= $maxDays; $day++) {
        $candidateDate = $candidate->format('Y-m-d');
        $candidateTime = $candidate->format('H:i:s');

        $slotCount = mcpCountTurnosByHour($db, $candidateDate, $candidateTime, $excludeTurnoId);
        $vehicleConflict = mcpVehicleHasHourConflict($db, $vehiculoId, $candidateDate, $candidateTime, $excludeTurnoId);

        if ($slotCount < 4 && !$vehicleConflict) {
            return [
                'fecha' => $candidateDate,
                'hora_inicio' => $candidateTime,
                'motivo' => 'mismo horario en la siguiente fecha disponible',
            ];
        }

        $candidate->modify('+1 day');
    }

    return null;
}

function mcpBuildTurnoAvailability(PDO $db, string $fecha, string $horaInicio, int $vehiculoId, ?int $excludeTurnoId = null): array
{
    $slotCount = mcpCountTurnosByHour($db, $fecha, $horaInicio, $excludeTurnoId);
    $vehicleConflict = mcpVehicleHasHourConflict($db, $vehiculoId, $fecha, $horaInicio, $excludeTurnoId);
    $available = $slotCount < 4 && !$vehicleConflict;

    return [
        'fecha' => $fecha,
        'hora_inicio' => $horaInicio,
        'turnos_en_franga_horaria' => $slotCount,
        'cupo_horario_maximo' => 4,
        'vehiculo_duplicado_en_horario' => $vehicleConflict,
        'available' => $available,
        'suggested_slot' => $available ? null : mcpSuggestNextAvailableSlot($db, $fecha, $horaInicio, $vehiculoId, $excludeTurnoId),
    ];
}

function mcpFindTurno(PDO $db, int $turnoId): array
{
    $stmt = $db->prepare('SELECT * FROM turnos WHERE id = :id');
    $stmt->bindValue(':id', $turnoId, PDO::PARAM_INT);
    $stmt->execute();
    $turno = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$turno) {
        throw new InvalidArgumentException('Turno no encontrado.');
    }

    return $turno;
}

function mcpFetchTurnoDetail(PDO $db, int $turnoId): array
{
    $stmt = $db->prepare(
        'SELECT t.*, c.nombre AS cliente_nombre, c.telefono AS cliente_telefono,
                v.marca, v.modelo, v.matricula,
                s.nombre AS servicio_nombre
         FROM turnos t
         LEFT JOIN clientes c ON c.id = t.cliente_id
         LEFT JOIN vehiculos v ON v.id = t.vehiculo_id
         LEFT JOIN servicios s ON s.id = t.servicio_id
         WHERE t.id = :id'
    );
    $stmt->bindValue(':id', $turnoId, PDO::PARAM_INT);
    $stmt->execute();
    $turno = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$turno) {
        throw new InvalidArgumentException('No se pudo recuperar el turno actualizado.');
    }

    return $turno;
}

function mcpCreateTurno(PDO $db, array $arguments): array
{
    $clienteId = mcpNormalizeOptionalInt($arguments['cliente_id'] ?? null);
    $vehiculoId = mcpNormalizeOptionalInt($arguments['vehiculo_id'] ?? null);
    $fecha = mcpNormalizeDate((string) ($arguments['fecha'] ?? ''));
    $horaInicio = mcpNormalizeTime((string) ($arguments['hora_inicio'] ?? ''));
    $servicioId = mcpNormalizeOptionalInt($arguments['servicio_id'] ?? null);
    $mecanico = trim((string) ($arguments['mecanico'] ?? 'Gerente de turno')) ?: 'Gerente de turno';
    $descripcion = trim((string) ($arguments['descripcion'] ?? ''));

    if ($clienteId === null || $vehiculoId === null) {
        throw new InvalidArgumentException('cliente_id y vehiculo_id son obligatorios.');
    }

    mcpFindCliente($db, $clienteId);
    mcpFindVehiculo($db, $vehiculoId, $clienteId);
    $servicio = mcpFindServicio($db, $servicioId, $arguments['servicio'] ?? null);
    $availability = mcpBuildTurnoAvailability($db, $fecha, $horaInicio, $vehiculoId);

    if (!$availability['available']) {
        return [
            'executed' => false,
            'action' => 'create_turno',
            'message' => 'La franja solicitada no está disponible.',
            'availability' => $availability,
            'preview' => [
                'cliente_id' => $clienteId,
                'vehiculo_id' => $vehiculoId,
                'fecha' => $fecha,
                'hora_inicio' => $horaInicio,
                'hora_fin' => mcpCalculateHoraFin($horaInicio, (int) $servicio['duracion_estimada']),
                'servicio_id' => $servicio['id'],
                'servicio' => $servicio['nombre'],
                'mecanico' => $mecanico,
                'descripcion' => $descripcion,
            ],
        ];
    }

    mcpRequireConfirmation($arguments);
    $horaFin = mcpCalculateHoraFin($horaInicio, (int) $servicio['duracion_estimada']);

    $stmt = $db->prepare(
        'INSERT INTO turnos (cliente_id, vehiculo_id, mecanico, fecha, hora_inicio, hora_fin, servicio, servicio_id, descripcion)
         VALUES (:cliente_id, :vehiculo_id, :mecanico, :fecha, :hora_inicio, :hora_fin, :servicio, :servicio_id, :descripcion)'
    );
    $stmt->bindValue(':cliente_id', $clienteId, PDO::PARAM_INT);
    $stmt->bindValue(':vehiculo_id', $vehiculoId, PDO::PARAM_INT);
    $stmt->bindValue(':mecanico', $mecanico);
    $stmt->bindValue(':fecha', $fecha);
    $stmt->bindValue(':hora_inicio', $horaInicio);
    $stmt->bindValue(':hora_fin', $horaFin);
    $stmt->bindValue(':servicio', $servicio['nombre']);
    $stmt->bindValue(':servicio_id', $servicio['id'], $servicio['id'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
    $stmt->bindValue(':descripcion', $descripcion);
    $stmt->execute();

    $turnoId = (int) $db->lastInsertId();
    return [
        'executed' => true,
        'action' => 'create_turno',
        'turno_id' => $turnoId,
        'turno' => mcpFetchTurnoDetail($db, $turnoId),
    ];
}

function mcpUpdateTurno(PDO $db, array $arguments): array
{
    $turnoId = mcpNormalizeOptionalInt($arguments['turno_id'] ?? null);
    if ($turnoId === null) {
        throw new InvalidArgumentException('turno_id es obligatorio.');
    }

    $existingTurno = mcpFindTurno($db, $turnoId);
    $clienteId = mcpNormalizeOptionalInt($arguments['cliente_id'] ?? $existingTurno['cliente_id']);
    $vehiculoId = mcpNormalizeOptionalInt($arguments['vehiculo_id'] ?? $existingTurno['vehiculo_id']);
    $fecha = mcpNormalizeDate((string) ($arguments['fecha'] ?? $existingTurno['fecha']));
    $horaInicio = mcpNormalizeTime((string) ($arguments['hora_inicio'] ?? $existingTurno['hora_inicio']));
    $servicioId = array_key_exists('servicio_id', $arguments)
        ? mcpNormalizeOptionalInt($arguments['servicio_id'])
        : mcpNormalizeOptionalInt($existingTurno['servicio_id'] ?? null);
    $mecanico = trim((string) ($arguments['mecanico'] ?? $existingTurno['mecanico'] ?? 'Gerente de turno')) ?: 'Gerente de turno';
    $descripcion = trim((string) ($arguments['descripcion'] ?? $existingTurno['descripcion'] ?? ''));
    $estado = mcpValidateTurnoEstado((string) ($arguments['estado'] ?? $existingTurno['estado'] ?? 'programado'));

    mcpFindCliente($db, $clienteId ?? 0);
    mcpFindVehiculo($db, $vehiculoId ?? 0, $clienteId ?? 0);
    $servicio = mcpFindServicio($db, $servicioId, $arguments['servicio'] ?? ($existingTurno['servicio'] ?? null));
    $availability = mcpBuildTurnoAvailability($db, $fecha, $horaInicio, $vehiculoId ?? 0, $turnoId);

    if (!$availability['available']) {
        return [
            'executed' => false,
            'action' => 'update_turno',
            'message' => 'La nueva franja solicitada no está disponible.',
            'availability' => $availability,
            'preview' => [
                'turno_id' => $turnoId,
                'cliente_id' => $clienteId,
                'vehiculo_id' => $vehiculoId,
                'fecha' => $fecha,
                'hora_inicio' => $horaInicio,
                'hora_fin' => mcpCalculateHoraFin($horaInicio, (int) $servicio['duracion_estimada']),
                'servicio_id' => $servicio['id'],
                'servicio' => $servicio['nombre'],
                'mecanico' => $mecanico,
                'descripcion' => $descripcion,
                'estado' => $estado,
            ],
        ];
    }

    mcpRequireConfirmation($arguments);
    $horaFin = mcpCalculateHoraFin($horaInicio, (int) $servicio['duracion_estimada']);

    $stmt = $db->prepare(
        'UPDATE turnos
         SET cliente_id = :cliente_id,
             vehiculo_id = :vehiculo_id,
             mecanico = :mecanico,
             fecha = :fecha,
             hora_inicio = :hora_inicio,
             hora_fin = :hora_fin,
             servicio = :servicio,
             servicio_id = :servicio_id,
             descripcion = :descripcion,
             estado = :estado
         WHERE id = :id'
    );
    $stmt->bindValue(':cliente_id', $clienteId, PDO::PARAM_INT);
    $stmt->bindValue(':vehiculo_id', $vehiculoId, PDO::PARAM_INT);
    $stmt->bindValue(':mecanico', $mecanico);
    $stmt->bindValue(':fecha', $fecha);
    $stmt->bindValue(':hora_inicio', $horaInicio);
    $stmt->bindValue(':hora_fin', $horaFin);
    $stmt->bindValue(':servicio', $servicio['nombre']);
    $stmt->bindValue(':servicio_id', $servicio['id'], $servicio['id'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
    $stmt->bindValue(':descripcion', $descripcion);
    $stmt->bindValue(':estado', $estado);
    $stmt->bindValue(':id', $turnoId, PDO::PARAM_INT);
    $stmt->execute();

    return [
        'executed' => true,
        'action' => 'update_turno',
        'turno_id' => $turnoId,
        'turno' => mcpFetchTurnoDetail($db, $turnoId),
    ];
}

function mcpChangeTurnoStatus(PDO $db, array $arguments): array
{
    $turnoId = mcpNormalizeOptionalInt($arguments['turno_id'] ?? null);
    if ($turnoId === null) {
        throw new InvalidArgumentException('turno_id es obligatorio.');
    }

    $turno = mcpFindTurno($db, $turnoId);
    $estado = mcpValidateTurnoEstado((string) ($arguments['estado'] ?? ''));
    mcpRequireConfirmation($arguments);

    $stmt = $db->prepare('UPDATE turnos SET estado = :estado WHERE id = :id');
    $stmt->bindValue(':estado', $estado);
    $stmt->bindValue(':id', $turnoId, PDO::PARAM_INT);
    $stmt->execute();

    return [
        'executed' => true,
        'action' => 'change_turno_status',
        'previous_estado' => $turno['estado'] ?? null,
        'turno_id' => $turnoId,
        'turno' => mcpFetchTurnoDetail($db, $turnoId),
    ];
}

function mcpDeleteTurno(PDO $db, array $arguments): array
{
    $turnoId = mcpNormalizeOptionalInt($arguments['turno_id'] ?? null);
    if ($turnoId === null) {
        throw new InvalidArgumentException('turno_id es obligatorio.');
    }

    $turno = mcpFetchTurnoDetail($db, $turnoId);
    mcpRequireConfirmation($arguments);

    $stmt = $db->prepare('DELETE FROM turnos WHERE id = :id');
    $stmt->bindValue(':id', $turnoId, PDO::PARAM_INT);
    $stmt->execute();

    return [
        'executed' => true,
        'action' => 'delete_turno',
        'deleted_turno_id' => $turnoId,
        'deleted_turno' => $turno,
    ];
}

function mcpBuildDatabaseOverview(PDO $db, bool $includeViews = false): array
{
    $tables = mcpFetchTables($db, $includeViews);
    $tableNames = array_column($tables, 'TABLE_NAME');
    $expected = mcpExpectedCoreTables();

    return [
        'database' => DB_NAME,
        'generated_at' => gmdate(DATE_ATOM),
        'configured_auth_header' => MCP_AUTH_HEADER,
        'auth_scheme' => MCP_AUTH_SCHEME,
        'auth_enabled' => API_TOKEN !== '',
        'table_count' => count($tables),
        'tables' => $tables,
        'expected_core_tables' => $expected,
        'missing_expected_tables' => array_values(array_diff($expected, $tableNames)),
        'unexpected_tables' => array_values(array_diff($tableNames, $expected)),
    ];
}

function mcpBuildTableDescription(PDO $db, string $tableName): array
{
    $tableName = mcpValidateTableName($db, $tableName);

    return [
        'table' => $tableName,
        'columns' => mcpFetchColumns($db, $tableName),
        'indexes' => mcpFetchIndexes($db, $tableName),
        'foreign_keys' => mcpFetchForeignKeys($db, $tableName),
        'row_count' => mcpFetchTableRowCount($db, $tableName),
        'create_statement' => mcpFetchCreateTable($db, $tableName),
    ];
}

function mcpBuildVerificationReport(PDO $db, bool $includeSampleRows = false, int $sampleLimit = 3): array
{
    $tables = mcpFetchTables($db, false);
    $report = [];
    $sampleLimit = max(1, min($sampleLimit, 20));

    foreach ($tables as $table) {
        $tableName = $table['TABLE_NAME'];
        $columns = mcpFetchColumns($db, $tableName);
        $indexes = mcpFetchIndexes($db, $tableName);
        $foreignKeys = mcpFetchForeignKeys($db, $tableName);
        $rowCount = mcpFetchTableRowCount($db, $tableName);

        $entry = [
            'table' => $tableName,
            'engine' => $table['ENGINE'],
            'row_count' => $rowCount,
            'column_count' => count($columns),
            'primary_key_columns' => array_values(array_map(
                static fn(array $column): string => $column['COLUMN_NAME'],
                array_filter($columns, static fn(array $column): bool => $column['COLUMN_KEY'] === 'PRI')
            )),
            'indexes' => $indexes,
            'foreign_keys' => $foreignKeys,
            'status' => 'ok',
        ];

        if ($includeSampleRows) {
            $entry['sample_rows'] = mcpFetchTablePreview($db, $tableName, $sampleLimit, 0)['rows'];
        }

        if ($entry['column_count'] === 0) {
            $entry['status'] = 'warning';
            $entry['warning'] = 'La tabla no devolvió columnas desde information_schema.';
        }

        $report[] = $entry;
    }

    return [
        'database' => DB_NAME,
        'verified_at' => gmdate(DATE_ATOM),
        'table_count' => count($report),
        'missing_expected_tables' => array_values(array_diff(mcpExpectedCoreTables(), array_column($report, 'table'))),
        'tables' => $report,
    ];
}

function mcpReadonlyQueryAllowed(string $query): bool
{
    $query = trim($query);
    if ($query === '') {
        return false;
    }

    if (!preg_match('/^(SELECT|SHOW|DESCRIBE|EXPLAIN|WITH)\b/i', $query)) {
        return false;
    }

    if (preg_match('/\b(INSERT|UPDATE|DELETE|DROP|ALTER|TRUNCATE|REPLACE|CREATE|GRANT|REVOKE|CALL|LOAD|OUTFILE|INFILE)\b/i', $query)) {
        return false;
    }

    if (preg_match('/;\s*\S+/s', $query)) {
        return false;
    }

    return true;
}

function mcpExecuteReadonlyQuery(PDO $db, string $query, int $maxRows = 200): array
{
    if (!mcpReadonlyQueryAllowed($query)) {
        throw new InvalidArgumentException('Solo se permiten consultas de lectura: SELECT, SHOW, DESCRIBE, EXPLAIN o WITH.');
    }

    $maxRows = max(1, min($maxRows, 500));
    $stmt = $db->query($query);
    $rows = [];
    $truncated = false;

    while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
        $rows[] = $row;
        if (count($rows) > $maxRows) {
            $truncated = true;
            array_pop($rows);
            break;
        }
    }

    return [
        'query' => $query,
        'returned_rows' => count($rows),
        'truncated' => $truncated,
        'rows' => $rows,
    ];
}

function mcpToolDefinitions(): array
{
    return [
        [
            'name' => 'stm_database_overview',
            'description' => 'Devuelve el resumen general de la base STM, tablas presentes y faltantes respecto de las esperadas por el sistema.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'include_views' => ['type' => 'boolean'],
                ],
            ],
        ],
        [
            'name' => 'stm_list_tables',
            'description' => 'Lista todas las tablas de la base de datos actual con metadatos básicos.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'include_views' => ['type' => 'boolean'],
                ],
            ],
        ],
        [
            'name' => 'stm_describe_table',
            'description' => 'Describe una tabla: columnas, índices, claves foráneas, conteo real de filas y CREATE TABLE.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'table_name' => ['type' => 'string'],
                ],
                'required' => ['table_name'],
            ],
        ],
        [
            'name' => 'stm_get_table_rows',
            'description' => 'Obtiene una muestra paginada de filas de una tabla.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'table_name' => ['type' => 'string'],
                    'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 200],
                    'offset' => ['type' => 'integer', 'minimum' => 0],
                    'order_by' => ['type' => 'string'],
                    'order_direction' => ['type' => 'string', 'enum' => ['ASC', 'DESC', 'asc', 'desc']],
                ],
                'required' => ['table_name'],
            ],
        ],
        [
            'name' => 'stm_verify_database',
            'description' => 'Verifica todas las tablas accesibles, informando conteos, columnas, índices, relaciones y faltantes esperados.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'include_sample_rows' => ['type' => 'boolean'],
                    'sample_limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 20],
                ],
            ],
        ],
        [
            'name' => 'stm_execute_readonly_query',
            'description' => 'Ejecuta consultas SQL de solo lectura para análisis controlado desde n8n o cualquier cliente MCP.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'query' => ['type' => 'string'],
                    'max_rows' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 500],
                ],
                'required' => ['query'],
            ],
        ],
        [
            'name' => 'stm_create_turno',
            'description' => 'Crea un turno nuevo validando cliente, vehículo, servicio, cupo horario y confirmación explícita.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'cliente_id' => ['type' => 'integer'],
                    'vehiculo_id' => ['type' => 'integer'],
                    'fecha' => ['type' => 'string'],
                    'hora_inicio' => ['type' => 'string'],
                    'servicio_id' => ['type' => 'integer'],
                    'servicio' => ['type' => 'string'],
                    'mecanico' => ['type' => 'string'],
                    'descripcion' => ['type' => 'string'],
                    'confirm' => ['type' => 'boolean'],
                ],
                'required' => ['cliente_id', 'vehiculo_id', 'fecha', 'hora_inicio', 'confirm'],
            ],
        ],
        [
            'name' => 'stm_update_turno',
            'description' => 'Actualiza o reagenda un turno existente con recálculo de hora_fin y validación de cupo horario.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'turno_id' => ['type' => 'integer'],
                    'cliente_id' => ['type' => 'integer'],
                    'vehiculo_id' => ['type' => 'integer'],
                    'fecha' => ['type' => 'string'],
                    'hora_inicio' => ['type' => 'string'],
                    'servicio_id' => ['type' => 'integer'],
                    'servicio' => ['type' => 'string'],
                    'mecanico' => ['type' => 'string'],
                    'descripcion' => ['type' => 'string'],
                    'estado' => ['type' => 'string'],
                    'confirm' => ['type' => 'boolean'],
                ],
                'required' => ['turno_id', 'confirm'],
            ],
        ],
        [
            'name' => 'stm_change_turno_status',
            'description' => 'Cambia el estado de un turno existente con confirmación explícita.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'turno_id' => ['type' => 'integer'],
                    'estado' => ['type' => 'string'],
                    'confirm' => ['type' => 'boolean'],
                ],
                'required' => ['turno_id', 'estado', 'confirm'],
            ],
        ],
        [
            'name' => 'stm_delete_turno',
            'description' => 'Elimina un turno existente con confirmación explícita.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'turno_id' => ['type' => 'integer'],
                    'confirm' => ['type' => 'boolean'],
                ],
                'required' => ['turno_id', 'confirm'],
            ],
        ],
    ];
}

function mcpResourceTemplates(): array
{
    return [
        [
            'uriTemplate' => 'stm://table/{table}/schema',
            'name' => 'Esquema de tabla STM',
            'description' => 'Devuelve columnas, índices y relaciones de una tabla.',
            'mimeType' => 'application/json',
        ],
        [
            'uriTemplate' => 'stm://table/{table}/sample?limit={limit}',
            'name' => 'Muestra de tabla STM',
            'description' => 'Devuelve filas de ejemplo de una tabla.',
            'mimeType' => 'application/json',
        ],
    ];
}

function mcpResourceList(PDO $db): array
{
    $resources = [
        [
            'uri' => 'stm://schema/overview',
            'name' => 'Resumen de base STM',
            'description' => 'Vista consolidada de tablas, auth y consistencia esperada.',
            'mimeType' => 'application/json',
        ],
    ];

    foreach (mcpFetchTables($db, false) as $table) {
        $tableName = $table['TABLE_NAME'];
        $resources[] = [
            'uri' => 'stm://table/' . $tableName . '/schema',
            'name' => 'Esquema de ' . $tableName,
            'mimeType' => 'application/json',
        ];
        $resources[] = [
            'uri' => 'stm://table/' . $tableName . '/sample?limit=10',
            'name' => 'Muestra de ' . $tableName,
            'mimeType' => 'application/json',
        ];
    }

    return $resources;
}

function mcpToolResponse(array $payload, bool $isError = false): array
{
    return [
        'content' => [
            [
                'type' => 'text',
                'text' => json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ],
        ],
        'structuredContent' => $payload,
        'isError' => $isError,
    ];
}

function mcpHandleToolCall(PDO $db, array $params): array
{
    $toolName = $params['name'] ?? null;
    $arguments = is_array($params['arguments'] ?? null) ? $params['arguments'] : [];

    try {
        switch ($toolName) {
            case 'stm_database_overview':
                return mcpToolResponse(mcpBuildDatabaseOverview($db, (bool) ($arguments['include_views'] ?? false)));

            case 'stm_list_tables':
                return mcpToolResponse([
                    'database' => DB_NAME,
                    'tables' => mcpFetchTables($db, (bool) ($arguments['include_views'] ?? false)),
                ]);

            case 'stm_describe_table':
                return mcpToolResponse(mcpBuildTableDescription($db, (string) ($arguments['table_name'] ?? '')));

            case 'stm_get_table_rows':
                $tableName = mcpValidateTableName($db, (string) ($arguments['table_name'] ?? ''));
                return mcpToolResponse(mcpFetchTablePreview(
                    $db,
                    $tableName,
                    (int) ($arguments['limit'] ?? 25),
                    (int) ($arguments['offset'] ?? 0),
                    isset($arguments['order_by']) ? (string) $arguments['order_by'] : null,
                    (string) ($arguments['order_direction'] ?? 'ASC')
                ));

            case 'stm_verify_database':
                return mcpToolResponse(mcpBuildVerificationReport(
                    $db,
                    (bool) ($arguments['include_sample_rows'] ?? false),
                    (int) ($arguments['sample_limit'] ?? 3)
                ));

            case 'stm_execute_readonly_query':
                return mcpToolResponse(mcpExecuteReadonlyQuery(
                    $db,
                    (string) ($arguments['query'] ?? ''),
                    (int) ($arguments['max_rows'] ?? 200)
                ));

            case 'stm_create_turno':
                return mcpToolResponse(mcpCreateTurno($db, $arguments));

            case 'stm_update_turno':
                return mcpToolResponse(mcpUpdateTurno($db, $arguments));

            case 'stm_change_turno_status':
                return mcpToolResponse(mcpChangeTurnoStatus($db, $arguments));

            case 'stm_delete_turno':
                return mcpToolResponse(mcpDeleteTurno($db, $arguments));
        }
    } catch (InvalidArgumentException $exception) {
        return mcpToolResponse([
            'error' => $exception->getMessage(),
            'tool' => $toolName,
        ], true);
    }

    return mcpToolResponse([
        'error' => 'Herramienta MCP no soportada.',
        'tool' => $toolName,
    ], true);
}

function mcpHandleResourceRead(PDO $db, array $params): array
{
    $uri = $params['uri'] ?? '';
    if (!is_string($uri) || trim($uri) === '') {
        throw new InvalidArgumentException('El recurso solicitado debe incluir un URI válido.');
    }

    $parts = parse_url($uri);
    $host = $parts['host'] ?? '';
    $path = trim((string) ($parts['path'] ?? ''), '/');
    $queryParams = [];
    if (isset($parts['query'])) {
        parse_str($parts['query'], $queryParams);
    }

    if ($host === 'schema' && $path === 'overview') {
        $payload = mcpBuildDatabaseOverview($db, false);
    } elseif ($host === 'table') {
        $segments = $path === '' ? [] : explode('/', $path);
        $tableName = $segments[0] ?? '';
        $mode = $segments[1] ?? 'schema';
        $tableName = mcpValidateTableName($db, $tableName);

        if ($mode === 'schema') {
            $payload = mcpBuildTableDescription($db, $tableName);
        } elseif ($mode === 'sample') {
            $payload = mcpFetchTablePreview($db, $tableName, (int) ($queryParams['limit'] ?? 10), 0);
        } else {
            throw new InvalidArgumentException('El recurso solicitado no existe para la tabla indicada.');
        }
    } else {
        throw new InvalidArgumentException('Recurso MCP no reconocido.');
    }

    return [
        'contents' => [
            [
                'uri' => $uri,
                'mimeType' => 'application/json',
                'text' => json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ],
        ],
    ];
}

function mcpHandleJsonRpc(PDO $db, array $payload): void
{
    $method = $payload['method'];
    $id = $payload['id'] ?? null;
    $params = is_array($payload['params'] ?? null) ? $payload['params'] : [];

    if (!mcpHasValidToken()) {
        mcpJsonRpcError($id, -32001, 'Autenticación inválida para el servidor MCP.', [
            'expected_header' => MCP_AUTH_HEADER,
            'expected_scheme' => MCP_AUTH_SCHEME,
        ], 401);
    }

    try {
        switch ($method) {
            case 'initialize':
                mcpJsonRpcResult($id, [
                    'protocolVersion' => MCP_PROTOCOL_VERSION,
                    'capabilities' => [
                        'tools' => ['listChanged' => false],
                        'resources' => ['listChanged' => false],
                    ],
                    'serverInfo' => [
                        'name' => MCP_SERVER_NAME,
                        'version' => '1.0.0',
                    ],
                    'instructions' => 'Servidor MCP HTTP para STM Taller. Permite inspección completa de la base y escritura controlada sobre turnos con confirmación explícita.',
                ]);

            case 'notifications/initialized':
                mcpNotificationAccepted();

            case 'ping':
                mcpJsonRpcResult($id, [
                    'ok' => true,
                    'server' => MCP_SERVER_NAME,
                    'timestamp' => gmdate(DATE_ATOM),
                    'database' => DB_NAME,
                ]);

            case 'tools/list':
                mcpJsonRpcResult($id, ['tools' => mcpToolDefinitions()]);

            case 'tools/call':
                mcpJsonRpcResult($id, mcpHandleToolCall($db, $params));

            case 'resources/list':
                mcpJsonRpcResult($id, ['resources' => mcpResourceList($db)]);

            case 'resources/templates/list':
                mcpJsonRpcResult($id, ['resourceTemplates' => mcpResourceTemplates()]);

            case 'resources/read':
                mcpJsonRpcResult($id, mcpHandleResourceRead($db, $params));

            case 'prompts/list':
                mcpJsonRpcResult($id, ['prompts' => []]);

            default:
                mcpJsonRpcError($id, -32601, 'Método MCP no soportado.');
        }
    } catch (InvalidArgumentException $exception) {
        mcpJsonRpcError($id, -32602, $exception->getMessage());
    } catch (Throwable $exception) {
        error_log('STM MCP error: ' . $exception->getMessage());
        mcpJsonRpcError($id, -32000, 'Error interno del servidor MCP.', [
            'message' => $exception->getMessage(),
        ], 500);
    }
}

$payload = mcpDecodeJsonBody();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && mcpIsJsonRpcRequest($payload)) {
    mcpHandleJsonRpc($db, $payload);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && !isset($_GET['resource'])) {
    mcpJsonResponse([
        'success' => true,
        'server' => MCP_SERVER_NAME,
        'transport' => 'streamable-http',
        'endpoint' => '/mcp/',
        'jsonrpc' => '2.0',
        'auth' => [
            'enabled' => API_TOKEN !== '',
            'header' => MCP_AUTH_HEADER,
            'scheme' => MCP_AUTH_SCHEME,
        ],
        'capabilities' => [
            'tools' => array_column(mcpToolDefinitions(), 'name'),
            'resources' => [
                'stm://schema/overview',
                'stm://table/{table}/schema',
                'stm://table/{table}/sample?limit={limit}',
            ],
        ],
        'legacy_api' => 'Disponible con query resource=clients|vehicles|orders y POST resource=turnos|telegram.',
    ]);
}

require_once __DIR__ . '/legacy_api.php';
runLegacyMcpApi($db);
