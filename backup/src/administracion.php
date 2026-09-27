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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['guardar_configuracion'])) {
            $valorDolar = trim((string) ($_POST['valor_dolar'] ?? '1.00'));
            $razonSocial = trim((string) ($_POST['empresa_razon_social'] ?? 'Sur Bateria'));
            $empresaCuit = trim((string) ($_POST['empresa_cuit'] ?? ''));

            setAdminConfig($db, 'valor_dolar', $valorDolar, 'Cotizacion de referencia del dolar');
            setAdminConfig($db, 'empresa_razon_social', $razonSocial, 'Razon social visible en documentos');
            setAdminConfig($db, 'empresa_cuit', $empresaCuit, 'Identificacion fiscal de la empresa');
            adminRedirectTo('administracion.php', 'Configuraciones administrativas actualizadas.');
        }

        if (isset($_POST['agregar_iva'])) {
            $nombre = trim((string) ($_POST['nombre_iva'] ?? ''));
            $porcentaje = (float) ($_POST['porcentaje_iva'] ?? 0);

            if ($nombre === '') {
                throw new RuntimeException('Debe indicar un nombre para la alicuota de IVA.');
            }

            $stmt = $db->prepare('INSERT INTO admin_iva (nombre, porcentaje, activo) VALUES (:nombre, :porcentaje, 1)');
            $stmt->bindParam(':nombre', $nombre);
            $stmt->bindParam(':porcentaje', $porcentaje);
            $stmt->execute();
            adminRedirectTo('administracion.php', 'Alicuota de IVA registrada.');
        }

        if (isset($_POST['agregar_impuesto'])) {
            $nombre = trim((string) ($_POST['nombre_impuesto'] ?? ''));
            $tipo = ($_POST['tipo_impuesto'] ?? 'porcentaje') === 'fijo' ? 'fijo' : 'porcentaje';
            $valor = (float) ($_POST['valor_impuesto'] ?? 0);

            if ($nombre === '') {
                throw new RuntimeException('Debe indicar un nombre para el impuesto.');
            }

            $stmt = $db->prepare('INSERT INTO admin_impuestos (nombre, tipo, valor, activo) VALUES (:nombre, :tipo, :valor, 1)');
            $stmt->bindParam(':nombre', $nombre);
            $stmt->bindParam(':tipo', $tipo);
            $stmt->bindParam(':valor', $valor);
            $stmt->execute();
            adminRedirectTo('administracion.php', 'Impuesto registrado.');
        }

        if (isset($_POST['agregar_tipo_factura'])) {
            $codigo = strtoupper(trim((string) ($_POST['codigo_factura'] ?? '')));
            $nombre = trim((string) ($_POST['nombre_factura'] ?? ''));
            $letra = strtoupper(trim((string) ($_POST['letra_factura'] ?? '')));

            if ($codigo === '' || $nombre === '' || $letra === '') {
                throw new RuntimeException('Complete codigo, nombre y letra del tipo de factura.');
            }

            $stmt = $db->prepare('INSERT INTO admin_tipos_factura (codigo, nombre, letra, activo) VALUES (:codigo, :nombre, :letra, 1)');
            $stmt->bindParam(':codigo', $codigo);
            $stmt->bindParam(':nombre', $nombre);
            $stmt->bindParam(':letra', $letra);
            $stmt->execute();
            adminRedirectTo('administracion.php', 'Tipo de factura registrado.');
        }

        if (isset($_POST['agregar_deposito'])) {
            $nombre = trim((string) ($_POST['nombre_deposito'] ?? ''));
            $ubicacion = trim((string) ($_POST['ubicacion_deposito'] ?? ''));
            $descripcion = trim((string) ($_POST['descripcion_deposito'] ?? ''));

            if ($nombre === '') {
                throw new RuntimeException('Debe indicar el nombre del deposito.');
            }

            $stmt = $db->prepare('INSERT INTO admin_depositos (nombre, ubicacion, descripcion, activo) VALUES (:nombre, :ubicacion, :descripcion, 1)');
            $stmt->bindParam(':nombre', $nombre);
            $stmt->bindParam(':ubicacion', $ubicacion);
            $stmt->bindParam(':descripcion', $descripcion);
            $stmt->execute();
            adminRedirectTo('administracion.php', 'Deposito registrado.');
        }

        if (isset($_POST['registrar_compra'])) {
            $fecha = $_POST['fecha_compra'] ?? date('Y-m-d');
            $proveedor = trim((string) ($_POST['proveedor'] ?? ''));
            $depositoId = (int) ($_POST['deposito_id'] ?? 0);
            $repuestoId = (int) ($_POST['repuesto_id'] ?? 0);
            $cantidad = (int) ($_POST['cantidad_compra'] ?? 0);
            $costoUnitario = (float) ($_POST['costo_unitario'] ?? 0);
            $ivaId = (int) ($_POST['iva_compra_id'] ?? 0);
            $moneda = strtoupper(trim((string) ($_POST['moneda_compra'] ?? 'ARS')));
            $observaciones = trim((string) ($_POST['observaciones_compra'] ?? ''));

            if ($proveedor === '' || $depositoId <= 0 || $repuestoId <= 0 || $cantidad <= 0 || $costoUnitario < 0) {
                throw new RuntimeException('Complete proveedor, deposito, repuesto, cantidad y costo de la compra.');
            }

            $numeroCompra = nextAdminNumber($db, 'admin_compras', 'numero_compra', 'CMP');
            $subtotal = $cantidad * $costoUnitario;
            $ivaPorcentaje = getPorcentajeById($db, 'admin_iva', $ivaId);
            $impuestosTotal = $subtotal * ($ivaPorcentaje / 100);
            $total = $subtotal + $impuestosTotal;
            $cotizacionDolar = (float) getAdminConfig($db, 'valor_dolar', '1.00');

            $db->beginTransaction();

            $stmtCompra = $db->prepare('INSERT INTO admin_compras (numero_compra, fecha, proveedor, deposito_id, moneda, cotizacion_dolar, subtotal, impuestos_total, total, observaciones)
                                        VALUES (:numero_compra, :fecha, :proveedor, :deposito_id, :moneda, :cotizacion_dolar, :subtotal, :impuestos_total, :total, :observaciones)');
            $stmtCompra->bindParam(':numero_compra', $numeroCompra);
            $stmtCompra->bindParam(':fecha', $fecha);
            $stmtCompra->bindParam(':proveedor', $proveedor);
            $stmtCompra->bindParam(':deposito_id', $depositoId, PDO::PARAM_INT);
            $stmtCompra->bindParam(':moneda', $moneda);
            $stmtCompra->bindParam(':cotizacion_dolar', $cotizacionDolar);
            $stmtCompra->bindParam(':subtotal', $subtotal);
            $stmtCompra->bindParam(':impuestos_total', $impuestosTotal);
            $stmtCompra->bindParam(':total', $total);
            $stmtCompra->bindParam(':observaciones', $observaciones);
            $stmtCompra->execute();

            $compraId = (int) $db->lastInsertId();
            $stmtItem = $db->prepare('INSERT INTO admin_compra_items (compra_id, repuesto_id, cantidad, costo_unitario, subtotal)
                                      VALUES (:compra_id, :repuesto_id, :cantidad, :costo_unitario, :subtotal)');
            $stmtItem->bindParam(':compra_id', $compraId, PDO::PARAM_INT);
            $stmtItem->bindParam(':repuesto_id', $repuestoId, PDO::PARAM_INT);
            $stmtItem->bindParam(':cantidad', $cantidad, PDO::PARAM_INT);
            $stmtItem->bindParam(':costo_unitario', $costoUnitario);
            $stmtItem->bindParam(':subtotal', $subtotal);
            $stmtItem->execute();

            $stmtStock = $db->prepare('UPDATE repuestos SET stock = stock + :cantidad WHERE id = :repuesto_id');
            $stmtStock->bindParam(':cantidad', $cantidad, PDO::PARAM_INT);
            $stmtStock->bindParam(':repuesto_id', $repuestoId, PDO::PARAM_INT);
            $stmtStock->execute();

            $stmtDeposito = $db->prepare('INSERT INTO repuestos_stock_deposito (repuesto_id, deposito_id, stock)
                                          VALUES (:repuesto_id, :deposito_id, :cantidad)
                                          ON DUPLICATE KEY UPDATE stock = stock + VALUES(stock)');
            $stmtDeposito->bindParam(':repuesto_id', $repuestoId, PDO::PARAM_INT);
            $stmtDeposito->bindParam(':deposito_id', $depositoId, PDO::PARAM_INT);
            $stmtDeposito->bindParam(':cantidad', $cantidad, PDO::PARAM_INT);
            $stmtDeposito->execute();

            $db->commit();
            adminRedirectTo('administracion.php', 'Compra registrada y stock actualizado.');
        }
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $mensaje = $e->getMessage();
        $tipo_mensaje = 'error';
    }
}

$valorDolar = getAdminConfig($db, 'valor_dolar', '1.00');
$empresaRazonSocial = getAdminConfig($db, 'empresa_razon_social', 'Sur Bateria');
$empresaCuit = getAdminConfig($db, 'empresa_cuit', '');

$stmtStats = $db->query("SELECT
    (SELECT COUNT(*) FROM admin_compras) AS compras,
    (SELECT COUNT(*) FROM admin_depositos WHERE activo = 1) AS depositos,
    (SELECT COUNT(*) FROM admin_iva WHERE activo = 1) AS ivas,
    (SELECT COUNT(*) FROM admin_impuestos WHERE activo = 1) AS impuestos,
    (SELECT COUNT(*) FROM admin_tipos_factura WHERE activo = 1) AS tipos_factura,
    (SELECT COALESCE(SUM(total), 0) FROM admin_compras) AS total_compras,
    (SELECT COALESCE(SUM(stock), 0) FROM repuestos) AS stock_total
");
$stats = $stmtStats->fetch(PDO::FETCH_ASSOC);

$ivas = $db->query('SELECT * FROM admin_iva ORDER BY porcentaje')->fetchAll(PDO::FETCH_ASSOC);
$impuestos = $db->query('SELECT * FROM admin_impuestos ORDER BY nombre')->fetchAll(PDO::FETCH_ASSOC);
$tiposFactura = $db->query('SELECT * FROM admin_tipos_factura ORDER BY codigo')->fetchAll(PDO::FETCH_ASSOC);
$depositos = $db->query('SELECT * FROM admin_depositos ORDER BY nombre')->fetchAll(PDO::FETCH_ASSOC);
$repuestos = $db->query('SELECT id, nombre, stock, precio FROM repuestos ORDER BY nombre')->fetchAll(PDO::FETCH_ASSOC);

$ultimasCompras = $db->query("SELECT c.numero_compra, c.fecha, c.proveedor, d.nombre AS deposito, c.total
                              FROM admin_compras c
                              INNER JOIN admin_depositos d ON d.id = c.deposito_id
                              ORDER BY c.id DESC LIMIT 8")->fetchAll(PDO::FETCH_ASSOC);

require_once 'includes/header.php';
?>
<link rel="stylesheet" href="css/styleess.css">

<div class="container">
    <h2>Administración</h2>
    <p style="margin-bottom: 20px; color: #5a6470;">Parámetros generales del ERP, impuestos, tipos de comprobante, depósitos e ingresos de compras.</p>

    <?php if ($mensaje !== ''): ?>
        <div class="alert <?php echo $tipo_mensaje === 'error' ? 'error' : 'success'; ?>">
            <?php echo htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 15px; margin-bottom: 25px;">
        <div class="summary-card total">
            <h4>Compras</h4>
            <p><?php echo (int) ($stats['compras'] ?? 0); ?></p>
        </div>
        <div class="summary-card total">
            <h4>Depósitos</h4>
            <p><?php echo (int) ($stats['depositos'] ?? 0); ?></p>
        </div>
        <div class="summary-card total">
            <h4>IVA</h4>
            <p><?php echo (int) ($stats['ivas'] ?? 0); ?></p>
        </div>
        <div class="summary-card total">
            <h4>Impuestos</h4>
            <p><?php echo (int) ($stats['impuestos'] ?? 0); ?></p>
        </div>
        <div class="summary-card total">
            <h4>Tipos factura</h4>
            <p><?php echo (int) ($stats['tipos_factura'] ?? 0); ?></p>
        </div>
    </div>

    <div class="inventory-summary" style="margin-bottom: 25px;">
        <h3>Cuadro de valores administrativos</h3>
        <div>
            <div>
                <h4>Valor dólar</h4>
                <p style="font-size: 28px; font-weight: bold; color: #1f5fbf;">$<?php echo number_format((float) $valorDolar, 2); ?></p>
            </div>
            <div>
                <h4>Total compras</h4>
                <p style="font-size: 28px; font-weight: bold; color: #2d7a57;">$<?php echo number_format((float) ($stats['total_compras'] ?? 0), 2); ?></p>
            </div>
            <div>
                <h4>Stock total</h4>
                <p style="font-size: 28px; font-weight: bold; color: #8a3b45;"><?php echo number_format((float) ($stats['stock_total'] ?? 0), 0); ?></p>
            </div>
        </div>
    </div>

    <div class="form-container">
        <h3>Parámetros generales</h3>
        <form method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label for="valor_dolar">Valor dólar:</label>
                    <input type="number" step="0.01" min="0" id="valor_dolar" name="valor_dolar" value="<?php echo htmlspecialchars($valorDolar, ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>
                <div class="form-group">
                    <label for="empresa_razon_social">Razón social:</label>
                    <input type="text" id="empresa_razon_social" name="empresa_razon_social" value="<?php echo htmlspecialchars($empresaRazonSocial, ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="form-group">
                    <label for="empresa_cuit">CUIT empresa:</label>
                    <input type="text" id="empresa_cuit" name="empresa_cuit" value="<?php echo htmlspecialchars($empresaCuit, ENT_QUOTES, 'UTF-8'); ?>">
                </div>
            </div>
            <button type="submit" name="guardar_configuracion" class="btn btn-primary">Guardar parámetros</button>
        </form>
    </div>

    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 20px; margin-bottom: 20px;">
        <div class="form-container">
            <h3>IVA</h3>
            <form method="POST" style="margin-bottom: 15px;">
                <div class="form-row">
                    <div class="form-group">
                        <label for="nombre_iva">Nombre:</label>
                        <input type="text" id="nombre_iva" name="nombre_iva" required>
                    </div>
                    <div class="form-group">
                        <label for="porcentaje_iva">Porcentaje:</label>
                        <input type="number" step="0.01" min="0" id="porcentaje_iva" name="porcentaje_iva" required>
                    </div>
                </div>
                <button type="submit" name="agregar_iva" class="btn btn-primary">Agregar IVA</button>
            </form>
            <table class="table">
                <thead><tr><th>Nombre</th><th>%</th></tr></thead>
                <tbody>
                    <?php foreach ($ivas as $iva): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($iva['nombre']); ?></td>
                            <td><?php echo number_format((float) $iva['porcentaje'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="form-container">
            <h3>Impuestos</h3>
            <form method="POST" style="margin-bottom: 15px;">
                <div class="form-row">
                    <div class="form-group">
                        <label for="nombre_impuesto">Nombre:</label>
                        <input type="text" id="nombre_impuesto" name="nombre_impuesto" required>
                    </div>
                    <div class="form-group">
                        <label for="tipo_impuesto">Tipo:</label>
                        <select id="tipo_impuesto" name="tipo_impuesto">
                            <option value="porcentaje">Porcentaje</option>
                            <option value="fijo">Importe fijo</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="valor_impuesto">Valor:</label>
                        <input type="number" step="0.01" min="0" id="valor_impuesto" name="valor_impuesto" required>
                    </div>
                </div>
                <button type="submit" name="agregar_impuesto" class="btn btn-primary">Agregar impuesto</button>
            </form>
            <table class="table">
                <thead><tr><th>Nombre</th><th>Tipo</th><th>Valor</th></tr></thead>
                <tbody>
                    <?php foreach ($impuestos as $impuesto): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($impuesto['nombre']); ?></td>
                            <td><?php echo htmlspecialchars(ucfirst($impuesto['tipo'])); ?></td>
                            <td><?php echo number_format((float) $impuesto['valor'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 20px; margin-bottom: 20px;">
        <div class="form-container">
            <h3>Tipos de factura</h3>
            <form method="POST" style="margin-bottom: 15px;">
                <div class="form-row">
                    <div class="form-group">
                        <label for="codigo_factura">Código:</label>
                        <input type="text" id="codigo_factura" name="codigo_factura" required>
                    </div>
                    <div class="form-group">
                        <label for="nombre_factura">Nombre:</label>
                        <input type="text" id="nombre_factura" name="nombre_factura" required>
                    </div>
                    <div class="form-group">
                        <label for="letra_factura">Letra:</label>
                        <input type="text" id="letra_factura" name="letra_factura" maxlength="5" required>
                    </div>
                </div>
                <button type="submit" name="agregar_tipo_factura" class="btn btn-primary">Agregar tipo</button>
            </form>
            <table class="table">
                <thead><tr><th>Código</th><th>Nombre</th><th>Letra</th></tr></thead>
                <tbody>
                    <?php foreach ($tiposFactura as $tipoFactura): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($tipoFactura['codigo']); ?></td>
                            <td><?php echo htmlspecialchars($tipoFactura['nombre']); ?></td>
                            <td><?php echo htmlspecialchars($tipoFactura['letra']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="form-container">
            <h3>Depósitos</h3>
            <form method="POST" style="margin-bottom: 15px;">
                <div class="form-row">
                    <div class="form-group">
                        <label for="nombre_deposito">Nombre:</label>
                        <input type="text" id="nombre_deposito" name="nombre_deposito" required>
                    </div>
                    <div class="form-group">
                        <label for="ubicacion_deposito">Ubicación:</label>
                        <input type="text" id="ubicacion_deposito" name="ubicacion_deposito">
                    </div>
                </div>
                <div class="form-group">
                    <label for="descripcion_deposito">Descripción:</label>
                    <textarea id="descripcion_deposito" name="descripcion_deposito" rows="2"></textarea>
                </div>
                <button type="submit" name="agregar_deposito" class="btn btn-primary">Agregar depósito</button>
            </form>
            <table class="table">
                <thead><tr><th>Nombre</th><th>Ubicación</th></tr></thead>
                <tbody>
                    <?php foreach ($depositos as $deposito): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($deposito['nombre']); ?></td>
                            <td><?php echo htmlspecialchars($deposito['ubicacion'] ?? ''); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="form-container">
        <h3>Ingreso de compras y actualización de stock</h3>
        <form method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label for="fecha_compra">Fecha:</label>
                    <input type="date" id="fecha_compra" name="fecha_compra" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="form-group">
                    <label for="proveedor">Proveedor:</label>
                    <input type="text" id="proveedor" name="proveedor" required>
                </div>
                <div class="form-group">
                    <label for="deposito_id">Depósito:</label>
                    <select id="deposito_id" name="deposito_id" required>
                        <option value="">Seleccionar</option>
                        <?php foreach ($depositos as $deposito): ?>
                            <option value="<?php echo (int) $deposito['id']; ?>"><?php echo htmlspecialchars($deposito['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="repuesto_id">Repuesto:</label>
                    <select id="repuesto_id" name="repuesto_id" required>
                        <option value="">Seleccionar</option>
                        <?php foreach ($repuestos as $repuesto): ?>
                            <option value="<?php echo (int) $repuesto['id']; ?>"><?php echo htmlspecialchars($repuesto['nombre']); ?> | stock actual <?php echo (int) $repuesto['stock']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="cantidad_compra">Cantidad:</label>
                    <input type="number" min="1" id="cantidad_compra" name="cantidad_compra" required>
                </div>
                <div class="form-group">
                    <label for="costo_unitario">Costo unitario:</label>
                    <input type="number" step="0.01" min="0" id="costo_unitario" name="costo_unitario" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="iva_compra_id">IVA:</label>
                    <select id="iva_compra_id" name="iva_compra_id">
                        <?php foreach ($ivas as $iva): ?>
                            <option value="<?php echo (int) $iva['id']; ?>"><?php echo htmlspecialchars($iva['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="moneda_compra">Moneda:</label>
                    <select id="moneda_compra" name="moneda_compra">
                        <option value="ARS">ARS</option>
                        <option value="USD">USD</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="observaciones_compra">Observaciones:</label>
                    <input type="text" id="observaciones_compra" name="observaciones_compra">
                </div>
            </div>
            <button type="submit" name="registrar_compra" class="btn btn-primary">Registrar compra</button>
        </form>
    </div>

    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px;">
        <div class="form-container">
            <h3>Últimas compras</h3>
            <table class="table">
                <thead><tr><th>Número</th><th>Proveedor</th><th>Total</th></tr></thead>
                <tbody>
                    <?php if (empty($ultimasCompras)): ?>
                        <tr><td colspan="3">Sin compras registradas.</td></tr>
                    <?php else: ?>
                        <?php foreach ($ultimasCompras as $compra): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($compra['numero_compra']); ?></td>
                                <td><?php echo htmlspecialchars($compra['proveedor']); ?></td>
                                <td>$<?php echo number_format((float) $compra['total'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>