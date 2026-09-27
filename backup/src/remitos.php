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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crear_remito'])) {
    try {
        $clienteId = (int) ($_POST['cliente_remito_id'] ?? 0);
        $vehiculoId = (int) ($_POST['vehiculo_remito_id'] ?? 0);
        $ordenId = $ordenesDisponibles ? (int) ($_POST['orden_remito_id'] ?? 0) : 0;
        $descripcion = trim((string) ($_POST['descripcion_remito'] ?? ''));
        $concepto = trim((string) ($_POST['concepto_remito'] ?? ''));
        $cantidad = (float) ($_POST['cantidad_remito'] ?? 0);
        $observaciones = trim((string) ($_POST['observaciones_remito'] ?? ''));

        if ($clienteId <= 0 || $concepto === '' || $cantidad <= 0) {
            throw new RuntimeException('Complete cliente, concepto y cantidad del remito.');
        }

        $numeroRemito = nextAdminNumber($db, 'admin_remitos', 'numero_remito', 'REM');

        $db->beginTransaction();

        $stmtRemito = $db->prepare('INSERT INTO admin_remitos (numero_remito, fecha, cliente_id, vehiculo_id, orden_id, descripcion, observaciones)
                                    VALUES (:numero_remito, CURDATE(), :cliente_id, :vehiculo_id, :orden_id, :descripcion, :observaciones)');
        $stmtRemito->bindParam(':numero_remito', $numeroRemito);
        $stmtRemito->bindParam(':cliente_id', $clienteId, PDO::PARAM_INT);
        if ($vehiculoId > 0) {
            $stmtRemito->bindParam(':vehiculo_id', $vehiculoId, PDO::PARAM_INT);
        } else {
            $vehiculoNull = null;
            $stmtRemito->bindParam(':vehiculo_id', $vehiculoNull, PDO::PARAM_NULL);
        }
        if ($ordenId > 0) {
            $stmtRemito->bindParam(':orden_id', $ordenId, PDO::PARAM_INT);
        } else {
            $ordenNull = null;
            $stmtRemito->bindParam(':orden_id', $ordenNull, PDO::PARAM_NULL);
        }
        $stmtRemito->bindParam(':descripcion', $descripcion);
        $stmtRemito->bindParam(':observaciones', $observaciones);
        $stmtRemito->execute();

        $remitoId = (int) $db->lastInsertId();
        $stmtItem = $db->prepare('INSERT INTO admin_remito_items (remito_id, concepto, cantidad, observaciones)
                                  VALUES (:remito_id, :concepto, :cantidad, :observaciones)');
        $stmtItem->bindParam(':remito_id', $remitoId, PDO::PARAM_INT);
        $stmtItem->bindParam(':concepto', $concepto);
        $stmtItem->bindParam(':cantidad', $cantidad);
        $stmtItem->bindParam(':observaciones', $observaciones);
        $stmtItem->execute();

        $db->commit();
        adminRedirectTo('remitos.php', 'Remito generado correctamente.');
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $mensaje = $e->getMessage();
        $tipo_mensaje = 'error';
    }
}

$stats = $db->query("SELECT
    COUNT(*) AS cantidad,
    COALESCE(SUM(CASE WHEN estado = 'entregado' THEN 1 ELSE 0 END), 0) AS entregados,
    COALESCE(SUM(CASE WHEN estado = 'emitido' THEN 1 ELSE 0 END), 0) AS emitidos
    FROM admin_remitos")->fetch(PDO::FETCH_ASSOC);

$clientes = $db->query('SELECT id, nombre FROM clientes ORDER BY nombre')->fetchAll(PDO::FETCH_ASSOC);
$vehiculos = $db->query("SELECT v.id, CONCAT(c.nombre, ' - ', v.marca, ' ', v.modelo, ' (', v.matricula, ')') AS etiqueta
                         FROM vehiculos v
                         INNER JOIN clientes c ON c.id = v.cliente_id
                         ORDER BY c.nombre, v.marca, v.modelo")->fetchAll(PDO::FETCH_ASSOC);

$ordenesFacturables = [];
if ($ordenesDisponibles) {
    $ordenesFacturables = $db->query("SELECT o.id, o.numero_orden, c.nombre AS cliente_nombre
                                      FROM ordenes_reparacion o
                                      INNER JOIN turnos t ON t.id = o.turno_id
                                      INNER JOIN clientes c ON c.id = t.cliente_id
                                      ORDER BY o.fecha_creacion DESC, o.id DESC")->fetchAll(PDO::FETCH_ASSOC);
}

$ultimosRemitos = $db->query("SELECT r.numero_remito, r.fecha, c.nombre AS cliente, r.estado
                              FROM admin_remitos r
                              INNER JOIN clientes c ON c.id = r.cliente_id
                              ORDER BY r.id DESC LIMIT 15")->fetchAll(PDO::FETCH_ASSOC);

require_once 'includes/header.php';
?>
<link rel="stylesheet" href="css/styleess.css">

<div class="container">
    <h2>Remitos</h2>
    <p style="margin-bottom: 20px; color: #5a6470;">Emisión y seguimiento de remitos con el mismo estilo operativo del sistema.</p>

    <?php if ($mensaje !== ''): ?>
        <div class="alert <?php echo $tipo_mensaje === 'error' ? 'error' : 'success'; ?>">
            <?php echo htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 15px; margin-bottom: 25px;">
        <div class="summary-card total">
            <h4>Cantidad</h4>
            <p><?php echo (int) ($stats['cantidad'] ?? 0); ?></p>
        </div>
        <div class="summary-card total">
            <h4>Emitidos</h4>
            <p><?php echo (int) ($stats['emitidos'] ?? 0); ?></p>
        </div>
        <div class="summary-card total">
            <h4>Entregados</h4>
            <p><?php echo (int) ($stats['entregados'] ?? 0); ?></p>
        </div>
    </div>

    <div class="form-container">
        <h3>Nuevo remito</h3>
        <form method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label for="cliente_remito_id">Cliente:</label>
                    <select id="cliente_remito_id" name="cliente_remito_id" required>
                        <option value="">Seleccionar</option>
                        <?php foreach ($clientes as $cliente): ?>
                            <option value="<?php echo (int) $cliente['id']; ?>"><?php echo htmlspecialchars($cliente['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="vehiculo_remito_id">Vehículo:</label>
                    <select id="vehiculo_remito_id" name="vehiculo_remito_id">
                        <option value="0">Sin vehículo</option>
                        <?php foreach ($vehiculos as $vehiculo): ?>
                            <option value="<?php echo (int) $vehiculo['id']; ?>"><?php echo htmlspecialchars($vehiculo['etiqueta']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="orden_remito_id">Orden asociada:</label>
                    <select id="orden_remito_id" name="orden_remito_id" <?php echo $ordenesDisponibles ? '' : 'disabled'; ?>>
                        <option value="0"><?php echo $ordenesDisponibles ? 'Sin orden' : 'Tablas de orden no disponibles'; ?></option>
                        <?php foreach ($ordenesFacturables as $orden): ?>
                            <option value="<?php echo (int) $orden['id']; ?>"><?php echo htmlspecialchars($orden['numero_orden'] . ' - ' . $orden['cliente_nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="concepto_remito">Concepto:</label>
                    <input type="text" id="concepto_remito" name="concepto_remito" required>
                </div>
                <div class="form-group">
                    <label for="cantidad_remito">Cantidad:</label>
                    <input type="number" step="0.01" min="0.01" id="cantidad_remito" name="cantidad_remito" required>
                </div>
            </div>
            <div class="form-group">
                <label for="descripcion_remito">Descripción:</label>
                <textarea id="descripcion_remito" name="descripcion_remito" rows="2"></textarea>
            </div>
            <div class="form-group">
                <label for="observaciones_remito">Observaciones:</label>
                <textarea id="observaciones_remito" name="observaciones_remito" rows="2"></textarea>
            </div>
            <button type="submit" name="crear_remito" class="btn btn-primary">Crear remito</button>
        </form>
    </div>

    <div class="form-container">
        <h3>Últimos remitos</h3>
        <table class="table">
            <thead>
                <tr>
                    <th>Número</th>
                    <th>Fecha</th>
                    <th>Cliente</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($ultimosRemitos)): ?>
                    <tr><td colspan="4">Sin remitos registrados.</td></tr>
                <?php else: ?>
                    <?php foreach ($ultimosRemitos as $remito): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($remito['numero_remito']); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($remito['fecha'])); ?></td>
                            <td><?php echo htmlspecialchars($remito['cliente']); ?></td>
                            <td><?php echo htmlspecialchars(ucfirst($remito['estado'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>