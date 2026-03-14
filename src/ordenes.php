<?php
require_once 'includes/auth.php';
require_once 'includes/database.php';
require_once 'includes/header.php';

// Verificar autenticación
requireAuth();

// enlace al CSS especializado
?>
<link rel="stylesheet" href="css/styleess.css">
<?php

$database = new Database();
$db = $database->getConnection();

$mensaje = '';

// Procesar creación de nueva orden
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crear_orden'])) {
    $turno_id = $_POST['turno_id'];
    $diagnostico = $_POST['diagnostico'];
    $tecnico_id = $_POST['tecnico_id'] ?? $_SESSION['user_id'];
    
    try {
        $numero_orden = '';
        $orden_id = null;
        $max_intentos = 6;

        for ($intento = 1; $intento <= $max_intentos; $intento++) {
            try {
                $db->beginTransaction();

                // Generar número de orden candidate para el año actual.
                $query_numero = "SELECT CONCAT('ORD-', YEAR(CURDATE()), '-', LPAD(COALESCE(MAX(CAST(SUBSTRING(numero_orden, 10) AS UNSIGNED)), 0) + 1, 4, '0')) as nuevo_numero
                                 FROM ordenes_reparacion
                                 WHERE YEAR(fecha_creacion) = YEAR(CURDATE())";
                $stmt_numero = $db->prepare($query_numero);
                $stmt_numero->execute();
                $numero_orden = $stmt_numero->fetch(PDO::FETCH_COLUMN);

                if (empty($numero_orden)) {
                    $numero_orden = 'ORD-' . date('Y') . '-0001';
                }

                // Crear la orden
                $query = "INSERT INTO ordenes_reparacion (turno_id, numero_orden, fecha_creacion, diagnostico, tecnico_id)
                          VALUES (:turno_id, :numero_orden, CURDATE(), :diagnostico, :tecnico_id)";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':turno_id', $turno_id);
                $stmt->bindParam(':numero_orden', $numero_orden);
                $stmt->bindParam(':diagnostico', $diagnostico);
                $stmt->bindParam(':tecnico_id', $tecnico_id);
                $stmt->execute();

                $orden_id = $db->lastInsertId();

                // Actualizar el turno con referencia a la orden
                $query_update = "UPDATE turnos SET orden_id = :orden_id WHERE id = :turno_id";
                $stmt_update = $db->prepare($query_update);
                $stmt_update->bindParam(':orden_id', $orden_id);
                $stmt_update->bindParam(':turno_id', $turno_id);
                $stmt_update->execute();

                $db->commit();
                break;
            } catch (PDOException $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }

                $duplicateNumero = isset($e->errorInfo[1]) && (int)$e->errorInfo[1] === 1062;
                if ($duplicateNumero && $intento < $max_intentos) {
                    usleep(120000);
                    continue;
                }

                throw $e;
            }
        }

        if ($orden_id === null) {
            throw new RuntimeException('No se pudo generar un número de orden único.');
        }

        $mensaje = "✅ Orden creada exitosamente: <strong>$numero_orden</strong>";

    } catch (Throwable $e) {
        $mensaje = "❌ Error al crear orden: " . $e->getMessage();
    }
}

// Procesar agregar repuesto a orden
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['agregar_repuesto'])) {
    $orden_id = $_POST['orden_id'];
    $repuesto_id = $_POST['repuesto_id'];
    $cantidad = $_POST['cantidad'];
    
    try {
        // Obtener precio del repuesto
        $query_precio = "SELECT precio FROM repuestos WHERE id = :repuesto_id";
        $stmt_precio = $db->prepare($query_precio);
        $stmt_precio->bindParam(':repuesto_id', $repuesto_id);
        $stmt_precio->execute();
        $precio = $stmt_precio->fetch(PDO::FETCH_COLUMN);
        
        if (!$precio) {
            throw new Exception("Repuesto no encontrado");
        }
        
        // Verificar stock disponible
        $query_stock = "SELECT stock FROM repuestos WHERE id = :repuesto_id";
        $stmt_stock = $db->prepare($query_stock);
        $stmt_stock->bindParam(':repuesto_id', $repuesto_id);
        $stmt_stock->execute();
        $stock_actual = $stmt_stock->fetch(PDO::FETCH_COLUMN);
        
        if ($stock_actual < $cantidad) {
            $mensaje = "❌ Stock insuficiente. Disponible: $stock_actual";
        } else {
            // Agregar repuesto a la orden
            $query = "INSERT INTO orden_repuestos (orden_id, repuesto_id, cantidad, precio_unitario) 
                      VALUES (:orden_id, :repuesto_id, :cantidad, :precio)";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':orden_id', $orden_id);
            $stmt->bindParam(':repuesto_id', $repuesto_id);
            $stmt->bindParam(':cantidad', $cantidad);
            $stmt->bindParam(':precio', $precio);
            $stmt->execute();
            
            // Actualizar stock
            $query_update = "UPDATE repuestos SET stock = stock - :cantidad WHERE id = :repuesto_id";
            $stmt_update = $db->prepare($query_update);
            $stmt_update->bindParam(':cantidad', $cantidad);
            $stmt_update->bindParam(':repuesto_id', $repuesto_id);
            $stmt_update->execute();
            
            $mensaje = "✅ Repuesto agregado a la orden";
        }
        
    } catch (Exception $e) {
        $mensaje = "❌ Error al agregar repuesto: " . $e->getMessage();
    }
}

// Procesar agregar tarea a orden
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['agregar_tarea'])) {
    $orden_id = $_POST['orden_id'];
    $descripcion = $_POST['descripcion_tarea'];
    $tiempo_horas = $_POST['tiempo_horas'];
    $costo_hora = $_POST['costo_hora'] ?? 50; // Precio por hora por defecto
    
    try {
        $query = "INSERT INTO orden_tareas (orden_id, descripcion, tiempo_horas, costo_hora) 
                  VALUES (:orden_id, :descripcion, :tiempo_horas, :costo_hora)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':orden_id', $orden_id);
        $stmt->bindParam(':descripcion', $descripcion);
        $stmt->bindParam(':tiempo_horas', $tiempo_horas);
        $stmt->bindParam(':costo_hora', $costo_hora);
        $stmt->execute();
        
        $mensaje = "✅ Tarea agregada a la orden";
        
    } catch (PDOException $e) {
        $mensaje = "❌ Error al agregar tarea: " . $e->getMessage();
    }
}

// Procesar finalización de orden
if (isset($_GET['finalizar_orden'])) {
    $orden_id = $_GET['finalizar_orden'];
    $trabajo_realizado = $_GET['trabajo_realizado'] ?? '';
    $observaciones = $_GET['observaciones'] ?? '';
    
    try {
        // Calcular costos totales
        $query_calcular = "
            SELECT 
                COALESCE(SUM(or2.subtotal), 0) as total_repuestos,
                COALESCE(SUM(ot.subtotal), 0) as total_mano_obra
            FROM ordenes_reparacion o
            LEFT JOIN orden_repuestos or2 ON o.id = or2.orden_id
            LEFT JOIN orden_tareas ot ON o.id = ot.orden_id
            WHERE o.id = :orden_id
        ";
        
        $stmt_calcular = $db->prepare($query_calcular);
        $stmt_calcular->bindParam(':orden_id', $orden_id);
        $stmt_calcular->execute();
        $totales = $stmt_calcular->fetch(PDO::FETCH_ASSOC);
        
        $costo_total = $totales['total_repuestos'] + $totales['total_mano_obra'];
        
        // Actualizar orden
        $query = "UPDATE ordenes_reparacion 
                  SET estado = 'completada', 
                      fecha_cierre = CURDATE(),
                      trabajo_realizado = :trabajo_realizado,
                      observaciones = :observaciones,
                      costo_mano_obra = :mano_obra,
                      costo_total = :costo_total
                  WHERE id = :orden_id";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':orden_id', $orden_id);
        $stmt->bindParam(':trabajo_realizado', $trabajo_realizado);
        $stmt->bindParam(':observaciones', $observaciones);
        $stmt->bindParam(':mano_obra', $totales['total_mano_obra']);
        $stmt->bindParam(':costo_total', $costo_total);
        $stmt->execute();
        
        // Actualizar estado del turno
        $query_turno = "UPDATE turnos t 
                       INNER JOIN ordenes_reparacion o ON t.orden_id = o.id
                       SET t.estado = 'completado'
                       WHERE o.id = :orden_id";
        
        $stmt_turno = $db->prepare($query_turno);
        $stmt_turno->bindParam(':orden_id', $orden_id);
        $stmt_turno->execute();
        
        $mensaje = "✅ Orden finalizada exitosamente. Costo total: $" . number_format($costo_total, 2);
        
    } catch (PDOException $e) {
        $mensaje = "❌ Error al finalizar orden: " . $e->getMessage();
    }
}

// Obtener lista de órdenes
$query_ordenes = "
    SELECT o.*, t.fecha, t.hora_inicio, c.nombre as cliente_nombre, 
           v.marca, v.modelo, v.matricula,
           (SELECT SUM(subtotal) FROM orden_repuestos WHERE orden_id = o.id) as total_repuestos,
           (SELECT SUM(subtotal) FROM orden_tareas WHERE orden_id = o.id) as total_mano_obra
    FROM ordenes_reparacion o
    INNER JOIN turnos t ON o.turno_id = t.id
    INNER JOIN clientes c ON t.cliente_id = c.id
    INNER JOIN vehiculos v ON t.vehiculo_id = v.id
    ORDER BY o.fecha_creacion DESC, o.numero_orden DESC
";

$stmt_ordenes = $db->prepare($query_ordenes);
$stmt_ordenes->execute();
$ordenes = $stmt_ordenes->fetchAll(PDO::FETCH_ASSOC);

// Obtener turnos sin orden
$query_turnos_sin_orden = "
    SELECT t.*, c.nombre as cliente_nombre, v.marca, v.modelo
    FROM turnos t
    INNER JOIN clientes c ON t.cliente_id = c.id
    INNER JOIN vehiculos v ON t.vehiculo_id = v.id
    WHERE t.orden_id IS NULL AND t.estado IN ('programado', 'en_proceso')
    ORDER BY t.fecha, t.hora_inicio
";

$stmt_turnos_sin_orden = $db->prepare($query_turnos_sin_orden);
$stmt_turnos_sin_orden->execute();
$turnos_sin_orden = $stmt_turnos_sin_orden->fetchAll(PDO::FETCH_ASSOC);

// Obtener repuestos disponibles
$query_repuestos = "SELECT id, nombre, precio, stock FROM repuestos WHERE stock > 0 ORDER BY nombre";
$stmt_repuestos = $db->prepare($query_repuestos);
$stmt_repuestos->execute();
$repuestos = $stmt_repuestos->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container">
    <h2>Órdenes de Reparación</h2>
    
    <?php if ($mensaje): ?>
    <div class="alert">
        <?php echo $mensaje; ?>
    </div>
    <?php endif; ?>
    
    <!-- Crear nueva orden -->
    <div class="form-container" style="margin-bottom: 30px;">
        <h3>Crear Nueva Orden</h3>
        <?php if (empty($turnos_sin_orden)): ?>
            <p>No hay turnos pendientes para crear órdenes.</p>
        <?php else: ?>
            <form method="POST">
                <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                    <div class="form-group" style="flex: 1; min-width: 300px;">
                        <label for="turno_id">Seleccionar Turno:</label>
                        <select id="turno_id" name="turno_id" required>
                            <option value="">Seleccionar turno...</option>
                            <?php foreach ($turnos_sin_orden as $turno): ?>
                            <option value="<?php echo $turno['id']; ?>">
                                <?php echo date('d/m/Y', strtotime($turno['fecha'])) . ' - ' . 
                                       $turno['cliente_nombre'] . ' - ' . 
                                       $turno['marca'] . ' ' . $turno['modelo'] . ' - ' . 
                                       $turno['servicio']; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group" style="flex: 2; min-width: 300px;">
                        <label for="diagnostico">Diagnóstico Inicial:</label>
                        <textarea id="diagnostico" name="diagnostico" rows="3" placeholder="Describa el problema o diagnóstico..." required></textarea>
                    </div>
                </div>
                
                <button type="submit" name="crear_orden" class="btn btn-primary">Crear Orden de Reparación</button>
            </form>
        <?php endif; ?>
    </div>
    
    <!-- Lista de órdenes activas -->
    <h3>Órdenes Activas</h3>
    <?php if (empty($ordenes)): ?>
        <p>No hay órdenes de reparación registradas.</p>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>N° Orden</th>
                    <th>Fecha</th>
                    <th>Cliente</th>
                    <th>Vehículo</th>
                    <th>Estado</th>
                    <th>Repuestos</th>
                    <th>Mano Obra</th>
                    <th>Total</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($ordenes as $orden): 
                    $total_repuestos = $orden['total_repuestos'] ?? 0;
                    $total_mano_obra = $orden['total_mano_obra'] ?? 0;
                    $total = $total_repuestos + $total_mano_obra;
                    
                    $estado_class = '';
                    switch ($orden['estado']) {
                        case 'abierta': $estado_class = 'estado-abierta'; break;
                        case 'en_proceso': $estado_class = 'estado-proceso'; break;
                        case 'completada': $estado_class = 'estado-completada'; break;
                        case 'facturada': $estado_class = 'estado-facturada'; break;
                        case 'cancelada': $estado_class = 'estado-cancelada'; break;
                    }
                ?>
                <tr>
                    <td><strong><?php echo $orden['numero_orden']; ?></strong></td>
                    <td><?php echo date('d/m/Y', strtotime($orden['fecha_creacion'])); ?></td>
                    <td><?php echo htmlspecialchars($orden['cliente_nombre']); ?></td>
                    <td><?php echo htmlspecialchars($orden['marca'] . ' ' . $orden['modelo']); ?><br><small><?php echo $orden['matricula']; ?></small></td>
                    <td>
                        <span class="estado-badge <?php echo $estado_class; ?>">
                            <?php echo ucfirst($orden['estado']); ?>
                        </span>
                    </td>
                    <td>$<?php echo number_format($total_repuestos, 2); ?></td>
                    <td>$<?php echo number_format($total_mano_obra, 2); ?></td>
                    <td><strong>$<?php echo number_format($total, 2); ?></strong></td>
                    <td>
                        <div style="display: flex; flex-direction: column; gap: 5px;">
                            <?php $orden_no_editable = in_array($orden['estado'], ['completada', 'facturada', 'cancelada'], true); ?>
                            <?php if (!$orden_no_editable): ?>
                                <a href="detalle_orden.php?id=<?php echo $orden['id']; ?>" class="btn btn-sm btn-primary">Ver/Editar</a>
                            <?php else: ?>
                                <span class="btn btn-sm btn-secondary" style="cursor: default;">Finalizada</span>
                            <?php endif; ?>

                            <a href="generar_pdf.php?orden_id=<?php echo htmlspecialchars($orden['id'], ENT_QUOTES, 'UTF-8'); ?>" 
                                class="btn btn-sm btn-secondary" 
                                target="_blank"
                                title="Generar PDF de esta orden">
                                <i class="fas fa-file-pdf"></i> PDF
                            </a>
                            <?php if (!$orden_no_editable): ?>
                                <a href="ordenes.php?finalizar_orden=<?php echo $orden['id']; ?>" 
                                   class="btn btn-sm btn-success"
                                   onclick="return confirm('¿Finalizar esta orden de reparación?')">Finalizar</a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<style>
.estado-badge {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: bold;
    display: inline-block;
    min-width: 80px;
    text-align: center;
}

.estado-abierta { background: #d1ecf1; color: #0c5460; }
.estado-proceso { background: #fff3cd; color: #856404; }
.estado-completada { background: #d4edda; color: #155724; }
.estado-facturada { background: #cce5ff; color: #004085; }
.estado-cancelada { background: #f8d7da; color: #721c24; }

.btn-sm {
    padding: 4px 8px;
    font-size: 12px;
    margin: 2px 0;
}
</style>

<?php include 'includes/footer.php'; ?>