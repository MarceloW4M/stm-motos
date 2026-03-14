<?php
require_once 'includes/auth.php';
require_once 'includes/database.php';

// Verificar autenticación
requireAuth();

$database = new Database();
$db = $database->getConnection();

$mensaje = '';

require_once 'includes/header.php';

// enlace al CSS especializado
?>
<link rel="stylesheet" href="css/styleess.css">
<?php

// Obtener estadísticas para el dashboard
$query_estadisticas = "
    SELECT 
        (SELECT COUNT(*) FROM turnos WHERE estado = 'completado' AND fecha >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) as turnos_mes,
        (SELECT COUNT(*) FROM clientes) as total_clientes,
        (SELECT COUNT(*) FROM vehiculos) as total_vehiculos,
        (SELECT SUM(stock) FROM repuestos) as total_repuestos,
        (SELECT SUM(precio * stock) FROM repuestos) as valor_inventario,
        (SELECT COUNT(*) FROM turnos WHERE estado = 'programado') as turnos_pendientes
";
$stmt_estadisticas = $db->prepare($query_estadisticas);
$stmt_estadisticas->execute();
$estadisticas = $stmt_estadisticas->fetch(PDO::FETCH_ASSOC);

// Obtener últimos turnos completados
$query_ultimos_turnos = "
    SELECT t.*, c.nombre as cliente_nombre, v.marca, v.modelo
    FROM turnos t
    INNER JOIN clientes c ON t.cliente_id = c.id
    INNER JOIN vehiculos v ON t.vehiculo_id = v.id
    WHERE t.estado = 'completado'
    ORDER BY t.fecha DESC, t.hora_inicio DESC
    LIMIT 10
";
$stmt_ultimos_turnos = $db->prepare($query_ultimos_turnos);
$stmt_ultimos_turnos->execute();
$ultimos_turnos = $stmt_ultimos_turnos->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container">
    <h2>Informes y Estadísticas</h2>
    
    <?php if ($mensaje): ?>
    <div class="alert">
        <?php echo $mensaje; ?>
    </div>
    <?php endif; ?>
    
    <!-- Dashboard de estadísticas -->
    <div class="dashboard-estadisticas">
        <h3>Resumen General</h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 20px 0;">
            <div class="stat-card" style="background: #e3f2fd; padding: 15px; border-radius: 8px; text-align: center;">
                <h4>Turnos (30 días)</h4>
                <p style="font-size: 24px; font-weight: bold; color: #1976d2;"><?php echo $estadisticas['turnos_mes']; ?></p>
            </div>
            <div class="stat-card" style="background: #f3e5f5; padding: 15px; border-radius: 8px; text-align: center;">
                <h4>Clientes</h4>
                <p style="font-size: 24px; font-weight: bold; color: #7b1fa2;"><?php echo $estadisticas['total_clientes']; ?></p>
            </div>
            <div class="stat-card" style="background: #e8f5e9; padding: 15px; border-radius: 8px; text-align: center;">
                <h4>Vehículos</h4>
                <p style="font-size: 24px; font-weight: bold; color: #388e3c;"><?php echo $estadisticas['total_vehiculos']; ?></p>
            </div>
            <div class="stat-card" style="background: #fff3e0; padding: 15px; border-radius: 8px; text-align: center;">
                <h4>Turnos Pendientes</h4>
                <p style="font-size: 24px; font-weight: bold; color: #f57c00;"><?php echo $estadisticas['turnos_pendientes']; ?></p>
            </div>
        </div>
    </div>
    
    <!-- Últimos turnos completados -->
    <div class="ultimos-turnos" style="margin: 30px 0;">
        <h3>Últimos Turnos Completados</h3>
        <?php if (empty($ultimos_turnos)): ?>
            <p>No hay turnos completados recientemente.</p>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Cliente</th>
                        <th>Vehículo</th>
                        <th>Servicio</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ultimos_turnos as $turno): ?>
                    <tr>
                        <td><?php echo date('d/m/Y', strtotime($turno['fecha'])); ?></td>
                        <td><?php echo htmlspecialchars($turno['cliente_nombre']); ?></td>
                        <td><?php echo htmlspecialchars($turno['marca'] . ' ' . $turno['modelo']); ?></td>
                        <td><?php echo htmlspecialchars($turno['servicio']); ?></td>
                        <td>
                            <a href="generar_pdf.php?turno_id=<?php echo $turno['id']; ?>" class="btn btn-sm btn-primary" target="_blank">Ver PDF</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    
    <!-- Generar Informe de Turnos -->
    <div class="form-container" style="margin: 30px 0;">
        <h3>Informe de Turnos por Período</h3>
        <form method="GET" action="generar_informe.php" target="_blank">
            <div style="display: flex; gap: 15px; flex-wrap: wrap; margin-bottom: 15px;">
                <div class="form-group" style="flex: 1; min-width: 200px;">
                    <label for="fecha_inicio">Fecha Inicio:</label>
                    <input type="date" id="fecha_inicio" name="fecha_inicio" value="<?php echo date('Y-m-01'); ?>" required>
                </div>
                
                <div class="form-group" style="flex: 1; min-width: 200px;">
                    <label for="fecha_fin">Fecha Fin:</label>
                    <input type="date" id="fecha_fin" name="fecha_fin" value="<?php echo date('Y-m-t'); ?>" required>
                </div>
                
                <div class="form-group" style="flex: 1; min-width: 200px;">
                    <label for="tipo_informe">Tipo de Informe:</label>
                    <select id="tipo_informe" name="tipo" required>
                        <option value="diario">Diario</option>
                        <option value="semanal" selected>Semanal</option>
                        <option value="mensual">Mensual</option>
                        <option value="anual">Anual</option>
                        <option value="personalizado">Personalizado</option>
                    </select>
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary">
                Generar Informe de Turnos
            </button>
        </form>
    </div>
    
    <!-- Generar Informe de Inventario -->
    <div class="form-container" style="margin: 30px 0;">
        <h3>Informe de Inventario</h3>
        <form method="GET" action="generar_informe_inventario.php" target="_blank">
            <div class="form-group">
                <p>Genera un informe completo del inventario de repuestos con valores actuales.</p>
            </div>
            
            <button type="submit" class="btn btn-primary">
                Generar Informe de Inventario
            </button>
        </form>
    </div>
    
    <!-- Generar Informe Financiero -->
    <div class="form-container" style="margin: 30px 0;">
        <h3>Informe Financiero</h3>
        <form method="GET" action="generar_informe_financiero.php" target="_blank">
            <div style="display: flex; gap: 15px; flex-wrap: wrap; margin-bottom: 15px;">
                <div class="form-group" style="flex: 1; min-width: 200px;">
                    <label for="mes_financiero">Mes:</label>
                    <select id="mes_financiero" name="mes" required>
                        <option value="01" <?php echo date('m') == '01' ? 'selected' : ''; ?>>Enero</option>
                        <option value="02" <?php echo date('m') == '02' ? 'selected' : ''; ?>>Febrero</option>
                        <option value="03" <?php echo date('m') == '03' ? 'selected' : ''; ?>>Marzo</option>
                        <option value="04" <?php echo date('m') == '04' ? 'selected' : ''; ?>>Abril</option>
                        <option value="05" <?php echo date('m') == '05' ? 'selected' : ''; ?>>Mayo</option>
                        <option value="06" <?php echo date('m') == '06' ? 'selected' : ''; ?>>Junio</option>
                        <option value="07" <?php echo date('m') == '07' ? 'selected' : ''; ?>>Julio</option>
                        <option value="08" <?php echo date('m') == '08' ? 'selected' : ''; ?>>Agosto</option>
                        <option value="09" <?php echo date('m') == '09' ? 'selected' : ''; ?>>Septiembre</option>
                        <option value="10" <?php echo date('m') == '10' ? 'selected' : ''; ?>>Octubre</option>
                        <option value="11" <?php echo date('m') == '11' ? 'selected' : ''; ?>>Noviembre</option>
                        <option value="12" <?php echo date('m') == '12' ? 'selected' : ''; ?>>Diciembre</option>
                    </select>
                </div>
                
                <div class="form-group" style="flex: 1; min-width: 200px;">
                    <label for="anio_financiero">Año:</label>
                    <input type="number" id="anio_financiero" name="anio" 
                           value="<?php echo date('Y'); ?>" min="2020" max="2030" required>
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary">
                Generar Informe Financiero
            </button>
        </form>
    </div>
    
    <!-- Informes Rápidos -->
    <div class="informes-rapidos" style="margin: 30px 0;">
        <h3>Informes Rápidos</h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;">
            <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); text-align: center;">
                <h4>📅 Hoy</h4>
                <p>Turnos programados para hoy</p>
                <a href="generar_informe.php?tipo=hoy" class="btn btn-primary" target="_blank">Generar</a>
            </div>
            
            <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); text-align: center;">
                <h4>📋 Esta Semana</h4>
                <p>Actividad semanal completa</p>
                <a href="generar_informe.php?tipo=semanal" class="btn btn-primary" target="_blank">Generar</a>
            </div>
            
            <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); text-align: center;">
                <h4>Este Mes</h4>
                <p>Resumen mensual de actividad</p>
                <a href="generar_informe.php?tipo=mensual" class="btn btn-primary" target="_blank">Generar</a>
            </div>
            
            <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); text-align: center;">
                <h4>⚠️ Stock Bajo</h4>
                <p>Repuestos con stock menor a 5</p>
                <a href="generar_informe_inventario.php?tipo=stock_bajo" class="btn btn-warning" target="_blank">Generar</a>
            </div>
        </div>
    </div>
</div>

<style>
.stat-card {
    transition: transform 0.3s;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
}

.btn-warning {
    background-color: #ffc107;
    color: #212529;
    border: none;
}

.btn-warning:hover {
    background-color: #e0a800;
    color: #212529;
}
</style>

<?php include 'includes/footer.php'; ?>