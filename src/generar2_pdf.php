<?php
// 1. Activar output buffering al INICIO
ob_start();

// 2. Verificar si TCPDF existe SIN usar die() con HTML
if (!file_exists('tcpdf/tcpdf.php')) {
    // Limpiar buffer primero
    ob_end_clean();
    
    // Redireccionar a una página de error en lugar de die()
    header('Location: error.php?msg=' . urlencode('La librería TCPDF no está instalada'));
    exit();
}

// 3. Incluir archivos
require_once 'includes/auth.php';
require_once 'includes/database.php';

// 4. Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    ob_end_clean();
    header('Location: login.php');
    exit();
}

// 5. Verificar si hay turno_id
if (!isset($_GET['turno_id'])) {
    ob_end_clean();
    header('Location: turnos.php?error=no_turno_id');
    exit();
}

$turno_id = intval($_GET['turno_id']);
if ($turno_id <= 0) {
    ob_end_clean();
    header('Location: turnos.php?error=id_invalido');
    exit();
}

// 6. Obtener datos del turno
try {
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "SELECT t.*, c.nombre as cliente_nombre, c.telefono, c.email, 
                     v.marca, v.modelo, v.matricula, v.anio, o.numero_orden as numero_orden_reparacion
              FROM turnos t 
              INNER JOIN clientes c ON t.cliente_id = c.id 
              INNER JOIN vehiculos v ON t.vehiculo_id = v.id 
              LEFT JOIN  ordenes_reparacion o ON o.turno_id = t.id            
              WHERE t.id = :turno_id";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':turno_id', $turno_id);
    $stmt->execute();
    $turno = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$turno) {
        throw new Exception("Turno no encontrado");
    }
    
} catch (Exception $e) {
    ob_end_clean();
    header('Location: turnos.php?error=' . urlencode($e->getMessage()));
    exit();
}

// 7. AHORA sí, limpiar TODO el buffer de salida
while (ob_get_level()) {
    ob_end_clean();
}

// 8. Incluir TCPDF DESPUÉS de limpiar el buffer
require_once 'tcpdf/tcpdf.php';

// 9. Crear PDF
$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);

// Configuración del PDF
$pdf->SetCreator('STM - Aventura Motos');
$pdf->SetAuthor('STM - Aventura Motos');
$pdf->SetTitle('Informe de Servicio - ' . $turno['cliente_nombre']);
$pdf->SetSubject('Informe de Servicio');

// Configuración de márgenes
$pdf->SetMargins(15, 20, 15);
$pdf->SetHeaderMargin(10);
$pdf->SetFooterMargin(10);
$pdf->SetAutoPageBreak(TRUE, 15);

// Añadir página
$pdf->AddPage();

// Fuente para el logo
$pdf->Image('css/img/logo01.png', 10, 8, 33);
$pdf->SetFont('helvetica', 'B', 20);
$pdf->SetTextColor(211, 47, 47);
$pdf->Cell(0, 5, 'STM - Aventura Motos', 0, 1, 'C');
$pdf->SetFont('helvetica', '', 10);
$pdf->SetTextColor(0, 0, 0);

// Línea separadora
// $pdf->Line(15, 30, 195, 30);

// Título
$pdf->SetY(30);
$pdf->SetFont('helvetica', 'B', 16);
$pdf->Cell(0, 1, 'Informe de Servicio:  '. $turno['numero_orden_reparacion'], 0, 1, 'C');
$pdf->Ln(5);

// Información del cliente y vehículo
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 10, 'DATOS DEL CLIENTE Y VEHÍCULO', 0, 1);
$pdf->SetFont('helvetica', '', 10);

// Tabla de información
$html = '<table border="0" cellpadding="4" cellspacing="0" style="width: 100%">
    <tr>
        <td width="12%"><strong>Cliente:</strong></td>
        <td width="38%">' . htmlspecialchars($turno['cliente_nombre']) . '</td>
        <td width="12%"><strong>Teléfono:</strong></td>
        <td width="38%">' . htmlspecialchars($turno['telefono']) . '</td>
    </tr>
    <tr>
        <td><strong>Email:</strong></td>
        <td>' . htmlspecialchars($turno['email'] ?? 'N/A') . '</td>
        <td><strong>Vehículo:</strong></td>
        <td>' . htmlspecialchars($turno['marca'] . ' ' . $turno['modelo']) . '</td>
    </tr>
    <tr>
        <td><strong>Matrícula:</strong></td>
        <td>' . htmlspecialchars($turno['matricula']) . '</td>
        <td><strong>Año:</strong></td>
        <td>' . htmlspecialchars($turno['anio'] ?? 'N/A') . '</td>
    </tr>
</table>';

$pdf->writeHTML($html, true, false, true, false, '');
$pdf->Ln(2);

// Detalles del servicio
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 10, 'DETALLES DEL SERVICIO', 0, 1);
$pdf->SetFont('helvetica', '', 10);

$html = '<table border="0" cellpadding="4" cellspacing="0" style="width: 100%">
    <tr>
        <td width="13%"><strong>Fecha:</strong></td>
        <td width="37%">' . date('d/m/Y', strtotime($turno['fecha'])) . '</td>
        <td width="13%"><strong>Hora Inicio:</strong></td>
        <td width="37%">' . date('H:i', strtotime($turno['hora_inicio'])) . '</td>
    </tr>
    <tr>
        <td><strong>Hora Fin:</strong></td>
        <td>' . date('H:i', strtotime($turno['hora_fin'])) . '</td>
        <td><strong>Servicio:</strong></td>
        <td>' . htmlspecialchars($turno['servicio']) . '</td>
    </tr>
</table>';

$pdf->writeHTML($html, true, false, true, false, '');
$pdf->Ln(5);

// Descripción
if (!empty($turno['descripcion'])) {
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'DESCRIPCIÓN DEL TRABAJO', 0, 1);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->MultiCell(0, 8, htmlspecialchars($turno['descripcion']), 0, 'L');
    $pdf->Ln(5);
}

// Observaciones
if (!empty($turno['observaciones'])) {
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'OBSERVACIONES', 0, 1);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->MultiCell(0, 8, htmlspecialchars($turno['observaciones']), 0, 'L');
    $pdf->Ln(5);
}

// Firma del técnico
$pdf->SetY(-50);
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 10, 'Firma del Técnico: _________________________', 0, 1, 'R');
$pdf->Cell(0, 5, 'Nombre: ____________________________________', 0, 1, 'R');
$pdf->Cell(0, 5, 'Fecha: ' . date('d/m/Y'), 0, 1, 'R');

// Pie de página
$pdf->SetY(-15);
$pdf->SetFont('helvetica', 'I', 8);
$pdf->Cell(0, 10, 'Página ' . $pdf->getAliasNumPage() . ' de ' . $pdf->getAliasNbPages(), 0, 0, 'C');

// 10. FINALMENTE, enviar el PDF al navegador
$pdf->Output('informe_stm_' . $turno_id . '.pdf', 'I');
exit();