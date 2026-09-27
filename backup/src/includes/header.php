<?php
require_once __DIR__ . '/config.php';
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title : SITE_NAME; ?></title>
    <link rel="icon" type="image/png" href="/css/img/logo01.png">
    <link rel="stylesheet" href="css/style.css">
</head>
<body<?php echo isset($body_class) && $body_class !== '' ? ' class="' . htmlspecialchars($body_class, ENT_QUOTES, 'UTF-8') . '"' : ''; ?>>
    <header class="header">
        <div class="logo">           
            <h1><?php echo SITE_NAME; ?></h1>
        </div>
        
        <nav>
            <ul class="nav-menu">
                <li><a href="dashboard.php">Inicio</a></li>
                <li><a href="clientes.php">Clientes</a></li>
                <li><a href="vehiculos.php">Vehículos</a></li>
                <li class="nav-dropdown">
                    <a href="repuestos.php">Repuestos</a>
                    <button type="button" class="submenu-toggle" aria-expanded="false" aria-label="Abrir opciones de repuestos y servicios">▼</button>
                    <ul class="nav-submenu">
                        <li><a href="repuestos.php">Repuestos</a></li>
                        <li><a href="servicios.php">Servicios</a></li>
                    </ul>
                </li>
                <li><a href="ordenes.php">Ordenes</a></li>
                <li><a href="turnos.php">Turnos</a></li>
                <li><a href="informes.php">Informes</a></li>
                <li class="nav-dropdown">
                    <a href="administracion.php">Administracion</a>
                    <button type="button" class="submenu-toggle" aria-expanded="false" aria-label="Abrir opciones de administracion y facturacion">▼</button>
                    <ul class="nav-submenu">
                        <li><a href="administracion.php">Administracion</a></li>
                        <li><a href="facturacion.php">Facturacion</a></li>
                        <li><a href="presupuestos.php">Presupuestos</a></li>
                        <li><a href="remitos.php">Remitos</a></li>
                    </ul>
                </li>
                <li><a href="logout.php">Cerrar Sesión (<?php echo $_SESSION['username']; ?>)</a></li>
            </ul>
        </nav>
    </header>
    
    <main>
