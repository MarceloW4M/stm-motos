<?php
// Iniciar sesión solo si no está ya iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar si el usuario está autenticado
function isAuthenticated() {
    return isset($_SESSION['user_id']);
}

// Redirigir si no está autenticado
function requireAuth() {
    if (!isAuthenticated()) {
        header("Location: ../login.php");
        exit();
    }
}

// Cerrar sesión
function logout() {
    session_unset();
    session_destroy();
    header("Location: ../login.php");
    exit();
}
?>