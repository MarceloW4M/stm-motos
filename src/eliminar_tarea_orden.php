<?php
require_once 'includes/auth.php';
require_once 'includes/database.php';

requireAuth();

$orden_id = $_GET['orden_id'] ?? null;
$tarea_id = $_GET['tarea_id'] ?? null;

if (!$orden_id || !$tarea_id) {
    header("Location: ordenes.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// No permitir modificaciones si la orden está finalizada
$query_estado = "SELECT estado FROM ordenes_reparacion WHERE id = :orden_id";
$stmt_estado = $db->prepare($query_estado);
$stmt_estado->bindParam(':orden_id', $orden_id, PDO::PARAM_INT);
$stmt_estado->execute();
$estado = $stmt_estado->fetchColumn();
if (in_array($estado, ['completada', 'facturada', 'cancelada'], true)) {
    header("Location: detalle_orden.php?id=" . urlencode($orden_id));
    exit();
}

try {
    $del = "DELETE FROM orden_tareas WHERE id = :id";
    $stmt = $db->prepare($del);
    $stmt->bindParam(':id', $tarea_id, PDO::PARAM_INT);
    $stmt->execute();
} catch (Exception $e) {
    // noop
}

header("Location: detalle_orden.php?id=" . urlencode($orden_id));
exit();
