<?php
// Script para crear la BD (si no existe) y conceder permisos al usuario de la app.
// Uso: php grant_privileges.php <host> <port> <root_user> <root_pass> <db_name> <target_user> <target_host> <target_user_pass>

if ($argc < 9) {
    fwrite(STDERR, "Uso: php grant_privileges.php <host> <port> <root_user> <root_pass> <db_name> <target_user> <target_host> <target_user_pass>\n");
    exit(1);
}

$host = $argv[1];
$port = $argv[2];
$rootUser = $argv[3];
$rootPass = $argv[4];
$dbName = $argv[5];
$targetUser = $argv[6];
$targetHost = $argv[7];
$targetPass = $argv[8];

try {
    $dsn = "mysql:host={$host};port={$port};charset=utf8mb4";
    $pdo = new PDO($dsn, $rootUser, $rootPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (Exception $e) {
    fwrite(STDERR, "No se pudo conectar como root: " . $e->getMessage() . PHP_EOL);
    exit(2);
}

try {
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . str_replace('`', '``', $dbName) . "` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    // Crear usuario para host específico y asignar contraseña
    $createUserHost = sprintf("CREATE USER IF NOT EXISTS '%s'@'%s' IDENTIFIED BY '%s'", addslashes($targetUser), addslashes($targetHost), addslashes($targetPass));
    $pdo->exec($createUserHost);
    $grantHost = sprintf("GRANT ALL PRIVILEGES ON `%s`.* TO '%s'@'%s'", str_replace('`', '``', $dbName), addslashes($targetUser), addslashes($targetHost));
    $pdo->exec($grantHost);

    // Crear usuario para cualquier host ('%') y otorgar permisos también
    $createUserAny = sprintf("CREATE USER IF NOT EXISTS '%s'@'%%' IDENTIFIED BY '%s'", addslashes($targetUser), addslashes($targetPass));
    $pdo->exec($createUserAny);
    $grantAny = sprintf("GRANT ALL PRIVILEGES ON `%s`.* TO '%s'@'%%'", str_replace('`', '``', $dbName), addslashes($targetUser));
    $pdo->exec($grantAny);

    $pdo->exec('FLUSH PRIVILEGES');
    echo "Permisos concedidos correctamente para {$targetUser} en base {$dbName}.\n";
    exit(0);
} catch (Exception $e) {
    fwrite(STDERR, "Error al ejecutar sentencias: " . $e->getMessage() . PHP_EOL);
    exit(3);
}
