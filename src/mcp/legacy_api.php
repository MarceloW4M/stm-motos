<?php

function runLegacyMcpApi(PDO $db): void
{
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $segments = array_values(array_filter(explode('/', $path)));
    $mcpIndex = array_search('mcp', $segments, true);
    $resource = null;

    if ($mcpIndex !== false) {
        $next = $segments[$mcpIndex + 1] ?? null;
        if ($next === 'index.php') {
            $resource = $segments[$mcpIndex + 2] ?? null;
        } else {
            $resource = $next;
        }
    }

    if (($resource === null || $resource === '') && isset($_GET['resource'])) {
        $resource = (string) $_GET['resource'];
    }

    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        if ($resource === 'clients') {
            $id = $_GET['id'] ?? null;
            $cuit = $_GET['cuit'] ?? null;
            $idBoot = $_GET['id_boot'] ?? null;

            if ($id) {
                $stmt = $db->prepare('SELECT * FROM clientes WHERE id = :id');
                $stmt->bindValue(':id', $id, PDO::PARAM_INT);
                $stmt->execute();
                mcpJsonResponse(['success' => true, 'client' => $stmt->fetch(PDO::FETCH_ASSOC)]);
            }

            if ($cuit) {
                $stmt = $db->prepare('SELECT * FROM clientes WHERE cuit = :cuit LIMIT 1');
                $stmt->bindValue(':cuit', $cuit);
                $stmt->execute();
                mcpJsonResponse(['success' => true, 'client' => $stmt->fetch(PDO::FETCH_ASSOC)]);
            }

            if ($idBoot) {
                $stmt = $db->prepare('SELECT * FROM clientes WHERE id_boot = :id_boot LIMIT 1');
                $stmt->bindValue(':id_boot', $idBoot);
                $stmt->execute();
                mcpJsonResponse(['success' => true, 'client' => $stmt->fetch(PDO::FETCH_ASSOC)]);
            }

            $limit = isset($_GET['limit']) ? max(1, min((int) $_GET['limit'], 100)) : 50;
            $stmt = $db->prepare('SELECT id, nombre, telefono, cuit, id_boot FROM clientes ORDER BY nombre LIMIT :limit');
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            mcpJsonResponse(['success' => true, 'count' => count($rows), 'rows' => $rows]);
        }

        if ($resource === 'vehicles') {
            $id = $_GET['id'] ?? null;
            $clienteId = $_GET['cliente_id'] ?? null;

            if ($id) {
                $stmt = $db->prepare('SELECT * FROM vehiculos WHERE id = :id');
                $stmt->bindValue(':id', $id, PDO::PARAM_INT);
                $stmt->execute();
                mcpJsonResponse(['success' => true, 'vehicle' => $stmt->fetch(PDO::FETCH_ASSOC)]);
            }

            if ($clienteId) {
                $stmt = $db->prepare('SELECT * FROM vehiculos WHERE cliente_id = :cliente_id ORDER BY marca, modelo');
                $stmt->bindValue(':cliente_id', $clienteId, PDO::PARAM_INT);
                $stmt->execute();
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                mcpJsonResponse(['success' => true, 'count' => count($rows), 'rows' => $rows]);
            }

            mcpJsonResponse(['success' => false, 'message' => 'Parámetros insuficientes'], 400);
        }

        if ($resource === 'orders') {
            $id = $_GET['id'] ?? null;
            $clienteId = $_GET['cliente_id'] ?? null;
            $vehiculoId = $_GET['vehiculo_id'] ?? null;

            if ($id) {
                $stmt = $db->prepare('SELECT * FROM ordenes_reparacion WHERE id = :id');
                $stmt->bindValue(':id', $id, PDO::PARAM_INT);
                $stmt->execute();
                mcpJsonResponse(['success' => true, 'order' => $stmt->fetch(PDO::FETCH_ASSOC)]);
            }

            if ($clienteId || $vehiculoId) {
                $conditions = [];
                $params = [];

                if ($clienteId) {
                    $conditions[] = 't.cliente_id = :cliente_id';
                    $params[':cliente_id'] = [(int) $clienteId, PDO::PARAM_INT];
                }

                if ($vehiculoId) {
                    $conditions[] = 't.vehiculo_id = :vehiculo_id';
                    $params[':vehiculo_id'] = [(int) $vehiculoId, PDO::PARAM_INT];
                }

                $stmt = $db->prepare(
                    'SELECT o.*
                     FROM ordenes_reparacion o
                     INNER JOIN turnos t ON o.turno_id = t.id
                     WHERE ' . implode(' AND ', $conditions) . '
                     ORDER BY o.id DESC
                     LIMIT 100'
                );

                foreach ($params as $name => [$value, $type]) {
                    $stmt->bindValue($name, $value, $type);
                }

                $stmt->execute();
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                mcpJsonResponse(['success' => true, 'count' => count($rows), 'rows' => $rows]);
            }

            mcpJsonResponse(['success' => false, 'message' => 'Parámetros insuficientes'], 400);
        }

        mcpJsonResponse(['success' => false, 'message' => 'Recurso no encontrado'], 404);
    }

    if ($method === 'POST') {
        if (!mcpHasValidToken()) {
            mcpJsonResponse(['success' => false, 'message' => 'API token inválido o faltante'], 401);
        }

        $body = mcpDecodeJsonBody() ?? $_POST;

        if ($resource === 'turnos') {
            $clienteId = $body['cliente_id'] ?? null;
            $vehiculoId = $body['vehiculo_id'] ?? null;
            $fecha = $body['fecha'] ?? null;
            $horaInicio = $body['hora_inicio'] ?? null;
            $mecanico = $body['mecanico'] ?? 'Gerente de turno';
            $servicioId = $body['servicio_id'] ?? null;
            $descripcion = $body['descripcion'] ?? '';

            if (!$clienteId || !$vehiculoId || !$fecha || !$horaInicio) {
                mcpJsonResponse(['success' => false, 'message' => 'cliente_id, vehiculo_id, fecha y hora_inicio son obligatorios'], 400);
            }

            $stmt = $db->prepare('SELECT id FROM clientes WHERE id = :id');
            $stmt->bindValue(':id', $clienteId, PDO::PARAM_INT);
            $stmt->execute();
            if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
                mcpJsonResponse(['success' => false, 'message' => 'Cliente no encontrado'], 404);
            }

            $stmt = $db->prepare('SELECT id FROM vehiculos WHERE id = :id AND cliente_id = :cliente_id');
            $stmt->bindValue(':id', $vehiculoId, PDO::PARAM_INT);
            $stmt->bindValue(':cliente_id', $clienteId, PDO::PARAM_INT);
            $stmt->execute();
            if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
                mcpJsonResponse(['success' => false, 'message' => 'Vehículo no encontrado para ese cliente'], 404);
            }

            $duracionMinutos = 60;
            $nombreServicio = '';
            if ($servicioId) {
                $stmt = $db->prepare('SELECT nombre, duracion_estimada FROM servicios WHERE id = :id');
                $stmt->bindValue(':id', $servicioId, PDO::PARAM_INT);
                $stmt->execute();
                $servicio = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($servicio) {
                    $nombreServicio = $servicio['nombre'];
                    $duracionMinutos = (int) ($servicio['duracion_estimada'] ?? 60);
                }
            }

            try {
                $inicio = new DateTime($horaInicio);
            } catch (Exception $exception) {
                $partesHora = explode(':', (string) $horaInicio);
                $inicio = new DateTime();
                $inicio->setTime((int) ($partesHora[0] ?? 0), (int) ($partesHora[1] ?? 0));
            }

            $inicio->modify('+' . $duracionMinutos . ' minutes');
            $horaFin = $inicio->format('H:i:s');

            try {
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
                $stmt->bindValue(':servicio', $nombreServicio);
                $stmt->bindValue(':servicio_id', $servicioId, $servicioId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
                $stmt->bindValue(':descripcion', $descripcion);
                $stmt->execute();
            } catch (PDOException $exception) {
                mcpJsonResponse(['success' => false, 'message' => 'Error al insertar turno: ' . $exception->getMessage()], 500);
            }

            mcpJsonResponse(['success' => true, 'turno_id' => $db->lastInsertId()]);
        }

        if ($resource === 'telegram') {
            $chatId = $body['chat_id'] ?? null;
            $text = trim((string) ($body['text'] ?? ''));

            if (!$chatId || $text === '') {
                mcpJsonResponse(['success' => false, 'message' => 'chat_id y text obligatorios'], 400);
            }

            if (stripos($text, '/mis_turnos') === 0) {
                $parts = preg_split('/\s+/', $text);
                $identificador = $parts[1] ?? null;
                if (!$identificador) {
                    mcpJsonResponse(['success' => false, 'message' => 'Enviar CUIT: /mis_turnos 20-...'], 400);
                }

                $stmt = $db->prepare('SELECT id, nombre FROM clientes WHERE cuit = :ident OR id_boot = :ident LIMIT 1');
                $stmt->bindValue(':ident', $identificador);
                $stmt->execute();
                $cliente = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$cliente) {
                    mcpJsonResponse(['success' => true, 'reply' => 'No encontré cliente para ' . $identificador, 'chat_id' => $chatId]);
                }

                $stmt = $db->prepare(
                    'SELECT fecha, hora_inicio, servicio
                     FROM turnos
                     WHERE cliente_id = :cliente_id AND fecha >= CURDATE()
                     ORDER BY fecha ASC
                     LIMIT 10'
                );
                $stmt->bindValue(':cliente_id', $cliente['id'], PDO::PARAM_INT);
                $stmt->execute();
                $turnos = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if (empty($turnos)) {
                    $reply = 'Hola ' . $cliente['nombre'] . ', no tenés turnos programados.';
                } else {
                    $lineas = [];
                    foreach ($turnos as $turno) {
                        $lineas[] = $turno['fecha'] . ' ' . $turno['hora_inicio'] . ' - ' . $turno['servicio'];
                    }
                    $reply = 'Turnos para ' . $cliente['nombre'] . ":\n" . implode("\n", $lineas);
                }

                mcpJsonResponse(['success' => true, 'reply' => $reply, 'chat_id' => $chatId]);
            }

            mcpJsonResponse(['success' => true, 'reply' => 'Recibí: ' . $text, 'chat_id' => $chatId]);
        }

        mcpJsonResponse(['success' => false, 'message' => 'Recurso no encontrado para POST'], 404);
    }

    mcpJsonResponse(['success' => false, 'message' => 'Método no soportado'], 405);
}