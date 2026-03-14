<?php
// Verificar si TCPDF existe
if (!file_exists('tcpdf/tcpdf.php')) {
    die("❌ Error: La librería TCPDF no está instalada. Ejecuta: wget https://github.com/tecnickcom/tcpdf/archive/refs/tags/6.6.2.tar.gz && tar -xzf 6.6.2.tar.gz && mv tcpdf-6.6.2 tcpdf");
}

// Incluir auth.php primero (ya inicia la sesión)
require_once 'includes/auth.php';
require_once 'includes/database.php';

// Verificar autenticación (la sesión ya está iniciada por auth.php)
if (!isset($_SESSION['user_id'])) {
    die("❌ No autenticado. <a href='login.php'>Iniciar sesión</a>");
}

// Incluir TCPDF
require_once 'tcpdf/tcpdf.php';

// Obtener datos del turno
if (isset($_GET['turno_id'])) {
    $turno_id = $_GET['turno_id'];
    
    $database = new Database();
    $db = $database->getConnection();
    
    // Consulta corregida para evitar errores
    $query = "SELECT t.*, c.nombre as cliente_nombre, c.telefono, c.email, 
                     v.marca, v.modelo, v.matricula, v.anio
              FROM turnos t 
              INNER JOIN clientes c ON t.cliente_id = c.id 
              INNER JOIN vehiculos v ON t.vehiculo_id = v.id 
              WHERE t.id = :turno_id";
    
    try {
        $stmt = $db->prepare($query);
        $stmt->bindParam(':turno_id', $turno_id);
        $stmt->execute();
        $turno = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$turno) {
            throw new Exception("Turno no encontrado con ID: $turno_id");
        }
        
    } catch (Exception $e) {
        die("❌ Error en la base de datos: " . $e->getMessage());
    }
    
    // Crear nuevo documento PDF
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    
    // Información del documento
    $pdf->SetCreator('STM Taller de Motos');
    $pdf->SetAuthor('STM Taller de Motos');
    $pdf->SetTitle('Informe de Servicio - ' . $turno['cliente_nombre']);
    $pdf->SetSubject('Informe de Servicio');
    
    // Configuración de márgenes
    $pdf->SetMargins(15, 25, 15);
    $pdf->SetHeaderMargin(10);
    $pdf->SetFooterMargin(10);
    $pdf->SetAutoPageBreak(TRUE, 15);
    
    // Fuente
    $pdf->SetFont('helvetica', '', 10);
    
    // Añadir página
    $pdf->AddPage();
    
    // Logo con texto (sin necesidad de archivo de imagen)
    $pdf->SetFont('helvetica', 'B', 20);
    $pdf->SetTextColor(211, 47, 47); // Color rojo STM
    $pdf->Cell(0, 10, 'STM TALLER DE MOTOS', 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->SetTextColor(0, 0, 0); // Volver a negro
    
    // Línea separadora
    $pdf->Line(15, 30, 195, 30);
    
    // Título
    $pdf->SetY(40);
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'INFORME DE SERVICIO', 0, 1, 'C');
    $pdf->Ln(5);
    
    // Información del cliente y vehículo
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'DATOS DEL CLIENTE Y VEHÍCULO', 0, 1);
    $pdf->SetFont('helvetica', '', 10);
    
    // Tabla de información
    $html = '<table border="0" cellpadding="4" cellspacing="0" style="width: 100%">
        <tr>
            <td width="25%"><strong>Cliente:</strong></td>
            <td width="25%">' . htmlspecialchars($turno['cliente_nombre']) . '</td>
            <td width="25%"><strong>Teléfono:</strong></td>
            <td width="25%">' . htmlspecialchars($turno['telefono']) . '</td>
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
    $pdf->Ln(8);
    
    // Detalles del servicio
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'DETALLES DEL SERVICIO', 0, 1);
    $pdf->SetFont('helvetica', '', 10);
    
    $html = '<table border="0" cellpadding="4" cellspacing="0" style="width: 100%">
        <tr>
            <td width="25%"><strong>Fecha:</strong></td>
            <td width="25%">' . date('d/m/Y', strtotime($turno['fecha'])) . '</td>
            <td width="25%"><strong>Hora Inicio:</strong></td>
            <td width="25%">' . date('H:i', strtotime($turno['hora_inicio'])) . '</td>
        </tr>
        <tr>
            <td><strong>Hora Fin:</strong></td>
            <td>' . date('H:i', strtotime($turno['hora_fin'])) . '</td>
            <td><strong>Servicio:</strong></td>
            <td>' . htmlspecialchars($turno['servicio']) . '</td>
        </tr>
    </table>';
    
    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->Ln(8);
    
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
    
    // Repuestos utilizados (si existe la tabla)
    try {
        $query_repuestos = "SELECT r.nombre, r.precio, tr.cantidad 
                           FROM turno_repuestos tr 
                           INNER JOIN repuestos r ON tr.repuesto_id = r.id 
                           WHERE tr.turno_id = :turno_id";
        $stmt_repuestos = $db->prepare($query_repuestos);
        $stmt_repuestos->bindParam(':turno_id', $turno_id);
        $stmt_repuestos->execute();
        $repuestos = $stmt_repuestos->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($repuestos)) {
            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->Cell(0, 10, 'REPUESTOS UTILIZADOS', 0, 1);
            $pdf->SetFont('helvetica', '', 10);
            
            $html = '<table border="1" cellpadding="5" cellspacing="0" style="width: 100%">
                <tr style="background-color: #f2f2f2;">
                    <th width="60%"><strong>Repuesto</strong></th>
                    <th width="15%"><strong>Cantidad</strong></th>
                    <th width="25%"><strong>Precio Total</strong></th>
                </tr>';
            
            $total_repuestos = 0;
            foreach ($repuestos as $repuesto) {
                $subtotal = $repuesto['precio'] * $repuesto['cantidad'];
                $total_repuestos += $subtotal;
                
                $html .= '<tr>
                    <td>' . htmlspecialchars($repuesto['nombre']) . '</td>
                    <td align="center">' . $repuesto['cantidad'] . '</td>
                    <td align="right">$' . number_format($subtotal, 2) . '</td>
                </tr>';
            }
            
            $html .= '<tr style="background-color: #e6e6e6;">
                <td colspan="2" align="right"><strong>Total Repuestos:</strong></td>
                <td align="right"><strong>$' . number_format($total_repuestos, 2) . '</strong></td>
            </tr></table>';
            
            $pdf->writeHTML($html, true, false, true, false, '');
            $pdf->Ln(8);
        }
    } catch (Exception $e) {
        // La tabla de repuestos puede no existir, no es crítico
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
    
    // Generar PDF
    $pdf->Output('informe_stm_' . $turno_id . '.pdf', 'I');
    
} else {
    // Mostrar página de error si no hay turno_id
    echo '<!DOCTYPE html>
    <html>
    <head>
        <title>Error - STM Taller</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 40px; background: #f4f4f4; }
            .error { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        </style>
    </head>
    <body>
        <div class="error">
            <h1>❌ Error al generar PDF</h1>
            <p>No se especificó un ID de turno válido.</p>
            <p>Usage: generar_pdf.php?turno_id=123</p>
            <a href="turnos.php">← Volver a Turnos</a>
        </div>
    </body>
    </html>';
}
