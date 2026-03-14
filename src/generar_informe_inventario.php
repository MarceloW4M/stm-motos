<?php
ob_start();

require_once 'includes/auth.php';
require_once 'includes/database.php';

// Verificar autenticación
requireAuth();

$database = new Database();
$db = $database->getConnection();

$tipo = $_GET['tipo'] ?? 'completo';

// Obtener datos de inventario
if ($tipo == 'stock_bajo') {
    $query = "SELECT * FROM repuestos WHERE stock < 5 ORDER BY stock ASC, nombre";
    $titulo = "INFORME DE STOCK BAJO";
} else {
    $query = "SELECT * FROM repuestos ORDER BY nombre";
    $titulo = "INFORME DE INVENTARIO COMPLETO";
}

$stmt = $db->prepare($query);
$stmt->execute();
$repuestos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcular totales
$total_repuestos = count($repuestos);
$total_stock = 0;
$valor_total = 0;
$stock_bajo = 0;

foreach ($repuestos as $repuesto) {
    $total_stock += $repuesto['stock'];
    $valor_total += $repuesto['precio'] * $repuesto['stock'];
    if ($repuesto['stock'] < 5) $stock_bajo++;
}

require_once 'tcpdf/tcpdf.php';

$pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetMargins(15, 25, 15);
$pdf->SetAutoPageBreak(TRUE, 15);
$pdf->AddPage();
$pdf->SetFont('helvetica', '', 10);

// Título
$pdf->SetFont('helvetica', 'B', 20);
$pdf->SetTextColor(211, 47, 47);
$pdf->Cell(0, 10, 'STM TALLER DE MOTOS', 0, 1, 'C');
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('helvetica', 'B', 16);
$pdf->Cell(0, 10, $titulo, 0, 1, 'C');
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 10, 'Fecha: ' . date('d/m/Y H:i'), 0, 1);
$pdf->Ln(5);

// Estadísticas
$html = '<table border="0" cellpadding="4" cellspacing="0" style="width: 100%">
    <tr>
        <td width="25%"><strong>Total repuestos:</strong></td>
        <td width="25%">' . $total_repuestos . '</td>
        <td width="25%"><strong>Stock total:</strong></td>
        <td width="25%">' . $total_stock . ' unidades</td>
    </tr>
    <tr>
        <td><strong>Stock bajo (<5):</strong></td>
        <td>' . $stock_bajo . ' (' . ($total_repuestos > 0 ? round(($stock_bajo/$total_repuestos)*100, 1) : 0) . '%)</td>
        <td><strong>Valor total inventario:</strong></td>
        <td>$' . number_format($valor_total, 2) . '</td>
    </tr>
</table>';

$pdf->writeHTML($html, true, false, true, false, '');
$pdf->Ln(8);

// Tabla de repuestos
if ($total_repuestos > 0) {
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, '📦 DETALLE DE REPUESTOS', 0, 1);
    $pdf->SetFont('helvetica', '', 9);
    
    $html = '<table border="1" cellpadding="4" cellspacing="0" style="width: 100%">
        <tr style="background-color: #f2f2f2;">
            <th width="5%"><strong>ID</strong></th>
            <th width="30%"><strong>Nombre</strong></th>
            <th width="30%"><strong>Descripción</strong></th>
            <th width="10%"><strong>Precio</strong></th>
            <th width="10%"><strong>Stock</strong></th>
            <th width="15%"><strong>Valor Total</strong></th>
        </tr>';
    
    foreach ($repuestos as $repuesto) {
        $stock_color = '';
        if ($repuesto['stock'] == 0) {
            $stock_color = '#f8d7da'; // Rojo - agotado
        } elseif ($repuesto['stock'] < 5) {
            $stock_color = '#fff3cd'; // Amarillo - bajo
        } else {
            $stock_color = '#d4edda'; // Verde - normal
        }
        
        $valor_total_item = $repuesto['precio'] * $repuesto['stock'];
        
        $html .= '<tr style="background-color: ' . $stock_color . ';">
            <td>' . $repuesto['id'] . '</td>
            <td>' . htmlspecialchars($repuesto['nombre']) . '</td>
            <td>' . htmlspecialchars(substr($repuesto['descripcion'] ?? '', 0, 50)) . '...</td>
            <td align="right">$' . number_format($repuesto['precio'], 2) . '</td>
            <td align="center">' . $repuesto['stock'] . '</td>
            <td align="right">$' . number_format($valor_total_item, 2) . '</td>
        </tr>';
    }
    
    $html .= '<tr style="background-color: #e6e6e6; font-weight: bold;">
        <td colspan="4" align="right">TOTALES:</td>
        <td align="center">' . $total_stock . '</td>
        <td align="right">$' . number_format($valor_total, 2) . '</td>
    </tr></table>';
    
    $pdf->writeHTML($html, true, false, true, false, '');
} else {
    $pdf->Cell(0, 10, 'No hay repuestos en el inventario.', 0, 1);
}

// Pie de página
$pdf->SetY(-15);
$pdf->SetFont('helvetica', 'I', 8);
$pdf->Cell(0, 10, 'Página ' . $pdf->getAliasNumPage() . ' de ' . $pdf->getAliasNbPages(), 0, 0, 'C');

if (ob_get_length()) {
    ob_end_clean();
}

$pdf->Output('informe_inventario_' . date('Ymd_His') . '.pdf', 'I');
exit();