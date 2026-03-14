<?php
require_once 'includes/auth.php';
require_once 'includes/database.php';
require_once 'includes/header.php';

// Verificar autenticación
requireAuth();

// Obtener la fecha seleccionada o usar la fecha actual
$fecha_seleccionada = isset($_GET['fecha']) ? $_GET['fecha'] : date('Y-m-d');

// Validar formato de fecha
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_seleccionada)) {
    $fecha_seleccionada = date('Y-m-d');
}

// Obtener tareas del día seleccionado
$database = new Database();
$db = $database->getConnection();

$query = "SELECT t.*, c.nombre as cliente_nombre, v.marca, v.modelo 
          FROM turnos t 
          INNER JOIN clientes c ON t.cliente_id = c.id 
          INNER JOIN vehiculos v ON t.vehiculo_id = v.id 
          WHERE t.fecha = :fecha 
          ORDER BY t.hora_inicio";

$stmt = $db->prepare($query);
$stmt->bindParam(':fecha', $fecha_seleccionada);
$stmt->execute();
$turnos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Agrupar turnos por hora
$agenda = [];
foreach ($turnos as $turno) {
    $hora = date('H:i', strtotime($turno['hora_inicio']));
    if (!isset($agenda[$hora])) {
        $agenda[$hora] = [];
    }
    $agenda[$hora][] = $turno;
}

// Calcular fechas para navegación
$fecha_anterior = date('Y-m-d', strtotime($fecha_seleccionada . ' -1 day'));
$fecha_siguiente = date('Y-m-d', strtotime($fecha_seleccionada . ' +1 day'));
?>

<div class="container">
    <h2>Agenda de Turnos</h2>
    
<!-- Selector de fecha compacto -->
<div class="date-selector-compact">
    <div class="date-header">
        <!-- Fecha actual -->
        <div class="current-date-compact">
            <?php 
            $fecha_formateada = date('d/m/Y', strtotime($fecha_seleccionada));
            $dia_semana = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
            $dia_index = date('w', strtotime($fecha_seleccionada));
            echo '<div class="week-day">' . $dia_semana[$dia_index] . '</div>';
            echo '<div class="date">' . $fecha_formateada . '</div>';
            ?>
        </div>

        <!-- Selector de calendario inline -->
        <div class="date-picker-compact">
        <input type="date" 
               id="fecha-calendario" 
               name="fecha" 
               value="<?php echo $fecha_seleccionada; ?>"
               class="compact-date-input"
               onchange="cambiarFecha()"
               title="Seleccionar fecha">
        </div>
        
        <!-- Navegación compacta -->
        <div class="nav-compact">
            <a href="?fecha=<?php echo $fecha_anterior; ?>" class="nav-arrow prev" title="Día anterior">
                <span>◀</span>
            </a>
            <a href="?" class="btn-today-compact" title="Ir a hoy">
                Hoy
            </a>
            <a href="?fecha=<?php echo $fecha_siguiente; ?>" class="nav-arrow next" title="Día siguiente">
                <span>▶</span>
            </a>
        </div>
    </div>    
    
    <!-- Estadísticas minimalistas -->
    <div class="compact-stats">
        <div class="stat-item">
            <span class="stat-label">Turnos:</span>
            <span class="stat-value"><?php echo count($turnos); ?></span>
        </div>
        <div class="stat-divider">•</div>
        <div class="stat-item">
            <span class="stat-label">Ocupadas:</span>
            <span class="stat-value"><?php echo count($agenda); ?>h</span>
        </div>
        <div class="stat-divider">•</div>
        <div class="stat-item">
            <span class="stat-label">Libres:</span>
            <span class="stat-value"><?php echo 13 - count($agenda); ?>h</span>
        </div>
    </div>
</div>

    
    <!-- Agenda del día -->
    <div class="agenda">
        <h3 style="margin-bottom: 20px; color: #333;">
            <?php 
            if ($fecha_seleccionada == date('Y-m-d')) {
                echo "📌 Turnos de Hoy";
            } else {
                echo "📋 Turnos del " . $fecha_formateada;
            }
            ?>
        </h3>
        
        <?php
        // Generar slots de tiempo desde las 8:00 hasta las 20:00
        $start = strtotime('08:00');
        $end = strtotime('20:00');
        
        for ($time = $start; $time <= $end; $time = strtotime('+60 minutes', $time)) {
            $hora = date('H:i', $time);
            $turnos_hora = $agenda[$hora] ?? [];
            ?>
            <div class="time-slot">
                <h3><?php echo $hora; ?></h3>
                
                <?php if (empty($turnos_hora)): ?>
                    <p style="color: #999; font-style: italic;">✓ Disponible</p>
                <?php else: ?>
                    <?php foreach ($turnos_hora as $turno): ?>
                        <div class="appointment">
                            <p><strong>👤 Cliente:</strong> <?php echo htmlspecialchars($turno['cliente_nombre']); ?></p>
                            <p><strong>🚗 Vehículo:</strong> <?php echo htmlspecialchars($turno['marca'] . ' ' . $turno['modelo']); ?></p>
                            <p><strong>🔧 Servicio:</strong> <?php echo htmlspecialchars($turno['servicio']); ?></p>
                            <?php if (isset($turno['notas']) && !empty($turno['notas'])): ?>
                                <p><strong>📝 Notas:</strong> <?php echo htmlspecialchars($turno['notas']); ?></p>
                            <?php endif; ?>
                            <a href="generar_pdf.php?turno_id=<?php echo $turno['id']; ?>" class="btn-secondary">📄 Generar Informe PDF</a>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <?php
        }
        ?>
    </div>
</div>

<script>
function cambiarFecha() {
    const fechaInput = document.getElementById('fecha-calendario');
    const fecha = fechaInput.value;
    
    if (fecha) {
        window.location.href = '?fecha=' + fecha;
    }
}

// Atajos de teclado
document.addEventListener('keydown', function(e) {
    // Flecha izquierda: día anterior
    if (e.key === 'ArrowLeft' && !e.target.matches('input, textarea')) {
        window.location.href = '?fecha=<?php echo $fecha_anterior; ?>';
    }
    
    // Flecha derecha: día siguiente
    if (e.key === 'ArrowRight' && !e.target.matches('input, textarea')) {
        window.location.href = '?fecha=<?php echo $fecha_siguiente; ?>';
    }
    
    // Tecla H: ir a hoy
    if (e.key === 'h' && !e.target.matches('input, textarea')) {
        window.location.href = '?';
    }
});
</script>

<?php include 'includes/footer.php'; ?>