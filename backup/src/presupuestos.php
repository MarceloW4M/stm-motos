<?php
require_once 'includes/auth.php';
require_once 'includes/database.php';
require_once 'includes/administracion_schema.php';

requireAuth();

$database = new Database();
$db = $database->getConnection();
ensureAdministracionSchema($db);

$mensaje = $_GET['mensaje'] ?? '';
$tipo_mensaje = $_GET['tipo'] ?? 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crear_presupuesto'])) {
    try {
        $clienteId = (int) ($_POST['cliente_id'] ?? 0);
        $vehiculoId = (int) ($_POST['vehiculo_id'] ?? 0);
        $descripcion = trim((string) ($_POST['descripcion_presupuesto'] ?? ''));
        $concepto = trim((string) ($_POST['concepto_presupuesto'] ?? ''));
        $cantidad = (float) ($_POST['cantidad_presupuesto'] ?? 0);
        $precioUnitario = (float) ($_POST['precio_presupuesto'] ?? 0);
        $ivaId = (int) ($_POST['iva_presupuesto_id'] ?? 0);
        $impuestoId = (int) ($_POST['impuesto_presupuesto_id'] ?? 0);
        $validezDias = (int) ($_POST['validez_dias'] ?? 15);
        $observaciones = trim((string) ($_POST['observaciones_presupuesto'] ?? ''));

        if ($clienteId <= 0 || $concepto === '' || $cantidad <= 0) {
            throw new RuntimeException('Complete cliente, concepto y cantidad del presupuesto.');
        }

        $numeroPresupuesto = nextAdminNumber($db, 'admin_presupuestos', 'numero_presupuesto', 'PRE');
        $subtotal = $cantidad * $precioUnitario;
        $ivaPorcentaje = getPorcentajeById($db, 'admin_iva', $ivaId);
        $impuestoData = getImpuestoData($db, $impuestoId);
        $impuestosTotal = $subtotal * ($ivaPorcentaje / 100);
        $impuestosTotal += $impuestoData['tipo'] === 'fijo'
            ? $impuestoData['valor']
            : $subtotal * ($impuestoData['valor'] / 100);
        $total = $subtotal + $impuestosTotal;

        $db->beginTransaction();

        $stmtPresupuesto = $db->prepare('INSERT INTO admin_presupuestos (numero_presupuesto, fecha, cliente_id, vehiculo_id, descripcion, subtotal, impuestos_total, total, validez_dias, observaciones)
                                         VALUES (:numero_presupuesto, CURDATE(), :cliente_id, :vehiculo_id, :descripcion, :subtotal, :impuestos_total, :total, :validez_dias, :observaciones)');
        $stmtPresupuesto->bindParam(':numero_presupuesto', $numeroPresupuesto);
        $stmtPresupuesto->bindParam(':cliente_id', $clienteId, PDO::PARAM_INT);
        if ($vehiculoId > 0) {
            $stmtPresupuesto->bindParam(':vehiculo_id', $vehiculoId, PDO::PARAM_INT);
        } else {
            $vehiculoNull = null;
            $stmtPresupuesto->bindParam(':vehiculo_id', $vehiculoNull, PDO::PARAM_NULL);
        }
        $stmtPresupuesto->bindParam(':descripcion', $descripcion);
        $stmtPresupuesto->bindParam(':subtotal', $subtotal);
        $stmtPresupuesto->bindParam(':impuestos_total', $impuestosTotal);
        $stmtPresupuesto->bindParam(':total', $total);
        $stmtPresupuesto->bindParam(':validez_dias', $validezDias, PDO::PARAM_INT);
        $stmtPresupuesto->bindParam(':observaciones', $observaciones);
        $stmtPresupuesto->execute();

        $presupuestoId = (int) $db->lastInsertId();
        $stmtItem = $db->prepare('INSERT INTO admin_presupuesto_items (presupuesto_id, concepto, cantidad, precio_unitario, subtotal)
                                  VALUES (:presupuesto_id, :concepto, :cantidad, :precio_unitario, :subtotal)');
        $stmtItem->bindParam(':presupuesto_id', $presupuestoId, PDO::PARAM_INT);
        $stmtItem->bindParam(':concepto', $concepto);
        $stmtItem->bindParam(':cantidad', $cantidad);
        $stmtItem->bindParam(':precio_unitario', $precioUnitario);
        $stmtItem->bindParam(':subtotal', $subtotal);
        $stmtItem->execute();

        $db->commit();
        adminRedirectTo('presupuestos.php', 'Presupuesto creado correctamente.');
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
    COALESCE(SUM(total), 0) AS total_presupuestado,
    COALESCE(SUM(CASE WHEN estado = 'aprobado' THEN total ELSE 0 END), 0) AS total_aprobado
    FROM admin_presupuestos")->fetch(PDO::FETCH_ASSOC);

$ivas = $db->query('SELECT * FROM admin_iva ORDER BY porcentaje')->fetchAll(PDO::FETCH_ASSOC);
$impuestos = $db->query('SELECT * FROM admin_impuestos ORDER BY nombre')->fetchAll(PDO::FETCH_ASSOC);
$clientes = $db->query('SELECT id, nombre FROM clientes ORDER BY nombre')->fetchAll(PDO::FETCH_ASSOC);
$vehiculos = $db->query("SELECT v.id, CONCAT(c.nombre, ' - ', v.marca, ' ', v.modelo, ' (', v.matricula, ')') AS etiqueta
                         FROM vehiculos v
                         INNER JOIN clientes c ON c.id = v.cliente_id
                         ORDER BY c.nombre, v.marca, v.modelo")->fetchAll(PDO::FETCH_ASSOC);
$ultimosPresupuestos = $db->query("SELECT p.numero_presupuesto, p.fecha, c.nombre AS cliente, p.total, p.estado
                                   FROM admin_presupuestos p
                                   INNER JOIN clientes c ON c.id = p.cliente_id
                                   ORDER BY p.id DESC LIMIT 15")->fetchAll(PDO::FETCH_ASSOC);

require_once 'includes/header.php';
?>
<link rel="stylesheet" href="css/styleess.css">

<div class="container">
    <h2>Presupuestos</h2>
    <p style="margin-bottom: 20px; color: #5a6470;">Alta y seguimiento de presupuestos comerciales con el mismo formato visual del sistema.</p>

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
            <h4>Total presupuestado</h4>
            <p>$<?php echo number_format((float) ($stats['total_presupuestado'] ?? 0), 2); ?></p>
        </div>
        <div class="summary-card total">
            <h4>Total aprobado</h4>
            <p>$<?php echo number_format((float) ($stats['total_aprobado'] ?? 0), 2); ?></p>
        </div>
    </div>

    <div class="form-container">
        <h3>Nuevo presupuesto</h3>
        <form method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label for="cliente_id">Cliente:</label>
                    <select id="cliente_id" name="cliente_id" required>
                        <option value="">Seleccionar</option>
                        <?php foreach ($clientes as $cliente): ?>
                            <option value="<?php echo (int) $cliente['id']; ?>"><?php echo htmlspecialchars($cliente['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="vehiculo_id">Vehículo:</label>
                    <select id="vehiculo_id" name="vehiculo_id">
                        <option value="0">Sin vehículo</option>
                        <?php foreach ($vehiculos as $vehiculo): ?>
                            <option value="<?php echo (int) $vehiculo['id']; ?>"><?php echo htmlspecialchars($vehiculo['etiqueta']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="concepto_presupuesto">Concepto:</label>
                    <input type="text" id="concepto_presupuesto" name="concepto_presupuesto" required>
                </div>
                <div class="form-group">
                    <label for="cantidad_presupuesto">Cantidad:</label>
                    <input type="number" step="0.01" min="0.01" id="cantidad_presupuesto" name="cantidad_presupuesto" required>
                </div>
                <div class="form-group">
                    <label for="precio_presupuesto">Precio unitario:</label>
                    <input type="number" step="0.01" min="0" id="precio_presupuesto" name="precio_presupuesto" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="iva_presupuesto_id">IVA:</label>
                    <select id="iva_presupuesto_id" name="iva_presupuesto_id">
                        <?php foreach ($ivas as $iva): ?>
                            <option value="<?php echo (int) $iva['id']; ?>"><?php echo htmlspecialchars($iva['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="impuesto_presupuesto_id">Impuesto:</label>
                    <select id="impuesto_presupuesto_id" name="impuesto_presupuesto_id">
                        <option value="0">Sin impuesto</option>
                        <?php foreach ($impuestos as $impuesto): ?>
                            <option value="<?php echo (int) $impuesto['id']; ?>"><?php echo htmlspecialchars($impuesto['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="validez_dias">Validez (días):</label>
                    <input type="number" min="1" id="validez_dias" name="validez_dias" value="15">
                </div>
            </div>
            <div class="form-group">
                <label for="descripcion_presupuesto">Descripción:</label>
                <textarea id="descripcion_presupuesto" name="descripcion_presupuesto" rows="2"></textarea>
            </div>
            <div class="form-group">
                <label for="observaciones_presupuesto">Observaciones:</label>
                <textarea id="observaciones_presupuesto" name="observaciones_presupuesto" rows="2"></textarea>
            </div>
            <button type="submit" name="crear_presupuesto" class="btn btn-primary">Crear presupuesto</button>
        </form>
    </div>

    <div class="form-container">
        <h3>Últimos presupuestos</h3>
        <table class="table">
            <thead>
                <tr>
                    <th>Número</th>
                    <th>Fecha</th>
                    <th>Cliente</th>
                    <th>Estado</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($ultimosPresupuestos)): ?>
                    <tr><td colspan="5">Sin presupuestos registrados.</td></tr>
                <?php else: ?>
                    <?php foreach ($ultimosPresupuestos as $presupuesto): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($presupuesto['numero_presupuesto']); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($presupuesto['fecha'])); ?></td>
                            <td><?php echo htmlspecialchars($presupuesto['cliente']); ?></td>
                            <td><?php echo htmlspecialchars(ucfirst($presupuesto['estado'])); ?></td>
                            <td>$<?php echo number_format((float) $presupuesto['total'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>