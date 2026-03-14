<?php
require_once 'includes/auth.php';
require_once 'includes/database.php';

requireAuth();

$clienteId = filter_input(INPUT_GET, 'cliente_id', FILTER_VALIDATE_INT);
$ajax = isset($_GET['ajax']) && $_GET['ajax'] === '1';

if (!$clienteId) {
    if ($ajax) {
        header('Content-Type: text/html; charset=UTF-8');
        echo '<p>ID de cliente invalido.</p>';
        exit;
    }
    header('Location: clientes.php');
    exit;
}

$database = new Database();
$db = $database->getConnection();

$queryCliente = 'SELECT id, nombre, id_ant FROM clientes WHERE id = :id LIMIT 1';
$stmtCliente = $db->prepare($queryCliente);
$stmtCliente->bindParam(':id', $clienteId, PDO::PARAM_INT);
$stmtCliente->execute();
$cliente = $stmtCliente->fetch(PDO::FETCH_ASSOC);

if (!$cliente) {
    if ($ajax) {
        header('Content-Type: text/html; charset=UTF-8');
        echo '<p>Cliente no encontrado.</p>';
        exit;
    }
    header('Location: clientes.php');
    exit;
}

$queryHistorico = 'SELECT orden, codigo_equipo, fecha_ingreso, fecha_egreso, observaciones, id_cliente_access
                   FROM historico
                   WHERE cliente_id = :cliente_id
                      OR (id_cliente_access IS NOT NULL AND id_cliente_access = :id_ant)
                   ORDER BY fecha_ingreso DESC, orden DESC';
$stmtHistorico = $db->prepare($queryHistorico);
$stmtHistorico->bindValue(':cliente_id', $clienteId, PDO::PARAM_INT);
$stmtHistorico->bindValue(':id_ant', (string)($cliente['id_ant'] ?? ''));
$stmtHistorico->execute();
$items = $stmtHistorico->fetchAll(PDO::FETCH_ASSOC);

$insumos = [];
$insumosError = null;
try {
    $queryInsumos = 'SELECT orden, codigo_equipo, pieza_insumo, precio_estimado, abonado
                     FROM historico_insumos
                     WHERE cliente_id = :cliente_id
                        OR (id_cliente_access IS NOT NULL AND id_cliente_access = :id_ant)
                     ORDER BY orden DESC, pieza_insumo ASC';
    $stmtInsumos = $db->prepare($queryInsumos);
    $stmtInsumos->bindValue(':cliente_id', $clienteId, PDO::PARAM_INT);
    $stmtInsumos->bindValue(':id_ant', (string)($cliente['id_ant'] ?? ''));
    $stmtInsumos->execute();
    $insumos = $stmtInsumos->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    // Si la tabla temporal no existe en algun entorno, no interrumpe el modal.
    $insumosError = 'La seccion temporal de repuestos e insumos aun no esta disponible en este entorno.';
}

$insumosPorOrden = [];
foreach ($insumos as $insumo) {
    $ordenInsumo = (int)($insumo['orden'] ?? 0);
    if ($ordenInsumo <= 0) {
        continue;
    }
    if (!isset($insumosPorOrden[$ordenInsumo])) {
        $insumosPorOrden[$ordenInsumo] = [];
    }
    $insumosPorOrden[$ordenInsumo][] = $insumo;
}

if (!$ajax) {
    header('Location: clientes_m.php?id=' . urlencode((string)$clienteId));
    exit;
}

header('Content-Type: text/html; charset=UTF-8');
?>
<div class="historico-head">
    <h3>Histórico de Reparaciones</h3>
    <p><strong>Cliente:</strong> <?php echo htmlspecialchars($cliente['nombre'], ENT_QUOTES, 'UTF-8'); ?></p>
</div>

<?php if (empty($items)): ?>
    <p>No hay registros históricos para este cliente.</p>
<?php else: ?>
    <div class="table-wrapper-scroll" style="height: 520px; padding-right: 4px;">
        <?php foreach ($items as $item): ?>
            <?php $orden = (int)$item['orden']; ?>
            <div style="border: 1px solid #d7e2f0; border-radius: 10px; overflow: hidden; margin-bottom: 12px; box-shadow: 0 1px 2px rgba(0,0,0,0.04);">
                <div style="background: rgba(28, 102, 188, 0.18); border-bottom: 1px solid #b8cfea; padding: 10px 12px;">
                    <div style="display: flex; flex-wrap: wrap; gap: 10px 16px; font-size: 13px; color: #0f2f55;">
                        <span><strong>Orden:</strong> <?php echo $orden; ?></span>
                        <span><strong>Matrícula:</strong> <?php echo htmlspecialchars((string)$item['codigo_equipo'], ENT_QUOTES, 'UTF-8'); ?></span>
                        <span><strong>Ingreso:</strong> <?php echo !empty($item['fecha_ingreso']) ? date('d/m/Y', strtotime((string)$item['fecha_ingreso'])) : '-'; ?></span>
                        <span><strong>Egreso:</strong> <?php echo !empty($item['fecha_egreso']) ? date('d/m/Y', strtotime((string)$item['fecha_egreso'])) : '-'; ?></span>
                    </div>
                    <?php if (!empty($item['observaciones'])): ?>
                        <div style="margin-top: 8px; font-size: 13px; color: #0f2f55;">
                            <strong>Observaciones:</strong>
                            <?php echo nl2br(htmlspecialchars((string)$item['observaciones'], ENT_QUOTES, 'UTF-8')); ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div style="background: #ffffff; padding: 0;">
                    <?php if ($insumosError !== null): ?>
                        <div style="padding: 10px 12px; font-size: 13px; color: #5f6b7a;">
                            <?php echo htmlspecialchars($insumosError, ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                    <?php elseif (empty($insumosPorOrden[$orden])): ?>
                        <div style="padding: 10px 12px; font-size: 13px; color: #5f6b7a;">
                            Sin insumos cargados para esta orden.
                        </div>
                    <?php else: ?>
                        <?php foreach ($insumosPorOrden[$orden] as $idx => $insumo): ?>
                            <div style="padding: 10px 12px; font-size: 13px; color: #1d2833; <?php echo $idx > 0 ? 'border-top: 1px solid #eef2f7;' : ''; ?>">
                                <?php echo htmlspecialchars((string)$insumo['pieza_insumo'], ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
