<?php
require_once 'includes/auth.php';
require_once 'includes/database.php';
require_once 'includes/administracion_schema.php';

requireAuth();

$database = new Database();
$db = $database->getConnection();
ensureAdministracionSchema($db);
$ordenesDisponibles = adminTableExists($db, 'ordenes_reparacion') && adminTableExists($db, 'orden_repuestos') && adminTableExists($db, 'orden_tareas');

$mensaje = $_GET['mensaje'] ?? '';
$tipo_mensaje = $_GET['tipo'] ?? 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['facturar_orden'])) {
            if (!$ordenesDisponibles) {
                throw new RuntimeException('La facturacion de ordenes requiere las tablas de ordenes_reparacion, orden_repuestos y orden_tareas.');
            }

            $ordenId = (int) ($_POST['orden_factura_id'] ?? 0);
            $tipoFacturaId = (int) ($_POST['tipo_factura_id'] ?? 0);
            $ivaId = (int) ($_POST['iva_factura_id'] ?? 0);
            $impuestoId = (int) ($_POST['impuesto_factura_id'] ?? 0);
            $numeroFactura = trim((string) ($_POST['numero_factura'] ?? ''));
            $moneda = strtoupper(trim((string) ($_POST['moneda_factura'] ?? 'ARS')));
            $observaciones = trim((string) ($_POST['observaciones_factura'] ?? ''));

            if ($ordenId <= 0 || $tipoFacturaId <= 0) {
                throw new RuntimeException('Seleccione la orden y el tipo de factura.');
            }

            if ($numeroFactura === '') {
                $numeroFactura = nextAdminNumber($db, 'admin_facturas', 'numero_factura', 'FAC');
            }

            $stmtOrden = $db->prepare("SELECT 
                    COALESCE((SELECT SUM(cantidad * precio_unitario) FROM orden_repuestos WHERE orden_id = :orden_id), 0) AS total_repuestos,
                    COALESCE((SELECT SUM(tiempo_horas * costo_hora) FROM orden_tareas WHERE orden_id = :orden_id), 0) AS total_mano_obra
                FROM ordenes_reparacion WHERE id = :orden_id LIMIT 1");
            $stmtOrden->bindParam(':orden_id', $ordenId, PDO::PARAM_INT);
            $stmtOrden->execute();
            $totales = $stmtOrden->fetch(PDO::FETCH_ASSOC);

            if (!$totales) {
                throw new RuntimeException('La orden seleccionada no existe.');
            }

            $subtotal = (float) $totales['total_repuestos'] + (float) $totales['total_mano_obra'];
            $ivaPorcentaje = getPorcentajeById($db, 'admin_iva', $ivaId);
            $impuestoData = getImpuestoData($db, $impuestoId);
            $impuestosTotal = $subtotal * ($ivaPorcentaje / 100);
            $impuestosTotal += $impuestoData['tipo'] === 'fijo'
                ? $impuestoData['valor']
                : $subtotal * ($impuestoData['valor'] / 100);
            $total = $subtotal + $impuestosTotal;
            $cotizacionDolar = (float) getAdminConfig($db, 'valor_dolar', '1.00');

            $db->beginTransaction();

            $stmtFactura = $db->prepare('INSERT INTO admin_facturas (orden_id, tipo_factura_id, numero_factura, fecha_emision, subtotal, impuestos_total, total, moneda, cotizacion_dolar, observaciones)
                                         VALUES (:orden_id, :tipo_factura_id, :numero_factura, CURDATE(), :subtotal, :impuestos_total, :total, :moneda, :cotizacion_dolar, :observaciones)');
            $stmtFactura->bindParam(':orden_id', $ordenId, PDO::PARAM_INT);
            $stmtFactura->bindParam(':tipo_factura_id', $tipoFacturaId, PDO::PARAM_INT);
            $stmtFactura->bindParam(':numero_factura', $numeroFactura);
            $stmtFactura->bindParam(':subtotal', $subtotal);
            $stmtFactura->bindParam(':impuestos_total', $impuestosTotal);
            $stmtFactura->bindParam(':total', $total);
            $stmtFactura->bindParam(':moneda', $moneda);
            $stmtFactura->bindParam(':cotizacion_dolar', $cotizacionDolar);
            $stmtFactura->bindParam(':observaciones', $observaciones);
            $stmtFactura->execute();

            $stmtEstado = $db->prepare("UPDATE ordenes_reparacion SET estado = 'facturada', costo_total = :total WHERE id = :orden_id");
            $stmtEstado->bindParam(':total', $total);
            $stmtEstado->bindParam(':orden_id', $ordenId, PDO::PARAM_INT);
            $stmtEstado->execute();

            $db->commit();
            adminRedirectTo('facturacion.php', 'Orden facturada y registrada en administración.');
        }
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $mensaje = $e->getMessage();
        $tipo_mensaje = 'error';
    }
}

$stmtStats = $db->query("SELECT
    (SELECT COUNT(*) FROM admin_facturas) AS facturas,
    (SELECT COALESCE(SUM(total), 0) FROM admin_facturas) AS total_facturado
");
$stats = $stmtStats->fetch(PDO::FETCH_ASSOC);

$ivas = $db->query('SELECT * FROM admin_iva ORDER BY porcentaje')->fetchAll(PDO::FETCH_ASSOC);
$impuestos = $db->query('SELECT * FROM admin_impuestos ORDER BY nombre')->fetchAll(PDO::FETCH_ASSOC);
$tiposFactura = $db->query('SELECT * FROM admin_tipos_factura ORDER BY codigo')->fetchAll(PDO::FETCH_ASSOC);

$ordenesFacturables = [];
if ($ordenesDisponibles) {
    $ordenesFacturables = $db->query("SELECT o.id, o.numero_orden, c.nombre AS cliente_nombre,
                                             COALESCE((SELECT SUM(cantidad * precio_unitario) FROM orden_repuestos WHERE orden_id = o.id), 0) +
                                             COALESCE((SELECT SUM(tiempo_horas * costo_hora) FROM orden_tareas WHERE orden_id = o.id), 0) AS total_estimado
                                      FROM ordenes_reparacion o
                                      INNER JOIN turnos t ON t.id = o.turno_id
                                      INNER JOIN clientes c ON c.id = t.cliente_id
                                      LEFT JOIN admin_facturas af ON af.orden_id = o.id
                                      WHERE o.estado IN ('completada', 'facturada') AND af.id IS NULL
                                      ORDER BY o.fecha_creacion DESC, o.id DESC")->fetchAll(PDO::FETCH_ASSOC);
}

$ultimasFacturas = $db->query("SELECT f.id, f.orden_id, f.numero_factura, f.fecha_emision, tf.nombre AS tipo_factura, f.total
                               FROM admin_facturas f
                               INNER JOIN admin_tipos_factura tf ON tf.id = f.tipo_factura_id
                               ORDER BY f.id DESC LIMIT 8")->fetchAll(PDO::FETCH_ASSOC);

require_once 'includes/header.php';
?>
<link rel="stylesheet" href="css/styleess.css">

<div class="container">
    <h2>Facturación</h2>

    <?php if ($mensaje !== ''): ?>
        <div class="alert <?php echo $tipo_mensaje === 'error' ? 'error' : 'success'; ?>">
            <?php echo htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <div style="display:flex; align-items:stretch; gap: 15px; margin-bottom: 20px; flex-wrap: wrap;">
        <div class="summary-card total" style="flex: 1; min-width: 280px; margin-bottom: 0; padding: 18px 22px; display: flex; align-items: center; justify-content: space-between;">
            <div>
                <h3 style="margin: 0 0 4px 0;">Facturas</h3>
                <h4 style="margin: 0;">Cantidad emitida</h4>
            </div>
            <div>
                <p style="font-size: 28px; font-weight: bold; margin: 0;"><?php echo (int) ($stats['facturas'] ?? 0); ?></p>
            </div>
        </div>
        <div class="summary-card total" style="flex: 1; min-width: 280px; margin-bottom: 0; padding: 18px 22px; display: flex; align-items: center; justify-content: space-between;">
            <div>
                <h3 style="margin: 0 0 4px 0;">Resumen comercial</h3>
                <h4 style="margin: 0;">Total facturado</h4>
            </div>
            <div>
                <p style="font-size: 28px; font-weight: bold; color: #2d7a57; margin: 0;">$<?php echo number_format((float) ($stats['total_facturado'] ?? 0), 2); ?></p>
            </div>
        </div>
    </div>

    <div class="form-container">
        <h3>Facturación de órdenes</h3>
        <?php if (!$ordenesDisponibles): ?>
            <div class="alert warning">La base actual no expone las tablas completas de órdenes. La sección queda visible, pero la facturación se habilitará cuando estén disponibles.</div>
        <?php endif; ?>
        <form method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label for="orden_factura_id">Orden:</label>
                    <select id="orden_factura_id" name="orden_factura_id" <?php echo $ordenesDisponibles ? 'required' : 'disabled'; ?>>
                        <option value="">Seleccionar</option>
                        <?php foreach ($ordenesFacturables as $orden): ?>
                            <option value="<?php echo (int) $orden['id']; ?>"><?php echo htmlspecialchars($orden['numero_orden'] . ' - ' . $orden['cliente_nombre'] . ' - $' . number_format((float) $orden['total_estimado'], 2)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="tipo_factura_id">Tipo de factura:</label>
                    <select id="tipo_factura_id" name="tipo_factura_id" required>
                        <option value="">Seleccionar</option>
                        <?php foreach ($tiposFactura as $tipoFactura): ?>
                            <option value="<?php echo (int) $tipoFactura['id']; ?>"><?php echo htmlspecialchars($tipoFactura['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="numero_factura">Número factura:</label>
                    <input type="text" id="numero_factura" name="numero_factura" placeholder="Autogenerar si queda vacío">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="iva_factura_id">IVA:</label>
                    <select id="iva_factura_id" name="iva_factura_id">
                        <?php foreach ($ivas as $iva): ?>
                            <option value="<?php echo (int) $iva['id']; ?>"><?php echo htmlspecialchars($iva['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="impuesto_factura_id">Impuesto:</label>
                    <select id="impuesto_factura_id" name="impuesto_factura_id">
                        <option value="0">Sin impuesto</option>
                        <?php foreach ($impuestos as $impuesto): ?>
                            <option value="<?php echo (int) $impuesto['id']; ?>"><?php echo htmlspecialchars($impuesto['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="moneda_factura">Moneda:</label>
                    <select id="moneda_factura" name="moneda_factura">
                        <option value="ARS">ARS</option>
                        <option value="USD">USD</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label for="observaciones_factura">Observaciones:</label>
                <textarea id="observaciones_factura" name="observaciones_factura" rows="2"></textarea>
            </div>
            <button type="submit" name="facturar_orden" class="btn btn-primary" style="padding: 8px 14px; font-size: 13px;" <?php echo $ordenesDisponibles ? '' : 'disabled'; ?>>Facturar orden</button>
        </form>
    </div>

    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px;">
        <div class="form-container">
            <h3>Últimas facturas</h3>
            <table class="table">
                <thead><tr><th>Número</th><th>Tipo</th><th>Total</th><th>Acciones</th></tr></thead>
                <tbody>
                    <?php if (empty($ultimasFacturas)): ?>
                        <tr><td colspan="4">Sin facturas registradas.</td></tr>
                    <?php else: ?>
                        <?php foreach ($ultimasFacturas as $factura): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($factura['numero_factura']); ?></td>
                                <td><?php echo htmlspecialchars($factura['tipo_factura']); ?></td>
                                <td>$<?php echo number_format((float) $factura['total'], 2); ?></td>
                                <td>
                                    <?php if ((int) ($factura['id'] ?? 0) > 0): ?>
                                        <a href="generar_factura_pdf.php?factura_id=<?php echo (int) $factura['id']; ?>" class="btn btn-secondary" style="padding: 6px 10px; font-size: 12px;" target="_blank" rel="noopener noreferrer">Imprimir</a>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>