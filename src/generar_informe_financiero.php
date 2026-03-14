<?php
ob_start();

require_once 'includes/auth.php';
require_once 'includes/database.php';

requireAuth();

$database = new Database();
$db = $database->getConnection();

$mes = filter_input(INPUT_GET, 'mes', FILTER_VALIDATE_INT);
$anio = filter_input(INPUT_GET, 'anio', FILTER_VALIDATE_INT);

$mes = ($mes && $mes >= 1 && $mes <= 12) ? $mes : (int) date('m');
$anio = ($anio && $anio >= 2020 && $anio <= 2100) ? $anio : (int) date('Y');

$fecha_inicio = sprintf('%04d-%02d-01', $anio, $mes);
$fecha_fin = date('Y-m-t', strtotime($fecha_inicio));

$meses = [
    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
    5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
    9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
];

$titulo = 'INFORME FINANCIERO - ' . $meses[$mes] . ' ' . $anio;

$total_turnos = 0;
$turnos_completados = 0;
$turnos_cancelados = 0;
$ingresos_total = 0.0;
$ticket_promedio = 0.0;
$fuente_ingresos = 'No disponible';

// Metricas base de turnos del periodo
$query_turnos = "
    SELECT
        COUNT(*) AS total_turnos,
        SUM(CASE WHEN estado = 'completado' THEN 1 ELSE 0 END) AS turnos_completados,
        SUM(CASE WHEN estado = 'cancelado' THEN 1 ELSE 0 END) AS turnos_cancelados
    FROM turnos
    WHERE fecha BETWEEN :fecha_inicio AND :fecha_fin
";
$stmt_turnos = $db->prepare($query_turnos);
$stmt_turnos->bindParam(':fecha_inicio', $fecha_inicio);
$stmt_turnos->bindParam(':fecha_fin', $fecha_fin);
$stmt_turnos->execute();
$data_turnos = $stmt_turnos->fetch(PDO::FETCH_ASSOC) ?: [];

$total_turnos = (int) ($data_turnos['total_turnos'] ?? 0);
$turnos_completados = (int) ($data_turnos['turnos_completados'] ?? 0);
$turnos_cancelados = (int) ($data_turnos['turnos_cancelados'] ?? 0);

// Intentar calcular ingresos desde ordenes_reparacion si existe
$query_tabla_ordenes = "
    SELECT COUNT(*)
    FROM information_schema.tables
    WHERE table_schema = DATABASE() AND table_name = 'ordenes_reparacion'
";
$ordenes_existe = (int) $db->query($query_tabla_ordenes)->fetchColumn() > 0;

if ($ordenes_existe) {
    $query_ingresos = "
        SELECT
            COALESCE(SUM(costo_total), 0) AS ingresos_total,
            COUNT(*) AS total_ordenes
        FROM ordenes_reparacion
        WHERE estado IN ('completada', 'facturada')
          AND fecha_creacion BETWEEN :fecha_inicio AND :fecha_fin
    ";

    $stmt_ingresos = $db->prepare($query_ingresos);
    $stmt_ingresos->bindParam(':fecha_inicio', $fecha_inicio);
    $stmt_ingresos->bindParam(':fecha_fin', $fecha_fin);
    $stmt_ingresos->execute();
    $data_ingresos = $stmt_ingresos->fetch(PDO::FETCH_ASSOC) ?: [];

    $ingresos_total = (float) ($data_ingresos['ingresos_total'] ?? 0);
    $total_ordenes = (int) ($data_ingresos['total_ordenes'] ?? 0);
    $ticket_promedio = $total_ordenes > 0 ? ($ingresos_total / $total_ordenes) : 0.0;
    $fuente_ingresos = 'Ordenes de reparacion';
}

// Ranking de servicios del periodo
$query_servicios = "
    SELECT servicio, COUNT(*) AS cantidad
    FROM turnos
    WHERE fecha BETWEEN :fecha_inicio AND :fecha_fin
    GROUP BY servicio
    ORDER BY cantidad DESC, servicio ASC
    LIMIT 10
";
$stmt_servicios = $db->prepare($query_servicios);
$stmt_servicios->bindParam(':fecha_inicio', $fecha_inicio);
$stmt_servicios->bindParam(':fecha_fin', $fecha_fin);
$stmt_servicios->execute();
$servicios = $stmt_servicios->fetchAll(PDO::FETCH_ASSOC);

require_once 'tcpdf/tcpdf.php';

$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetMargins(15, 20, 15);
$pdf->SetAutoPageBreak(true, 15);
$pdf->AddPage();
$pdf->SetFont('helvetica', '', 10);

$pdf->SetFont('helvetica', 'B', 18);
$pdf->Cell(0, 10, 'STM TALLER DE MOTOS', 0, 1, 'C');
$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, 8, $titulo, 0, 1, 'C');
$pdf->Ln(3);

$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 6, 'Periodo: ' . date('d/m/Y', strtotime($fecha_inicio)) . ' al ' . date('d/m/Y', strtotime($fecha_fin)), 0, 1);
$pdf->Cell(0, 6, 'Generado: ' . date('d/m/Y H:i'), 0, 1);
$pdf->Ln(3);

$resumen_html = '<table border="1" cellpadding="5" cellspacing="0" style="width:100%;">
    <tr style="background-color:#f2f2f2;">
        <th width="40%"><strong>Indicador</strong></th>
        <th width="60%"><strong>Valor</strong></th>
    </tr>
    <tr>
        <td>Total de turnos del periodo</td>
        <td>' . $total_turnos . '</td>
    </tr>
    <tr>
        <td>Turnos completados</td>
        <td>' . $turnos_completados . '</td>
    </tr>
    <tr>
        <td>Turnos cancelados</td>
        <td>' . $turnos_cancelados . '</td>
    </tr>
    <tr>
        <td>Ingresos del periodo</td>
        <td>$' . number_format($ingresos_total, 2) . '</td>
    </tr>
    <tr>
        <td>Ticket promedio</td>
        <td>$' . number_format($ticket_promedio, 2) . '</td>
    </tr>
    <tr>
        <td>Fuente de ingresos</td>
        <td>' . htmlspecialchars($fuente_ingresos) . '</td>
    </tr>
</table>';

$pdf->writeHTML($resumen_html, true, false, true, false, '');
$pdf->Ln(5);

$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 8, 'Servicios mas realizados', 0, 1);
$pdf->SetFont('helvetica', '', 10);

if (!empty($servicios)) {
    $servicios_html = '<table border="1" cellpadding="5" cellspacing="0" style="width:100%;">
        <tr style="background-color:#f2f2f2;">
            <th width="70%"><strong>Servicio</strong></th>
            <th width="30%"><strong>Cantidad</strong></th>
        </tr>';

    foreach ($servicios as $fila) {
        $servicios_html .= '<tr>
            <td>' . htmlspecialchars($fila['servicio'] ?? 'Sin nombre') . '</td>
            <td align="center">' . (int) $fila['cantidad'] . '</td>
        </tr>';
    }

    $servicios_html .= '</table>';
    $pdf->writeHTML($servicios_html, true, false, true, false, '');
} else {
    $pdf->Cell(0, 6, 'No hay servicios registrados en el periodo seleccionado.', 0, 1);
}

if (!$ordenes_existe) {
    $pdf->Ln(4);
    $pdf->SetFont('helvetica', 'I', 9);
    $pdf->MultiCell(0, 5, 'Nota: no se encontro la tabla ordenes_reparacion. Los ingresos se muestran en $0.00 hasta disponer de esa fuente.', 0, 'L');
}

if (ob_get_length()) {
    ob_end_clean();
}

$pdf->Output('informe_financiero_' . $anio . '_' . str_pad((string) $mes, 2, '0', STR_PAD_LEFT) . '.pdf', 'I');
exit();
