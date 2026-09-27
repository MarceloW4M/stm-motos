<?php
// Configuración de la base de datos
// Se permite lectura desde variables de entorno para mayor flexibilidad en Docker
// Por defecto usar el servicio mysql interno de Docker.
define('DB_HOST', getenv('DB_HOST') ?: getenv('MYSQL_HOST') ?: 'mysql');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: getenv('MYSQL_DATABASE') ?: 'dfs-web');
define('DB_USER', getenv('DB_USER') ?: getenv('MYSQL_USER') ?: 'surbat_user');
// Contraseña por defecto (si no se proporcionan variables de entorno).
define('DB_PASS', getenv('DB_PASS') ?: getenv('MYSQL_PASSWORD') ?: getenv('MYSQL_ROOT_PASSWORD') ?: 'surbat_pass');

// Otras configuraciones
define('SITE_NAME', 'Sur Bateria');
define('OPEN_TIME', '08:00:00');
define('CLOSE_TIME', '20:00:00');

// Chatbot n8n (VPS). Dejar vacio para desactivar el envio.
define('N8N_CHAT_WEBHOOK_URL', '');
define('N8N_CHAT_TIMEOUT_MS', 30000);

// Token API para proteger endpoints usados por n8n u otros servicios.
// Por seguridad NO se incluye un valor por defecto aquí. Establecer en el
// entorno del servidor (ej. export API_TOKEN="tu_token_seguro") o en
// las variables del servicio/container antes de arrancar la aplicación.
define('API_TOKEN', getenv('API_TOKEN') ?: '');
define('MCP_AUTH_HEADER', getenv('MCP_AUTH_HEADER') ?: 'Authorization');
define('MCP_AUTH_SCHEME', getenv('MCP_AUTH_SCHEME') ?: 'Bearer');
define('MCP_SERVER_NAME', getenv('MCP_SERVER_NAME') ?: 'stm-mcp');
define('MCP_PROTOCOL_VERSION', getenv('MCP_PROTOCOL_VERSION') ?: '2025-03-26');

// Función para conexión a BD
function getDBConnection() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $db = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        return $db;
    } catch(PDOException $e) {
        // Registrar el error en logs pero lanzar la excepción para manejo superior
        error_log('DB connection error: ' . $e->getMessage());
        throw $e;
    }
}

