<?php
require_once 'includes/auth.php';
require_once 'includes/database.php';
require_once 'includes/header.php';

// Verificar autenticación
requireAuth();

// Enlace al CSS especializado
?>
<link rel="stylesheet" href="css/styleess.css">
<?php

$database = new Database();
$db = $database->getConnection();

// allow id to come from POST too (forms may submit without query string)
$orden_id = $_GET['id'] ?? $_POST['orden_id'] ?? 0;

// Obtener datos de la orden
$query = "
    SELECT o.*, t.fecha, t.hora_inicio, t.servicio, 
           c.nombre as cliente_nombre, c.telefono, c.email, c.direccion,
           v.marca, v.modelo, v.matricula, v.anio, v.vin,
           u.nombre as tecnico_nombre
    FROM ordenes_reparacion o
    INNER JOIN turnos t ON o.turno_id = t.id
    INNER JOIN clientes c ON t.cliente_id = c.id
    INNER JOIN vehiculos v ON t.vehiculo_id = v.id
    LEFT JOIN usuarios u ON o.tecnico_id = u.id
    WHERE o.id = :orden_id
";

$stmt = $db->prepare($query);
$stmt->bindParam(':orden_id', $orden_id);
$stmt->execute();
$orden = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$orden) {
    header("Location: ordenes.php");
    exit();
}

// Determinar si la orden ya está finalizada (no editable)
$orden_finalizada = in_array($orden['estado'], ['completada', 'facturada', 'cancelada'], true);

// Obtener repuestos de esta orden
$query_repuestos = "
    SELECT or2.*, r.nombre, r.descripcion
    FROM orden_repuestos or2
    INNER JOIN repuestos r ON or2.repuesto_id = r.id
    WHERE or2.orden_id = :orden_id
    ORDER BY r.nombre
";

$stmt_repuestos = $db->prepare($query_repuestos);
$stmt_repuestos->bindParam(':orden_id', $orden_id);
$stmt_repuestos->execute();
$repuestos_orden = $stmt_repuestos->fetchAll(PDO::FETCH_ASSOC);

// Obtener tareas de esta orden
$query_tareas = "
    SELECT * FROM orden_tareas 
    WHERE orden_id = :orden_id
    ORDER BY created_at
";

$stmt_tareas = $db->prepare($query_tareas);
$stmt_tareas->bindParam(':orden_id', $orden_id);
$stmt_tareas->execute();
$tareas_orden = $stmt_tareas->fetchAll(PDO::FETCH_ASSOC);

// Obtener repuestos disponibles
$query_repuestos_disponibles = "SELECT id, nombre, precio, stock FROM repuestos WHERE stock > 0 ORDER BY nombre";
$stmt_repuestos_disponibles = $db->prepare($query_repuestos_disponibles);
$stmt_repuestos_disponibles->execute();
$repuestos_disponibles = $stmt_repuestos_disponibles->fetchAll(PDO::FETCH_ASSOC);

// Procesar formularios (similar a ordenes.php)
$mensaje = '';

// agregar repuesto mediante POST (solo si no está finalizada)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['agregar_repuesto'])) {
    if ($orden_finalizada) {
        $mensaje = "❌ Esta orden está finalizada y no puede modificarse.";
    } else {
        $orden_id_post = $_POST['orden_id'];
        $repuesto_id = $_POST['repuesto_id'];
        $cantidad = $_POST['cantidad'];
        try {
            // precio del repuesto
            $query_precio = "SELECT precio FROM repuestos WHERE id = :repuesto_id";
            $stmt_precio = $db->prepare($query_precio);
            $stmt_precio->bindParam(':repuesto_id', $repuesto_id);
            $stmt_precio->execute();
            $precio = $stmt_precio->fetch(PDO::FETCH_COLUMN);
            
            // stock
            $query_stock = "SELECT stock FROM repuestos WHERE id = :repuesto_id";
            $stmt_stock = $db->prepare($query_stock);
            $stmt_stock->bindParam(':repuesto_id', $repuesto_id);
            $stmt_stock->execute();
            $stock_actual = $stmt_stock->fetch(PDO::FETCH_COLUMN);
            
            if ($stock_actual < $cantidad) {
                $mensaje = "❌ Stock insuficiente. Disponible: $stock_actual";
            } else {
                $query = "INSERT INTO orden_repuestos (orden_id, repuesto_id, cantidad, precio_unitario) 
                          VALUES (:orden_id, :repuesto_id, :cantidad, :precio)";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':orden_id', $orden_id_post);
                $stmt->bindParam(':repuesto_id', $repuesto_id);
                $stmt->bindParam(':cantidad', $cantidad);
                $stmt->bindParam(':precio', $precio);
                $stmt->execute();
                
                // actualizar stock
                $query_update = "UPDATE repuestos SET stock = stock - :cantidad WHERE id = :repuesto_id";
                $stmt_update = $db->prepare($query_update);
                $stmt_update->bindParam(':cantidad', $cantidad);
                $stmt_update->bindParam(':repuesto_id', $repuesto_id);
                $stmt_update->execute();
                
                $mensaje = "✅ Repuesto agregado a la orden";
                // redirigir para refrescar datos y evitar repost
                header("Location: detalle_orden.php?id=" . urlencode($orden_id_post));
                exit();
            }
        } catch (Exception $e) {
            $mensaje = "❌ Error al agregar repuesto: " . $e->getMessage();
        }
    }
}

// procesar tareas similar al código existente (solo si no está finalizada)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['agregar_tarea'])) {
    if ($orden_finalizada) {
        $mensaje = "❌ Esta orden está finalizada y no puede modificarse.";
    } else {
        $orden_id_post = $_POST['orden_id'];
        $descripcion = $_POST['descripcion_tarea'];
        $tiempo = $_POST['tiempo_horas'];
        $costo = $_POST['costo_hora'];
        try {
            // subtotal es columna generada por la base, no se inserta manualmente
            $query = "INSERT INTO orden_tareas (orden_id, descripcion, tiempo_horas, costo_hora) 
                      VALUES (:orden_id, :descr, :tiempo, :costo)";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':orden_id', $orden_id_post);
            $stmt->bindParam(':descr', $descripcion);
            $stmt->bindParam(':tiempo', $tiempo);
            $stmt->bindParam(':costo', $costo);
            $stmt->execute();
            $mensaje = "✅ Tarea agregada";
            header("Location: detalle_orden.php?id=" . urlencode($orden_id_post));
            exit();
        } catch (Exception $e) {
            $mensaje = "❌ Error al agregar tarea: " . $e->getMessage();
        }
    }
}
?>

<div class="container">
    <?php if (!empty($mensaje)): ?>
        <div class="alert <?php echo strpos($mensaje,'✅')===0 ? 'success' : 'error'; ?>">
            <?php echo $mensaje; ?>
        </div>
    <?php endif; ?>

    <h2>Orden de Reparación: <?php echo $orden['numero_orden']; ?></h2>
    
    <!-- Información principal -->
    <div class="form-container" style="margin-bottom: 20px;">
        <div style="display: flex; justify-content: space-between; flex-wrap: wrap; gap: 20px;">
            <div style="flex: 1; min-width: 250px;">
                <h3>Información General</h3>
                <p><strong>Estado:</strong> 
                    <span class="estado-badge estado-<?php echo $orden['estado']; ?>">
                        <?php echo ucfirst($orden['estado']); ?>
                    </span>
                </p>
                <p><strong>Fecha creación:</strong> <?php echo date('d/m/Y', strtotime($orden['fecha_creacion'])); ?></p>
                <p><strong>Técnico asignado:</strong> <?php echo $orden['tecnico_nombre'] ?? 'No asignado'; ?></p>
            </div>
            <div style="flex: 1; min-width: 250px;">
                <h3>Cliente</h3>
                <p><strong>Nombre:</strong> <?php echo htmlspecialchars($orden['cliente_nombre']); ?></p>
                <p><strong>Teléfono:</strong> <?php echo $orden['telefono']; ?></p>
                <p><strong>Email:</strong> <?php echo $orden['email'] ?? 'No especificado'; ?></p>
            </div>
            <div style="flex: 1; min-width: 250px;">
                <h3>Vehículo</h3>
                <p><strong>Vehículo:</strong> <?php echo htmlspecialchars($orden['marca'] . ' ' . $orden['modelo']); ?></p>
                <p><strong>Matrícula:</strong> <?php echo $orden['matricula']; ?></p>
                <p><strong>Año:</strong> <?php echo $orden['anio'] ?? 'N/A'; ?></p>
                <p><strong>Servicio:</strong> <?php echo $orden['servicio']; ?></p>
            </div>
        </div>
        
        <!-- Diagnóstico -->
        <?php if (!empty($orden['diagnostico'])): ?>
        <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #eee;">
            <h4>Diagnóstico</h4>
            <p><?php echo nl2br(htmlspecialchars($orden['diagnostico'])); ?></p>
        </div>
        <?php endif; ?>
        
        <!-- Trabajo realizado -->
        <?php if (!empty($orden['trabajo_realizado'])): ?>
        <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #eee;">
            <h4>Trabajo Realizado</h4>
            <p><?php echo nl2br(htmlspecialchars($orden['trabajo_realizado'])); ?></p>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Sección de repuestos -->
    <div class="form-container" style="margin-bottom: 20px;">
        <h3>Repuestos Utilizados</h3>
        
        <!-- Formulario para agregar repuesto -->
        <?php if (!$orden_finalizada): ?>
        <form method="POST" action="detalle_orden.php?id=<?php echo $orden_id; ?>">
            <input type="hidden" name="orden_id" value="<?php echo $orden_id; ?>">
            <div class="form-row" style="align-items: flex-end;">
                <div class="form-group" style="flex: 2; min-width: 200px;">
                    <label for="repuestoNombre">Repuesto:</label>
                    <div style="display: flex; gap: 8px; align-items: center;">
                        <input type="text" id="repuestoNombre" placeholder="Seleccionar repuesto..." readonly style="flex:1; background-color:#f5f5f5; padding:8px 12px; border:1px solid #ced4da; border-radius:4px;">
                        <button type="button" class="btn btn-primary btn-sm" onclick="abrirModalBuscarRepuesto()">Buscar</button>
                    </div>
                    <input type="hidden" id="repuesto_id" name="repuesto_id" required>
                </div>
                
                <div class="form-group">
                    <label for="cantidad">Cantidad:</label>
                    <input type="number" id="cantidad" name="cantidad" min="1" value="1" required>
                </div>
                
                <div class="form-group">
                    <button type="submit" name="agregar_repuesto" class="btn btn-primary">Agregar Repuesto</button>
                </div>
            </div>
        </form>
        
        <!-- Modal para buscar repuesto -->
        <div id="modalBuscarRepuesto" class="modal" style="display:none;">
            <div class="modal-content" style="max-width:600px;">
                <span class="close" onclick="cerrarModalBuscarRepuesto()">&times;</span>
                <h3>Seleccionar Repuesto</h3>
                <input type="text" id="buscarRepuestoInput" placeholder="Buscar por nombre..." onkeyup="buscarRepuesto()" style="width:100%; padding:10px; margin-bottom:10px; border:1px solid #ddd; border-radius:4px;">
                <div style="max-height:400px; overflow-y:auto;">
                    <table class="table" id="tablaRepuestosModal" style="margin:0;">
                        <thead><tr><th>Nombre</th><th>Stock</th><th>Precio</th><th>Acción</th></tr></thead>
                        <tbody>
                            <?php foreach($repuestos_disponibles as $rep): ?>
                            <tr class="repuesto-row" data-id="<?php echo $rep['id']; ?>" data-nombre="<?php echo htmlspecialchars($rep['nombre']); ?>">
                                <td><?php echo htmlspecialchars($rep['nombre']); ?></td>
                                <td><?php echo $rep['stock']; ?></td>
                                <td><?php echo number_format($rep['precio'],2); ?></td>
                                <td><button type="button" class="btn btn-primary btn-sm" onclick="seleccionarRepuesto(this)">Seleccionar</button></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php else: ?>
            <p><strong>Orden finalizada:</strong> no es posible modificar repuestos ni tareas. Puede generar el PDF.</p>
        <?php endif; ?>
        <!-- Lista de repuestos agregados -->
        <?php if (empty($repuestos_orden)): ?>
            <p>No se han agregado repuestos a esta orden.</p>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Repuesto</th>
                        <th>Cantidad</th>
                        <th>Precio Unit.</th>
                        <th>Subtotal</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $total_repuestos = 0;
                    foreach ($repuestos_orden as $repuesto): 
                        $subtotal = $repuesto['cantidad'] * $repuesto['precio_unitario'];
                        $total_repuestos += $subtotal;
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($repuesto['nombre']); ?></td>
                        <td><?php echo $repuesto['cantidad']; ?></td>
                        <td>$<?php echo number_format($repuesto['precio_unitario'], 2); ?></td>
                        <td>$<?php echo number_format($subtotal, 2); ?></td>
                        <td>
                            <?php if (!$orden_finalizada): ?>
                                <a href="eliminar_repuesto_orden.php?orden_id=<?php echo $orden_id; ?>&repuesto_id=<?php echo $repuesto['id']; ?>" 
                                   class="btn btn-sm btn-secondary"
                                   onclick="return confirm('¿Eliminar este repuesto de la orden?')">Eliminar</a>
                            <?php else: ?>
                                <span style="color:#666;">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <tr style="font-weight: bold; background-color: #f0f0f0;">
                        <td colspan="3" style="text-align: right; font-weight: bold;">Total Repuestos:</td>
                        <td>$<?php echo number_format($total_repuestos, 2); ?></td>
                        <td></td>
                    </tr>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    
    <!-- Sección de tareas -->
    <div class="form-container" style="margin-bottom: 20px;">
        <h3>Tareas Realizadas</h3>
        
        <!-- Formulario para agregar tarea -->
        <?php if (!$orden_finalizada): ?>
        <form method="POST" action="detalle_orden.php?id=<?php echo $orden_id; ?>">
            <input type="hidden" name="orden_id" value="<?php echo $orden_id; ?>">
            <div class="form-row">
                <div class="form-group" style="flex: 2; min-width: 250px;">
                    <label for="descripcion_tarea">Descripción:</label>
                    <textarea id="descripcion_tarea" name="descripcion_tarea" rows="2" placeholder="Descripción de la tarea..." required></textarea>
                </div>
                
                <div class="form-group">
                    <label for="tiempo_horas">Horas:</label>
                    <input type="number" id="tiempo_horas" name="tiempo_horas" min="0.5" max="24" step="0.5" value="1" required>
                </div>
                
                <div class="form-group">
                    <label for="costo_hora">Costo/Hora:</label>
                    <input type="number" id="costo_hora" name="costo_hora" min="0" step="0.01" value="50" required>
                </div>
            </div>
            
            <div style="margin-top: 15px;">
                <button type="submit" name="agregar_tarea" class="btn btn-primary">Agregar Tarea</button>
            </div>
        </form>
        <?php else: ?>
            <p><strong>Orden finalizada:</strong> no es posible modificar repuestos ni tareas. Puede generar el PDF.</p>
        <?php endif; ?>
        
        <!-- Lista de tareas -->
        <?php if (empty($tareas_orden)): ?>
            <p>No se han registrado tareas para esta orden.</p>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Tarea</th>
                        <th>Horas</th>
                        <th>Costo/Hora</th>
                        <th>Subtotal</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $total_mano_obra = 0;
                    foreach ($tareas_orden as $tarea): 
                        $subtotal = $tarea['tiempo_horas'] * $tarea['costo_hora'];
                        $total_mano_obra += $subtotal;
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($tarea['descripcion']); ?></td>
                        <td><?php echo number_format($tarea['tiempo_horas'], 1); ?> h</td>
                        <td>$<?php echo number_format($tarea['costo_hora'], 2); ?></td>
                        <td>$<?php echo number_format($subtotal, 2); ?></td>
                        <td>
                            <?php if (!$orden_finalizada): ?>
                                <a href="eliminar_tarea_orden.php?orden_id=<?php echo $orden_id; ?>&tarea_id=<?php echo $tarea['id']; ?>" 
                                   class="btn btn-sm btn-secondary"
                                   onclick="return confirm('¿Eliminar esta tarea?')">Eliminar</a>
                            <?php else: ?>
                                <span style="color:#666;">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <tr style="font-weight: bold; background-color: #f0f0f0;">
                        <td colspan="3" style="text-align: right; font-weight: bold;">Total Mano de Obra:</td>
                        <td>$<?php echo number_format($total_mano_obra, 2); ?></td>
                        <td></td>
                    </tr>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    
    <!-- Resumen de totales -->
    <div class="form-container">
        <h3>Resumen de Costos</h3>
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
            <div>
                <p style="margin: 5px 0;"><strong>Total Repuestos:</strong> $<?php echo number_format($total_repuestos ?? 0, 2); ?></p>
                <p style="margin: 5px 0;"><strong>Total Mano de Obra:</strong> $<?php echo number_format($total_mano_obra ?? 0, 2); ?></p>
                <p style="margin: 10px 0; font-size: 18px; color: #007bff;"><strong>Total Orden:</strong> $<?php echo number_format(($total_repuestos ?? 0) + ($total_mano_obra ?? 0), 2); ?></p>
            </div>
            <div style="display: flex; gap: 10px;">
                <a href="generar_pdf.php?orden_id=<?php echo $orden_id; ?>" class="btn btn-primary" target="_blank">Generar PDF</a>
                <a href="ordenes.php" class="btn btn-secondary">Volver a Órdenes</a>
            </div>
        </div>
    </div>

<script>
function abrirModalBuscarRepuesto(){
    document.getElementById('modalBuscarRepuesto').style.display='block';
}
function cerrarModalBuscarRepuesto(){
    document.getElementById('modalBuscarRepuesto').style.display='none';
}
function buscarRepuesto(){
    var term = document.getElementById('buscarRepuestoInput').value.toLowerCase();
    var filas = document.querySelectorAll('#tablaRepuestosModal .repuesto-row');
    filas.forEach(function(f){
        var nombre = f.getAttribute('data-nombre').toLowerCase();
        f.style.display = nombre.indexOf(term) !== -1 ? '' : 'none';
    });
}
function seleccionarRepuesto(btn){
    var fila = btn.closest('tr');
    var id = fila.getAttribute('data-id');
    var nombre = fila.getAttribute('data-nombre');
    document.getElementById('repuesto_id').value = id;
    document.getElementById('repuestoNombre').value = nombre;
    cerrarModalBuscarRepuesto();
}
window.onclick = function(e){
    var modal = document.getElementById('modalBuscarRepuesto');
    if(e.target===modal) modal.style.display='none';
}
</script>

</div>

<?php require_once 'includes/footer.php'; ?>
