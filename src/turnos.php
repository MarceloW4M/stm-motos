<?php
require_once 'includes/auth.php';
require_once 'includes/database.php';

// Endpoint AJAX para obtener vehículos de un cliente
if (isset($_GET['get_vehiculos_cliente'])) {
    require_once 'includes/database.php';
    $database = new Database();
    $db = $database->getConnection();
    
    $cliente_id = $_GET['get_vehiculos_cliente'];
    $query = "SELECT id, marca, modelo, matricula FROM vehiculos WHERE cliente_id = :cliente_id ORDER BY marca, modelo";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':cliente_id', $cliente_id);
    $stmt->execute();
    $vehiculos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json');
    echo json_encode($vehiculos);
    exit;
}

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
$mecanicos_disponibles = ['Mecánico 1', 'Mecánico 2', 'Gerente de turno'];

/**
 * Devuelve la cantidad de turnos cargados para una fecha y franja horaria (por hora).
 */
function contarTurnosPorHora(PDO $db, string $fecha, string $hora_inicio): int {
        $query = "SELECT COUNT(*)
                            FROM turnos
                            WHERE fecha = :fecha
                                AND HOUR(hora_inicio) = HOUR(:hora_inicio)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':fecha', $fecha);
        $stmt->bindParam(':hora_inicio', $hora_inicio);
        $stmt->execute();

        return (int)$stmt->fetchColumn();
}

// Procesar formulario de agregar cliente rápido
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['agregar_cliente_rapido'])) {
    $nombre = $_POST['nombre_cliente'];
    $telefono = $_POST['telefono_cliente'];
    
    try {
        $query = "INSERT INTO clientes (nombre, telefono) VALUES (:nombre, :telefono)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':nombre', $nombre);
        $stmt->bindParam(':telefono', $telefono);
        $stmt->execute();
        
        $nuevo_cliente_id = $db->lastInsertId();
        $mensaje = "✅ Cliente agregado exitosamente. ID: $nuevo_cliente_id";
    } catch (PDOException $e) {
        $mensaje = "❌ Error al agregar cliente: " . $e->getMessage();
    }
}

// Procesar formulario de agregar turno (usando POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['agregar_turno'])) {
    $cliente_id = $_POST['cliente_id'];
    $vehiculo_id = $_POST['vehiculo_id'];
    $fecha = $_POST['fecha'];
    $hora_inicio = $_POST['hora_inicio'];
    $mecanico = $_POST['mecanico'] ?? 'Gerente de turno';
    $servicio_id = $_POST['servicio_id'];
    $descripcion = $_POST['descripcion'];
    
    try {
        $turnos_en_hora = contarTurnosPorHora($db, $fecha, $hora_inicio);
        if ($turnos_en_hora >= 4) {
            $mensaje = "❌ No se puede registrar el turno. La franja de las " . substr($hora_inicio, 0, 2) . ":00 ya tiene el máximo de 4 turnos.";
            throw new RuntimeException('Cupo horario completo');
        }

        // Obtener duración estimada del servicio
        $query_duracion = "SELECT nombre, duracion_estimada FROM servicios WHERE id = :servicio_id";
        $stmt_duracion = $db->prepare($query_duracion);
        $stmt_duracion->bindParam(':servicio_id', $servicio_id);
        $stmt_duracion->execute();
        $servicio_info = $stmt_duracion->fetch(PDO::FETCH_ASSOC);
        
        $duracion_minutos = $servicio_info['duracion_estimada'] ?? 60;
        $nombre_servicio = $servicio_info['nombre'] ?? 'Servicio';
        
        // Calcular hora fin basado en la duración del servicio
        $hora_inicio_dt = new DateTime($hora_inicio);
        $hora_inicio_dt->modify("+$duracion_minutos minutes");
        $hora_fin = $hora_inicio_dt->format('H:i:s');
        
        $query = "INSERT INTO turnos (cliente_id, vehiculo_id, mecanico, fecha, hora_inicio, hora_fin, servicio, servicio_id, descripcion) 
              VALUES (:cliente_id, :vehiculo_id, :mecanico, :fecha, :hora_inicio, :hora_fin, :servicio, :servicio_id, :descripcion)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':cliente_id', $cliente_id);
        $stmt->bindParam(':vehiculo_id', $vehiculo_id);
        $stmt->bindParam(':mecanico', $mecanico);
        $stmt->bindParam(':fecha', $fecha);
        $stmt->bindParam(':hora_inicio', $hora_inicio);
        $stmt->bindParam(':hora_fin', $hora_fin);
        $stmt->bindParam(':servicio', $nombre_servicio);
        $stmt->bindParam(':servicio_id', $servicio_id);
        $stmt->bindParam(':descripcion', $descripcion);
        $stmt->execute();
        
        $mensaje = "✅ Turno agregado exitosamente";
        
        // Redirigir para limpiar el formulario
        header("Location: turnos.php?success=1");
        exit();
        
    } catch (RuntimeException $e) {
        // El mensaje de cupo completo ya quedó definido arriba.
    } catch (PDOException $e) {
        $mensaje = "❌ Error al agregar turno: " . $e->getMessage();
    }
}

// Mostrar mensaje de éxito después de redirección
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $mensaje = "✅ Turno agregado exitosamente";
}

// Procesar eliminación de turno
if (isset($_GET['eliminar'])) {
    $id = $_GET['eliminar'];
    try {
        $query = "DELETE FROM turnos WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        $mensaje = "✅ Turno eliminado exitosamente";
    } catch (PDOException $e) {
        $mensaje = "❌ Error al eliminar turno: " . $e->getMessage();
    }
}

// Procesar cambio de estado
if (isset($_GET['cambiar_estado'])) {
    $id = $_GET['cambiar_estado'];
    $nuevo_estado = $_GET['estado'];
    
    try {
        $query = "UPDATE turnos SET estado = :estado WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':estado', $nuevo_estado);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        $mensaje = "✅ Estado del turno actualizado";
    } catch (PDOException $e) {
        $mensaje = "❌ Error al cambiar estado: " . $e->getMessage();
    }
}

// Obtener lista de turnos con información completa
$query = "
    SELECT t.*, c.nombre as cliente_nombre, c.telefono as cliente_telefono,
           v.marca, v.modelo, v.matricula, s.nombre as servicio_nombre
    FROM turnos t 
    LEFT JOIN clientes c ON t.cliente_id = c.id 
    LEFT JOIN vehiculos v ON t.vehiculo_id = v.id 
    LEFT JOIN servicios s ON t.servicio_id = s.id
    ORDER BY t.fecha DESC, t.hora_inicio DESC
";
$stmt = $db->prepare($query);
$stmt->execute();
$turnos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener lista de clientes para el dropdown
$query_clientes = "SELECT id, nombre, telefono FROM clientes ORDER BY nombre";
$stmt_clientes = $db->prepare($query_clientes);
$stmt_clientes->execute();
$clientes = $stmt_clientes->fetchAll(PDO::FETCH_ASSOC);

// Obtener vehículos del cliente seleccionado (si hay uno)
$vehiculos_cliente = [];
$cliente_seleccionado = $_GET['cliente_id'] ?? '';

if ($cliente_seleccionado) {
    $query_vehiculos_cliente = "SELECT id, marca, modelo, matricula FROM vehiculos WHERE cliente_id = :cliente_id ORDER BY marca, modelo";
    $stmt_vehiculos_cliente = $db->prepare($query_vehiculos_cliente);
    $stmt_vehiculos_cliente->bindParam(':cliente_id', $cliente_seleccionado);
    $stmt_vehiculos_cliente->execute();
    $vehiculos_cliente = $stmt_vehiculos_cliente->fetchAll(PDO::FETCH_ASSOC);
}

// Obtener servicios desde la base de datos
$query_servicios = "SELECT id, nombre, descripcion, precio_estimado, duracion_estimada 
                    FROM servicios WHERE activo = 1 ORDER BY nombre";
$stmt_servicios = $db->prepare($query_servicios);
$stmt_servicios->execute();
$servicios = $stmt_servicios->fetchAll(PDO::FETCH_ASSOC);

// Obtener valores del formulario para mantenerlos después de enviar
$fecha_valor = date('Y-m-d');
$hora_inicio_valor = '09:00';
$servicio_valor = '';
$descripcion_valor = '';
$cliente_id_valor = isset($_GET['cliente_id']) ? $_GET['cliente_id'] : '';
?>

<div class="container">
    <h2>Gestión de Turnos</h2>
    
    <?php if ($mensaje): ?>
    <div class="alert" style="padding: 10px; margin: 10px 0; border-radius: 4px; background: #d4edda; color: #155724;">
        <?php echo $mensaje; ?>
    </div>
    <?php endif; ?>
    
    <!-- Modal para buscar y seleccionar cliente -->
    <div id="modalBuscarCliente" class="modal" style="display: none;">
        <div class="modal-content" style="max-width: 600px;">
            <span class="close" onclick="cerrarModalBuscar()">&times;</span>
            <h3>Seleccionar Cliente</h3>
            <div class="search-container" style="margin-bottom: 20px;">
                <input type="text" id="buscarClienteTurnoInput" placeholder="Buscar por nombre o teléfono..." 
                       style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;" 
                       onkeyup="buscarClienteTurno()">
            </div>
            <div style="max-height: 400px; overflow-y: auto;">
                <table class="table" id="tablaClientesTurnoModal" style="margin: 0;">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Teléfono</th>
                            <th>Email</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clientes as $cliente): ?>
                        <tr class="cliente-row" data-cliente-id="<?php echo $cliente['id']; ?>" data-cliente-nombre="<?php echo htmlspecialchars($cliente['nombre']); ?>">
                            <td><?php echo htmlspecialchars($cliente['nombre']); ?></td>
                            <td><?php echo htmlspecialchars($cliente['telefono']); ?></td>
                            <td><?php echo htmlspecialchars($cliente['email'] ?? ''); ?></td>
                            <td><button type="button" class="btn btn-primary btn-sm" onclick="seleccionarClienteTurno(this)">Seleccionar</button></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal para agregar cliente rápido -->
    <div id="modalCliente" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close" onclick="cerrarModal('modalCliente')">&times;</span>
            <h3>Agregar Cliente Rápido</h3>
            <form method="POST">
                <div class="form-group">
                    <label for="nombre_cliente">Nombre:</label>
                    <input type="text" id="nombre_cliente" name="nombre_cliente" required>
                </div>
                
                <div class="form-group">
                    <label for="telefono_cliente">Teléfono:</label>
                    <input type="text" id="telefono_cliente" name='telefono_cliente' required>
                </div>
                
                <button type="submit" name="agregar_cliente_rapido" class="btn btn-primary" style="margin-right: 5px;">Agregar Cliente</button>
                <button type="button" class="btn btn-secondary" onclick="cerrarModal('modalCliente')">Cancelar</button>
            </form>
        </div>
    </div>

    <!-- Formulario para agregar turno -->
    <div class="form-container">
        <h3>Agregar Nuevo Turno</h3>
        <form method="POST" id="formTurno">
            <div class="form-row">
                <div class="form-group">
                    <label for="clienteNombreTurno">Cliente:</label>
                    <div style="display: flex; gap: 8px;">
                        <input type="text" id="clienteNombreTurno" placeholder="Seleccionar cliente..." readonly style="flex: 1; background-color: #f5f5f5; padding: 8px 12px; border: 1px solid #ced4da; border-radius: 4px;">
                        <button type="button" class="btn btn-primary btn-sm" onclick="abrirModalBuscar()">Buscar</button>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="abrirModal('modalCliente')">+ Nuevo</button>
                    </div>
                    <input type="hidden" id="cliente_id" name="cliente_id" value="" required>
                </div>
                
                <div class="form-group">
                    <label for="vehiculo_id">Vehículo:</label>
                    <select id="vehiculo_id" name="vehiculo_id" required>
                        <option value="">Seleccione un cliente primero</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="servicio_id">Servicio:</label>
                    <select id="servicio_id" name="servicio_id" required onchange="actualizarHoraFin()">
                        <option value="">Seleccionar servicio...</option>
                        <?php foreach ($servicios as $servicio): ?>
                        <option value="<?php echo $servicio['id']; ?>" 
                            data-duracion="<?php echo $servicio['duracion_estimada']; ?>"
                            data-precio="<?php echo $servicio['precio_estimado']; ?>">
                            <?php echo htmlspecialchars($servicio['nombre']); ?> 
                            (<?php echo number_format($servicio['precio_estimado'], 2); ?>$ - 
                            <?php echo $servicio['duracion_estimada']; ?> min)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="fecha">Fecha:</label>
                    <input type="date" id="fecha" name="fecha" value="<?php echo $fecha_valor; ?>" required>
                </div>

                <div class="form-group">
                    <label for="mecanico">Mecánico:</label>
                    <select id="mecanico" name="mecanico" required>
                        <option value="">Seleccionar mecánico...</option>
                        <?php foreach ($mecanicos_disponibles as $mecanico): ?>
                            <option value="<?php echo htmlspecialchars($mecanico, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($mecanico, ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="hora_inicio">Hora Inicio:</label>
                    <input type="time" id="hora_inicio" name="hora_inicio" value="<?php echo $hora_inicio_valor; ?>" min="08:00" max="18:00" step="3600" required onchange="actualizarHoraFin()">
                </div>
                
                <div class="form-group">
                    <label for="hora_fin">Hora Fin Estimada:</label>
                    <input type="time" id="hora_fin" name="hora_fin" readonly style="background-color: #f8f9fa;">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Duración estimada: <span id="duracion_estimada">0</span> minutos</label>
                </div>
                
                <div class="form-group">
                    <label>Precio estimado: $<span id="precio_estimado">0.00</span></label>
                </div>
            </div>
            
            <div class="form-group">
                <label for="descripcion">Descripción adicional:</label>
                <textarea id="descripcion" name="descripcion" rows="3" placeholder="Detalles adicionales del trabajo..."><?php echo htmlspecialchars($descripcion_valor); ?></textarea>
            </div>
            
            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" name="agregar_turno" value="1" class="btn btn-primary">Agendar Turno</button>
                <button type="button" class="btn btn-secondary" onclick="limpiarFormulario()">Limpiar</button>
            </div>
        </form>
    </div>
    
    <!-- Búsqueda -->
    <div class="search-container">
        <input type="text" id="searchInput" placeholder="Buscar turno por cliente, vehículo o servicio..." onkeyup="searchTurnos()">
        <button class="btn btn-primary" onclick="searchTurnos()">Buscar</button>
        <select id="filterEstado" onchange="filtrarTurnos()" style="margin-left: 10px;">
            <option value="">Todos los estados</option>
            <option value="programado">Programados</option>
            <option value="en_proceso">En Proceso</option>
            <option value="completado">Completados</option>
            <option value="cancelado">Cancelados</option>
        </select>
    </div>
    
    <!-- Tabla de turnos -->
    <h3>Turnos Programados</h3>
    <?php if (empty($turnos)): ?>
        <p>No hay turnos programados. Agrega el primer turno usando el formulario arriba.</p>
    <?php else: ?>
        <table class="table" id="turnosTable">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Hora</th>
                    <th>Mecánico</th>
                    <th>Cliente</th>
                    <th>Vehículo</th>
                    <th>Servicio</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($turnos as $turno): 
                    $estado_class = '';
                    switch ($turno['estado']) {
                        case 'programado': $estado_class = 'estado-programado'; break;
                        case 'en_proceso': $estado_class = 'estado-proceso'; break;
                        case 'completado': $estado_class = 'estado-completado'; break;
                        case 'cancelado': $estado_class = 'estado-cancelado'; break;
                    }
                ?>
                <tr class="turno-row" data-estado="<?php echo $turno['estado']; ?>">
                    <td><?php echo date('d/m/Y', strtotime($turno['fecha'])); ?></td>
                    <td><?php echo date('H:i', strtotime($turno['hora_inicio'])) . ' - ' . date('H:i', strtotime($turno['hora_fin'])); ?></td>
                    <td><?php echo htmlspecialchars($turno['mecanico'] ?? 'Sin asignar', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($turno['cliente_nombre']); ?><br><small><?php echo $turno['cliente_telefono']; ?></small></td>
                    <td><?php echo htmlspecialchars($turno['marca'] . ' ' . $turno['modelo']); ?><br><small><?php echo $turno['matricula']; ?></small></td>
                    <td><?php echo htmlspecialchars($turno['servicio_nombre'] ?? $turno['servicio']); ?><?php echo $turno['descripcion'] ? '<br><small>' . htmlspecialchars(substr($turno['descripcion'], 0, 50)) . '...</small>' : ''; ?></td>
                    <td>
                        <span class="estado-badge <?php echo $estado_class; ?>">
                            <?php echo ucfirst($turno['estado']); ?>
                        </span>
                    </td>
                    <td>
                        <div style="display: flex; flex-direction: column; gap: 5px;">
                            <select onchange="cambiarEstado(<?php echo $turno['id']; ?>, this.value)" class="estado-select">
                                <option value="programado" <?php echo $turno['estado'] == 'programado' ? 'selected' : ''; ?>>Programado</option>
                                <option value="en_proceso" <?php echo $turno['estado'] == 'en_proceso' ? 'selected' : ''; ?>>En Proceso</option>
                                <option value="completado" <?php echo $turno['estado'] == 'completado' ? 'selected' : ''; ?>>Completado</option>
                                <option value="cancelado" <?php echo $turno['estado'] == 'cancelado' ? 'selected' : ''; ?>>Cancelado</option>
                            </select>
                            
                            <?php if ($turno['estado'] === 'completado'): ?>
                                <span class="btn btn-sm btn-primary" style="opacity: 0.6; pointer-events: none; cursor: not-allowed;" title="No se puede editar un turno completado">Editar</span>
                            <?php else: ?>
                                <a href="editar_turno.php?id=<?php echo $turno['id']; ?>" class="btn btn-sm btn-primary">Editar</a>
                            <?php endif; ?>
                            <a href="generar_pdf.php?turno_id=<?php echo $turno['id']; ?>" class="btn btn-sm btn-secondary" target="_blank">PDF</a>
                            <?php if ($turno['estado'] === 'completado'): ?>
                                <span class="btn btn-sm btn-danger" style="opacity: 0.6; pointer-events: none; cursor: not-allowed;" title="No se puede eliminar un turno completado">Eliminar</span>
                            <?php else: ?>
                                <a href="turnos.php?eliminar=<?php echo $turno['id']; ?>" class="btn btn-sm btn-danger" 
                                   onclick="return confirm('¿Está seguro de eliminar este turno?')">Eliminar</a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<script>
function abrirModal(modalId) {
    document.getElementById(modalId).style.display = 'block';
}

function cerrarModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

function abrirModalBuscar() {
    document.getElementById('modalBuscarCliente').style.display = 'block';
    document.getElementById('buscarClienteTurnoInput').value = '';
    buscarClienteTurno();
    document.getElementById('buscarClienteTurnoInput').focus();
}

function cerrarModalBuscar() {
    document.getElementById('modalBuscarCliente').style.display = 'none';
}

function buscarClienteTurno() {
    const input = document.getElementById('buscarClienteTurnoInput');
    const filtro = input.value.toUpperCase();
    const tabla = document.getElementById('tablaClientesTurnoModal');
    const filas = tabla.getElementsByClassName('cliente-row');
    
    for (let i = 0; i < filas.length; i++) {
        const nombre = filas[i].cells[0].textContent.toUpperCase();
        const telefono = filas[i].cells[1].textContent.toUpperCase();
        
        if (nombre.indexOf(filtro) > -1 || telefono.indexOf(filtro) > -1) {
            filas[i].style.display = '';
        } else {
            filas[i].style.display = 'none';
        }
    }
}

function seleccionarClienteTurno(btn) {
    const fila = btn.parentNode.parentNode;
    const clienteId = fila.getAttribute('data-cliente-id');
    const clienteNombre = fila.getAttribute('data-cliente-nombre');
    
    // Llenar los campos del formulario
    document.getElementById('cliente_id').value = clienteId;
    document.getElementById('clienteNombreTurno').value = clienteNombre;
    
    // Cargar vehículos del cliente
    fetch('turnos.php?get_vehiculos_cliente=' + clienteId)
        .then(response => response.json())
        .then(vehiculos => {
            const selectVehiculo = document.getElementById('vehiculo_id');
            // Limpiar opciones excepto la primera
            selectVehiculo.innerHTML = '<option value="">Seleccionar vehículo...</option>';
            
            // Agregar vehículos
            vehiculos.forEach(vehiculo => {
                const option = document.createElement('option');
                option.value = vehiculo.id;
                option.textContent = vehiculo.marca + ' ' + vehiculo.modelo + ' - ' + vehiculo.matricula;
                selectVehiculo.appendChild(option);
            });
            
            // Habilitar el select
            selectVehiculo.disabled = false;
        })
        .catch(error => console.error('Error:', error));
    
    // Cerrar modal
    cerrarModalBuscar();
}

window.onclick = function(event) {
    const modal = document.getElementById('modalBuscarCliente');
    if (event.target === modal) {
        modal.style.display = 'none';
    }
}

// Función para actualizar la hora fin automáticamente
function actualizarHoraFin() {
    const servicioSelect = document.getElementById('servicio_id');
    const horaInicio = document.getElementById('hora_inicio');
    const horaFin = document.getElementById('hora_fin');
    const duracionSpan = document.getElementById('duracion_estimada');
    const precioSpan = document.getElementById('precio_estimado');
    
    if (servicioSelect.value && horaInicio.value) {
        const duracion = parseInt(servicioSelect.options[servicioSelect.selectedIndex].getAttribute('data-duracion')) || 60;
        const precio = parseFloat(servicioSelect.options[servicioSelect.selectedIndex].getAttribute('data-precio')) || 0;
        
        // Actualizar duración y precio
        duracionSpan.textContent = duracion;
        precioSpan.textContent = precio.toFixed(2);
        
        // Calcular hora fin
        const [hours, minutes] = horaInicio.value.split(':').map(Number);
        const totalMinutes = hours * 60 + minutes + duracion;
        
        const endHours = Math.floor(totalMinutes / 60) % 24;
        const endMinutes = totalMinutes % 60;
        
        horaFin.value = `${endHours.toString().padStart(2, '0')}:${endMinutes.toString().padStart(2, '0')}`;
    }
}

function cambiarEstado(turnoId, nuevoEstado) {
    if (confirm('¿Cambiar estado del turno?')) {
        window.location.href = `turnos.php?cambiar_estado=${turnoId}&estado=${nuevoEstado}`;
    }
}

function searchTurnos() {
    const input = document.getElementById('searchInput');
    const filter = input.value.toUpperCase();
    const table = document.getElementById('turnosTable');
    const tr = table.getElementsByTagName('tr');
    
    for (let i = 1; i < tr.length; i++) {
        const tdMecanico = tr[i].getElementsByTagName('td')[2];
        const tdCliente = tr[i].getElementsByTagName('td')[3];
        const tdVehiculo = tr[i].getElementsByTagName('td')[4];
        const tdServicio = tr[i].getElementsByTagName('td')[5];
        
        if (tdMecanico && tdCliente && tdVehiculo && tdServicio) {
            const txtValue = tdMecanico.textContent + tdCliente.textContent + tdVehiculo.textContent + tdServicio.textContent;
            if (txtValue.toUpperCase().indexOf(filter) > -1) {
                tr[i].style.display = '';
            } else {
                tr[i].style.display = 'none';
            }
        }
    }
}

function filtrarTurnos() {
    const filtro = document.getElementById('filterEstado').value;
    const rows = document.querySelectorAll('.turno-row');
    
    rows.forEach(row => {
        if (!filtro || row.getAttribute('data-estado') === filtro) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

function limpiarFormulario() {
    window.location.href = 'turnos.php';
}

// Cerrar modal al hacer clic fuera de él
window.onclick = function(event) {
    const modal = document.getElementById('modalCliente');
    if (event.target == modal) {
        cerrarModal('modalCliente');
    }
}

// Llamar a la función al cargar la página
document.addEventListener('DOMContentLoaded', function() {
    actualizarHoraFin();
    
    // Establecer hora por defecto si no hay valor
    if (!document.getElementById('hora_inicio').value) {
        document.getElementById('hora_inicio').value = '09:00';
        actualizarHoraFin();
    }
});
</script>

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

.estado-programado { background: #fff3cd; color: #856404; }
.estado-proceso { background: #d1ecf1; color: #0c5460; }
.estado-completado { background: #d4edda; color: #155724; }
.estado-cancelado { background: #f8d7da; color: #721c24; }

.estado-select {
    padding: 3px;
    border-radius: 4px;
    border: 1px solid #ddd;
    font-size: 12px;
    width: 100%;
}

.btn-sm {
    padding: 4px 8px;
    font-size: 12px;
    margin: 2px 0;
}

.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
}

.modal-content {
    background-color: white;
    margin: 10% auto;
    padding: 20px;
    border-radius: 8px;
    width: 90%;
    max-width: 400px;
    position: relative;
}

.close {
    color: #aaa;
    float: right;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
    position: absolute;
    right: 15px;
    top: 10px;
}

.close:hover {
    color: black;
}

/* Responsive para tablas de turnos */
@media (max-width: 768px) {
    .table th:nth-child(3),
    .table td:nth-child(3),
    .table th:nth-child(4),
    .table td:nth-child(4) {
        display: none;
    }
    
    .search-container {
        flex-direction: column;
    }
    
    .search-container select {
        margin-left: 0;
        margin-top: 10px;
    }
}

/* Estilos para información de duración y precio */
#duracion_estimada, #precio_estimado {
    font-weight: bold;
    color: #007bff;
}
</style>

<?php include 'includes/footer.php'; ?>
