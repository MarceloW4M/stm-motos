<?php
require_once 'includes/auth.php';
require_once 'includes/database.php';

requireAuth();

$orden_id = $_GET['orden_id'] ?? null;
$registro_id = $_GET['repuesto_id'] ?? null; // this is actually the id in orden_repuestos

if (!$orden_id || !$registro_id) {
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
    // primero obtener cantidad y ref al repuesto para devolver stock
    $query = "SELECT cantidad, repuesto_id FROM orden_repuestos WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $registro_id, PDO::PARAM_INT);
    $stmt->execute();
    $fila = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($fila) {
        $cantidad = $fila['cantidad'];
        $repuesto_id = $fila['repuesto_id'];

        // devolver stock
        $upd = "UPDATE repuestos SET stock = stock + :cantidad WHERE id = :rid";
        $stmt2 = $db->prepare($upd);
        $stmt2->bindParam(':cantidad', $cantidad, PDO::PARAM_INT);
        $stmt2->bindParam(':rid', $repuesto_id, PDO::PARAM_INT);
        $stmt2->execute();
    }

    // eliminar el registro de la orden
    $del = "DELETE FROM orden_repuestos WHERE id = :id";
    $stmt3 = $db->prepare($del);
    $stmt3->bindParam(':id', $registro_id, PDO::PARAM_INT);
    $stmt3->execute();
} catch (Exception $e) {
    // ignorar errores, redirigir de todas formas
}

header("Location: detalle_orden.php?id=" . urlencode($orden_id));
exit();
