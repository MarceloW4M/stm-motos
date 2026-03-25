<?php
// API pública mínima: buscar cliente por cuit o id_telegram.
// Devuelve JSON: { success: true, client: { ... } } o { success: false, message: '...' }

require_once __DIR__ . '/../includes/database.php';

header('Content-Type: application/json; charset=utf-8');
// Permitir llamadas desde n8n u otros servicios internos (ajustar en producción)
header('Access-Control-Allow-Origin: *');

$method = $_SERVER['REQUEST_METHOD'];
$data = [];
if ($method === 'POST') {
    $input = file_get_contents('php://input');
    $json = json_decode($input, true);
    if (is_array($json)) $data = $json;
    // also accept form-encoded
    $data = array_merge($data, $_POST);
} else {
    $data = $_GET;
}

$cuit = isset($data['cuit']) ? trim((string)$data['cuit']) : '';
$id_telegram = isset($data['id_telegram']) ? trim((string)$data['id_telegram']) : '';
$id_boot = isset($data['id_boot']) ? trim((string)$data['id_boot']) : '';

if ($cuit === '' && $id_telegram === '' && $id_boot === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Se requiere parámetro cuit, id_telegram o id_boot']);
    exit;
}

try {
    $db = (new \Database())->getConnection();

    // Comprobar si la columna id_telegram existe (no siempre está en todas las instalaciones)
    $hasIdTelegram = false;
    try {
        $colCheck = $db->prepare("SHOW COLUMNS FROM clientes LIKE 'id_telegram'");
        $colCheck->execute();
        $hasIdTelegram = (bool) $colCheck->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $hasIdTelegram = false;
    }

    $client = null;

    // Priorizar búsqueda por id_boot, luego id_telegram (si existe), luego cuit
    if ($id_boot !== '') {
        $query = 'SELECT id,nombre,telefono,email,cuit,id_boot FROM clientes WHERE id_boot = :id_boot LIMIT 1';
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id_boot', $id_boot);
        $stmt->execute();
        $client = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } elseif ($hasIdTelegram && $id_telegram !== '') {
        $query = 'SELECT id,nombre,telefono,email,cuit,id_telegram,id_boot FROM clientes WHERE id_telegram = :id_telegram LIMIT 1';
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id_telegram', $id_telegram);
        $stmt->execute();
        $client = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } else {
        // Buscar por CUIT (campo id_boot incluido en la respuesta)
        $query = 'SELECT id,nombre,telefono,email,cuit,id_boot FROM clientes WHERE cuit = :cuit LIMIT 1';
        $stmt = $db->prepare($query);
        $stmt->bindParam(':cuit', $cuit);
        $stmt->execute();
        $client = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    if ($client) {
        echo json_encode(['success' => true, 'found' => true, 'client' => $client]);
        exit;
    }

    // Autenticación por token (opcional): revisar header Authorization: Bearer <token> o X-API-KEY
    $requiredToken = defined('API_TOKEN') ? API_TOKEN : '';
    if (!empty($requiredToken)) {
        $provided = '';
        // getallheaders may not be available in some SAPIs; try to read common server vars too
        if (function_exists('getallheaders')) {
            $hdrs = getallheaders();
            if (!empty($hdrs['Authorization'])) $provided = $hdrs['Authorization'];
            elseif (!empty($hdrs['authorization'])) $provided = $hdrs['authorization'];
            elseif (!empty($hdrs['X-API-KEY'])) $provided = $hdrs['X-API-KEY'];
            elseif (!empty($hdrs['x-api-key'])) $provided = $hdrs['x-api-key'];
        }
        if (empty($provided)) {
            if (!empty($_SERVER['HTTP_AUTHORIZATION'])) $provided = $_SERVER['HTTP_AUTHORIZATION'];
            elseif (!empty($_SERVER['HTTP_X_API_KEY'])) $provided = $_SERVER['HTTP_X_API_KEY'];
        }
        // soportar formato "Bearer TOKEN"
        if (stripos($provided, 'Bearer ') === 0) {
            $provided = substr($provided, 7);
        }
        if ($provided !== $requiredToken) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Unauthorized: invalid API token']);
            exit;
        }
    }

    // Si no encontrado, intentar búsqueda alternativa por CUIT si se envió id_telegram
    if ($client === null && $hasIdTelegram && $id_telegram !== '' && $cuit !== '') {
        $query = 'SELECT id,nombre,telefono,email,cuit,id_telegram,id_boot FROM clientes WHERE cuit = :cuit LIMIT 1';
        $stmt = $db->prepare($query);
        $stmt->bindParam(':cuit', $cuit);
        $stmt->execute();
        $client = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($client) {
            echo json_encode(['success' => true, 'client' => $client]);
            exit;
        }
    }

    // Devolver JSON consistente para n8n: indicar found=false y client=null
    echo json_encode(['success' => false, 'found' => false, 'client' => null, 'message' => 'Cliente no encontrado']);
    exit;

} catch (Exception $e) {
    // Registrar el error real en logs del servidor para depuración
    error_log('API get_client error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor (BD)']);
    exit;
}
