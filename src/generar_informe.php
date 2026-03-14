<?php
require_once 'includes/auth.php';
require_once 'includes/database.php';

// Verificar autenticación
requireAuth();

$database = new Database();
$db = $database->getConnection();

// Obtener parámetros
$tipo = $_GET['tipo'] ?? 'semanal';
$fecha_inicio = $_GET['fecha_inicio'] ?? '';
$fecha_fin = $_GET['fecha_fin'] ?? '';

// Calcular fechas según el tipo
switch ($tipo) {
    case 'hoy':
        $fecha_inicio = date('Y-m-d');
        $fecha_fin = date('Y-m-d');
        $titulo = "Informe Diario - " . date('d/m/Y');
        break;
        
    case 'semanal':
        $fecha_inicio = date('Y-m-d', strtotime('monday this week'));
        $fecha_fin = date('Y-m-d', strtotime('sunday this week'));
        $titulo = "Informe Semanal " . date('d/m', strtotime($fecha_inicio)) . " al " . date('d/m/Y', strtotime($fecha_fin));
        break;
        
    case 'mensual':
        $fecha_inicio = date('Y-m-01');
        $fecha_fin = date('Y-m-t');
        $titulo = "Informe Mensual - " . date('F Y');
        break;
        
    case 'anual':
        $fecha_inicio = date('Y-01-01');
        $fecha_fin = date('Y-12-31');
        $titulo = "Informe Anual - " . date('Y');
        break;
        
    case 'personalizado':
        if (empty($fecha_inicio) || empty($fecha_fin)) {
            die("Error: Debe especificar fecha inicio y fin para informe personalizado");
        }
        $titulo = "Informe Personalizado " . date('d/m/Y', strtotime($fecha_inicio)) . " al " . date('d/m/Y', strtotime($fecha_fin));
        break;
        
    default:
        $fecha_inicio = date('Y-m-d', strtotime('-7 days'));
        $fecha_fin = date('Y-m-d');
        $titulo = "Informe de los últimos 7 días";
}

// Obtener datos de turnos
$query = "
    SELECT t.*, c.nombre as cliente_nombre, c.telefono,
           v.marca, v.modelo, v.matricula,
           s.nombre as servicio_nombre, s.precio_estimado
    FROM turnos t
    LEFT JOIN clientes c ON t.cliente_id = c.id
    LEFT JOIN vehiculos v ON t.vehiculo_id = v.id
    LEFT JOIN servicios s ON t.servicio_id = s.id
    WHERE t.fecha BETWEEN :fecha_inicio AND :fecha_fin
    ORDER BY t.fecha, t.hora_inicio
";

$stmt = $db->prepare($query);
$stmt->bindParam(':fecha_inicio', $fecha_inicio);
$stmt->bindParam(':fecha_fin', $fecha_fin);
$stmt->execute();
$turnos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcular estadísticas
$total_turnos = count($turnos);
$turnos_completados = 0;
$turnos_programados = 0;
$turnos_cancelados = 0;
$ingresos_estimados = 0;

foreach ($turnos as $turno) {
    switch ($turno['estado']) {
        case 'completado': $turnos_completados++; break;
        case 'programado': $turnos_programados++; break;
        case 'cancelado': $turnos_cancelados++; break;
    }
    $ingresos_estimados += $turno['precio_estimado'] ?? 0;
}

// Incluir TCPDF
require_once 'tcpdf/tcpdf.php';

// Crear PDF
$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetMargins(15, 25, 15);
$pdf->SetAutoPageBreak(TRUE, 15);
$pdf->AddPage();
$pdf->SetFont('helvetica', '', 10);

// Logo y título
$pdf->SetFont('helvetica', 'B', 20);
$pdf->SetTextColor(211, 47, 47);
$pdf->Cell(0, 10, 'STM TALLER DE MOTOS', 0, 1, 'C');
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('helvetica', 'B', 16);
$pdf->Cell(0, 10, $titulo, 0, 1, 'C');
$pdf->Ln(5);

// Estadísticas
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 10, '📊 RESUMEN ESTADÍSTICO', 0, 1);
$pdf->SetFont('helvetica', '', 10);

$html = '<table border="0" cellpadding="4" cellspacing="0" style="width: 100%">
    <tr>
        <td width="33%"><strong>Período:</strong></td>
        <td width="33%">' . date('d/m/Y', strtotime($fecha_inicio)) . ' - ' . date('d/m/Y', strtotime($fecha_fin)) . '</td>
        <td width="34%"><strong>Fecha generación:</strong></td>
        <td>' . date('d/m/Y H:i') . '</td>
    </tr>
    <tr>
        <td><strong>Total turnos:</strong></td>
        <td>' . $total_turnos . '</td>
        <td><strong>Completados:</strong></td>
        <td>' . $turnos_completados . ' (' . ($total_turnos > 0 ? round(($turnos_completados/$total_turnos)*100, 1) : 0) . '%)</td>
    </tr>
    <tr>
        <td><strong>Programados:</strong></td>
        <td>' . $turnos_programados . '</td>
        <td><strong>Cancelados:</strong></td>
        <td>' . $turnos_cancelados . '</td>
    </tr>
    <tr>
        <td><strong>Ingresos estimados:</strong></td>
        <td colspan="3">$' . number_format($ingresos_estimados, 2) . '</td>
    </tr>
</table>';

$pdf->writeHTML($html, true, false, true, false, '');
$pdf->Ln(8);

// Detalle de turnos
if ($total_turnos > 0) {
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, '📋 DETALLE DE TURNOS', 0, 1);
    $pdf->SetFont('helvetica', '', 9);
    
    $html = '<table border="1" cellpadding="4" cellspacing="0" style="width: 100%">
        <tr style="background-color: #f2f2f2;">
            <th width="10%"><strong>Fecha</strong></th>
            <th width="15%"><strong>Hora</strong></th>
            <th width="25%"><strong>Cliente</strong></th>
            <th width="20%"><strong>Vehículo</strong></th>
            <th width="15%"><strong>Servicio</strong></th>
            <th width="15%"><strong>Estado</strong></th>
        </tr>';
    
    foreach ($turnos as $turno) {
        $estado_color = '';
        switch ($turno['estado']) {
            case 'completado': $estado_color = '#d4edda'; break;
            case 'programado': $estado_color = '#fff3cd'; break;
            case 'cancelado': $estado_color = '#f8d7da'; break;
            default: $estado_color = '#f8f9fa';
        }
        
        $html .= '<tr style="background-color: ' . $estado_color . ';">
            <td>' . date('d/m/Y', strtotime($turno['fecha'])) . '</td>
            <td>' . date('H:i', strtotime($turno['hora_inicio'])) . '</td>
            <td>' . htmlspecialchars($turno['cliente_nombre']) . '</td>
            <td>' . htmlspecialchars($turno['marca'] . ' ' . $turno['modelo']) . '</td>
            <td>' . htmlspecialchars($turno['servicio_nombre'] ?? $turno['servicio']) . '</td>
            <td>' . ucfirst($turno['estado']) . '</td>
        </tr>';
    }
    
    $html .= '</table>';
    
    $pdf->writeHTML($html, true, false, true, false, '');
} else {
    $pdf->Cell(0, 10, 'No hay turnos registrados en este período.', 0, 1);
}

// Pie de página
$pdf->SetY(-15);
$pdf->SetFont('helvetica', 'I', 8);
$pdf->Cell(0, 10, 'Página ' . $pdf->getAliasNumPage() . ' de ' . $pdf->getAliasNbPages(), 0, 0, 'C');

// Generar PDF
$pdf->Output('informe_turnos_' . date('Ymd_His') . '.pdf', 'I');
?>