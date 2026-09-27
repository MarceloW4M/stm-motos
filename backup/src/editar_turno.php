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
$mecanicos_disponibles = ['Mecánico 1', 'Mecánico 2', 'Gerente de turno'];

/**
 * Devuelve la cantidad de turnos cargados para una fecha y franja horaria (por hora),
 * opcionalmente excluyendo un turno existente.
 */
function contarTurnosPorHoraEdicion(PDO $db, string $fecha, string $hora_inicio, int $excluir_id = 0): int {
        $query = "SELECT COUNT(*)
                            FROM turnos
                            WHERE fecha = :fecha
                                AND HOUR(hora_inicio) = HOUR(:hora_inicio)
                                AND id <> :excluir_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':fecha', $fecha);
        $stmt->bindParam(':hora_inicio', $hora_inicio);
        $stmt->bindParam(':excluir_id', $excluir_id, PDO::PARAM_INT);
        $stmt->execute();

        return (int)$stmt->fetchColumn();
}

// Obtener ID del turno a editar
$id = $_GET['id'] ?? 0;

// Obtener datos del turno
$turno = null;
if ($id) {
    $query = "SELECT * FROM turnos WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    $turno = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Si no existe el turno, redirigir
if (!$turno) {
    header("Location: turnos.php");
    exit();
}

// Procesar actualización
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cliente_id = $_POST['cliente_id'];
    $vehiculo_id = $_POST['vehiculo_id'];
    $fecha = $_POST['fecha'];
    $hora_inicio = $_POST['hora_inicio'];
    $hora_fin = $_POST['hora_fin'];
    $mecanico = $_POST['mecanico'] ?? 'Gerente de turno';
    $servicio = $_POST['servicio'];
    $descripcion = $_POST['descripcion'];
    $estado = $_POST['estado'];
    
    try {
        $turnos_en_hora = contarTurnosPorHoraEdicion($db, $fecha, $hora_inicio, (int)$id);
        if ($turnos_en_hora >= 4) {
            $mensaje = "❌ No se puede actualizar el turno. La franja de las " . substr($hora_inicio, 0, 2) . ":00 ya tiene el máximo de 4 turnos.";
            throw new RuntimeException('Cupo horario completo');
        }

        $query = "UPDATE turnos SET cliente_id = :cliente_id, vehiculo_id = :vehiculo_id, 
                  mecanico = :mecanico, fecha = :fecha, hora_inicio = :hora_inicio, hora_fin = :hora_fin,
                  servicio = :servicio, descripcion = :descripcion, estado = :estado 
                  WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':cliente_id', $cliente_id);
        $stmt->bindParam(':vehiculo_id', $vehiculo_id);
        $stmt->bindParam(':mecanico', $mecanico);
        $stmt->bindParam(':fecha', $fecha);
        $stmt->bindParam(':hora_inicio', $hora_inicio);
        $stmt->bindParam(':hora_fin', $hora_fin);
        $stmt->bindParam(':servicio', $servicio);
        $stmt->bindParam(':descripcion', $descripcion);
        $stmt->bindParam(':estado', $estado);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        $mensaje = "✅ Turno actualizado exitosamente";
        // Recargar datos
        $turno = array_merge($turno, $_POST);
    } catch (RuntimeException $e) {
        // El mensaje de cupo completo ya quedó definido arriba.
    } catch (PDOException $e) {
        $mensaje = "❌ Error al actualizar turno: " . $e->getMessage();
    }
}

// Obtener lista de clientes y vehículos
$query_clientes = "SELECT id, nombre FROM clientes ORDER BY nombre";
$stmt_clientes = $db->prepare($query_clientes);
$stmt_clientes->execute();
$clientes = $stmt_clientes->fetchAll(PDO::FETCH_ASSOC);

$query_vehiculos = "SELECT id, marca, modelo FROM vehiculos ORDER BY marca, modelo";
$stmt_vehiculos = $db->prepare($query_vehiculos);
$stmt_vehiculos->execute();
$vehiculos = $stmt_vehiculos->fetchAll(PDO::FETCH_ASSOC);

$servicios_predefinidos = [
    'Cambio de aceite', 'Revisión general', 'Reparación de frenos', 'Cambio de neumáticos',
    'Alineación y balanceo', 'Reparación de motor', 'Mantenimiento preventivo',
    'Lavado y detailing', 'Reparación eléctrica', 'Otro servicio'
];

$mecanico_turno_actual = $turno['mecanico'] ?? '';
if ($mecanico_turno_actual === '') {
    $mecanico_turno_actual = 'Gerente de turno';
}
?>

<div class="container">
    <h2>Editar Turno</h2>
    
    <?php if ($mensaje): ?>
    <div class="alert"><?php echo $mensaje; ?></div>
    <?php endif; ?>
    
    <div class="form-container">
        <form method="POST">
            <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                <div style="flex: 1; min-width: 250px;">
                    <div class="form-group">
                        <label for="cliente_id">Cliente:</label>
                        <select id="cliente_id" name="cliente_id" required>
                            <?php foreach ($clientes as $cliente): ?>
                            <option value="<?php echo $cliente['id']; ?>" 
                                <?php echo ($cliente['id'] == $turno['cliente_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cliente['nombre']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="vehiculo_id">Vehículo:</label>
                        <select id="vehiculo_id" name="vehiculo_id" required>
                            <?php foreach ($vehiculos as $vehiculo): ?>
                            <option value="<?php echo $vehiculo['id']; ?>" 
                                <?php echo ($vehiculo['id'] == $turno['vehiculo_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($vehiculo['marca'] . ' ' . $vehiculo['modelo']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="mecanico">Mecánico:</label>
                        <select id="mecanico" name="mecanico" required>
                            <option value="">Seleccionar mecánico...</option>
                            <?php foreach ($mecanicos_disponibles as $mecanico_item): ?>
                            <option value="<?php echo htmlspecialchars($mecanico_item, ENT_QUOTES, 'UTF-8'); ?>"
                                <?php echo ($mecanico_turno_actual === $mecanico_item) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($mecanico_item, ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="servicio">Servicio:</label>
                        <select id="servicio" name="servicio" required>
                            <?php foreach ($servicios_predefinidos as $servicio): ?>
                            <option value="<?php echo $servicio; ?>" 
                                <?php echo ($servicio == $turno['servicio']) ? 'selected' : ''; ?>>
                                <?php echo $servicio; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div style="flex: 1; min-width: 250px;">
                    <div class="form-group">
                        <label for="fecha">Fecha:</label>
                        <input type="date" id="fecha" name="fecha" value="<?php echo $turno['fecha']; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="hora_inicio">Hora Inicio:</label>
                        <input type="time" id="hora_inicio" name="hora_inicio" value="<?php echo substr($turno['hora_inicio'], 0, 5); ?>" step="3600" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="hora_fin">Hora Fin:</label>
                        <input type="time" id="hora_fin" name="hora_fin" value="<?php echo substr($turno['hora_fin'], 0, 5); ?>" step="3600">
                    </div>
                    
                    <div class="form-group">
                        <label for="estado">Estado:</label>
                        <select id="estado" name="estado" required>
                            <option value="programado" <?php echo $turno['estado'] == 'programado' ? 'selected' : ''; ?>>Programado</option>
                            <option value="en_proceso" <?php echo $turno['estado'] == 'en_proceso' ? 'selected' : ''; ?>>En Proceso</option>
                            <option value="completado" <?php echo $turno['estado'] == 'completado' ? 'selected' : ''; ?>>Completado</option>
                            <option value="cancelado" <?php echo $turno['estado'] == 'cancelado' ? 'selected' : ''; ?>>Cancelado</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label for="descripcion">Descripción:</label>
                <textarea id="descripcion" name="descripcion" rows="3"><?php echo htmlspecialchars($turno['descripcion']); ?></textarea>
            </div>
            
            <button type="submit" class="btn btn-primary">Actualizar Turno</button>
            <a href="turnos.php" class="btn btn-secondary">Cancelar</a>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
