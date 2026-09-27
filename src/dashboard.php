<?php
require_once 'includes/auth.php';
require_once 'includes/config.php';

requireAuth();
// Mostrar información mínima del dashboard
$username = $_SESSION['username'] ?? 'Usuario';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Dashboard - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <?php include __DIR__ . '/includes/header.php'; ?>

    <main class="container">
        <div class="login-container">
            <h2>Bienvenido, <?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?></h2>
            <p>Este es un dashboard minimal. Puedes personalizarlo desde el directorio <strong>src/</strong>.</p>
        </div>
    </main>
</body>
</html>
