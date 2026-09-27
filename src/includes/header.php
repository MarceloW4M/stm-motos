<?php
require_once __DIR__ . '/config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<header class="header">
    <div class="logo">
        <?php if (file_exists(__DIR__ . '/../css/img/logo01.png')): ?>
            <img src="/css/img/logo01.png" alt="Logo">
        <?php endif; ?>
        <h1><?php echo SITE_NAME; ?></h1>
    </div>
    <nav>
        <ul class="nav-menu">
            <li><a href="dashboard.php">Inicio</a></li>
            <?php if (isset($_SESSION['user_id'])): ?>
                <li><a href="change_password.php">Cambiar contraseña</a></li>
                <li><a href="logout.php">Cerrar sesión (<?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?>)</a></li>
            <?php else: ?>
                <li><a href="login.php">Ingresar</a></li>
            <?php endif; ?>
        </ul>
    </nav>
</header>
