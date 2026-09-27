<?php
ob_start();

if (!file_exists('tcpdf/tcpdf.php')) {
    ob_end_clean();
    header('Location: facturacion.php?mensaje=' . urlencode('La librería TCPDF no está instalada') . '&tipo=error');
    exit();
}

require_once 'includes/auth.php';
require_once 'includes/database.php';
require_once 'includes/administracion_schema.php';

requireAuth();

$facturaId = isset($_GET['factura_id']) ? (int) $_GET['factura_id'] : 0;
if ($facturaId <= 0) {
    ob_end_clean();
    header('Location: facturacion.php?mensaje=' . urlencode('Factura inválida') . '&tipo=error');
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();
    ensureAdministracionSchema($db);

    $stmtFactura = $db->prepare("SELECT f.*, tf.nombre AS tipo_factura_nombre, tf.letra AS tipo_factura_letra,
                                        o.numero_orden, o.fecha_creacion AS orden_fecha,
                                        t.id AS turno_id, t.fecha AS turno_fecha,
                                        c.nombre AS cliente_nombre, c.telefono AS cliente_telefono, c.email AS cliente_email,
                                        c.direccion AS cliente_direccion, c.cuit AS cliente_cuit,
                                        v.marca, v.modelo, v.matricula, v.anio, v.vin
                                 FROM admin_facturas f
                                 INNER JOIN admin_tipos_factura tf ON tf.id = f.tipo_factura_id
                                 INNER JOIN ordenes_reparacion o ON o.id = f.orden_id
                                 INNER JOIN turnos t ON t.id = o.turno_id
                                 INNER JOIN clientes c ON c.id = t.cliente_id
                                 INNER JOIN vehiculos v ON v.id = t.vehiculo_id
                                 WHERE f.id = :factura_id
                                 LIMIT 1");
    $stmtFactura->bindParam(':factura_id', $facturaId, PDO::PARAM_INT);
    $stmtFactura->execute();
    $factura = $stmtFactura->fetch(PDO::FETCH_ASSOC);

    if (!$factura) {
        throw new RuntimeException('Factura no encontrada.');
    }

    $empresaRazonSocial = getAdminConfig($db, 'empresa_razon_social', 'Sur Bateria');
    $empresaCuit = getAdminConfig($db, 'empresa_cuit', '');
    $empresaDireccion = '9 de Julio 686, U9100 Trelew, Chubut, Argentina';

    $stmtRepuestos = $db->prepare("SELECT r.nombre, or2.cantidad, or2.precio_unitario,
                                          (or2.cantidad * or2.precio_unitario) AS subtotal
                                   FROM orden_repuestos or2
                                   INNER JOIN repuestos r ON r.id = or2.repuesto_id
                                   WHERE or2.orden_id = :orden_id
                                   ORDER BY r.nombre");
    $stmtRepuestos->bindParam(':orden_id', $factura['orden_id'], PDO::PARAM_INT);
    $stmtRepuestos->execute();
    $repuestos = $stmtRepuestos->fetchAll(PDO::FETCH_ASSOC);

    $stmtTareas = $db->prepare("SELECT descripcion, tiempo_horas, costo_hora,
                                       (tiempo_horas * costo_hora) AS subtotal
                                FROM orden_tareas
                                WHERE orden_id = :orden_id
                                ORDER BY created_at, id");
    $stmtTareas->bindParam(':orden_id', $factura['orden_id'], PDO::PARAM_INT);
    $stmtTareas->execute();
    $tareas = $stmtTareas->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Location: facturacion.php?mensaje=' . urlencode($e->getMessage()) . '&tipo=error');
    exit();
}

while (ob_get_level()) {
    ob_end_clean();
}

require_once 'tcpdf/tcpdf.php';

class FacturaComercialPDF extends TCPDF
{
    private array $factura;
    private array $repuestos;
    private array $tareas;
    private string $empresaRazonSocial;
    private string $empresaCuit;
    private string $empresaDireccion;

    public function __construct(array $factura, array $repuestos, array $tareas, string $empresaRazonSocial, string $empresaCuit, string $empresaDireccion)
    {
        parent::__construct('P', 'mm', 'A4', true, 'UTF-8', false);
        $this->factura = $factura;
        $this->repuestos = $repuestos;
        $this->tareas = $tareas;
        $this->empresaRazonSocial = $empresaRazonSocial;
        $this->empresaCuit = $empresaCuit;
        $this->empresaDireccion = $empresaDireccion;

        $this->SetCreator('Sur Bateria');
        $this->SetAuthor('Sur Bateria');
        $this->SetTitle('Factura ' . $this->factura['numero_factura']);
        $this->SetSubject('Factura comercial');
        $this->setPrintHeader(false);
        $this->setPrintFooter(false);
        $this->SetMargins(12, 12, 12);
        $this->SetAutoPageBreak(false);
    }

    public function render(): void
    {
        $this->AddPage();
        $this->drawHeader();
        $this->drawParties();
        $this->drawItems();
        $this->drawNotes();
        $this->drawTotals();
    }

    private function drawHeader(): void
    {
        $this->SetFillColor(255, 255, 255);
        $this->Rect(0, 0, 210, 297, 'F');

        if (file_exists('css/img/logo01.png')) {
            $this->Image('css/img/logo01.png', 2, 2, 50.4);
        }

        $this->SetXY(60, 14);
        $this->SetFont('helvetica', 'B', 22);
        $this->SetTextColor(25, 25, 25);
        $this->Cell(90, 10, 'FACTURA ' . strtoupper((string) $this->factura['tipo_factura_letra']), 0, 0, 'C');

        $this->SetXY(152, 14);
        $this->SetFont('helvetica', 'B', 12);
        $this->Cell(46, 6, 'N° ' . $this->factura['numero_factura'], 0, 1, 'R');

        $this->SetXY(152, 22);
        $this->SetFont('helvetica', '', 10);
        $fechaEmision = !empty($this->factura['fecha_emision']) ? date('d/m/Y', strtotime($this->factura['fecha_emision'])) : date('d/m/Y');
        $this->Cell(46, 5, 'Fecha: ' . $fechaEmision, 0, 1, 'R');

        $this->SetXY(60, 26);
        $this->SetFont('helvetica', 'B', 12);
        $this->Cell(90, 6, $this->empresaRazonSocial, 0, 1, 'C');

        $this->SetXY(60, 33);
        $this->SetFont('helvetica', '', 9);
        $this->MultiCell(90, 8, $this->empresaDireccion, 0, 'C', false, 1);

        if ($this->empresaCuit !== '') {
            $this->SetXY(60, 41);
            $this->Cell(90, 5, 'CUIT: ' . $this->empresaCuit, 0, 1, 'C');
        }

        $this->SetDrawColor(210, 210, 210);
        $this->Line(12, 52, 198, 52);
    }

    private function drawParties(): void
    {
        $clienteHtml = '<table cellpadding="3" cellspacing="0" border="1" style="font-size: 9pt;">
            <tr style="background-color: #f4f4f4; font-weight: bold;">
                <td width="100%">Datos de Cliente</td>
            </tr>
            <tr>
                <td width="34%"><b>Nombre:</b> ' . htmlspecialchars((string) $this->factura['cliente_nombre'], ENT_QUOTES, 'UTF-8') . '</td>
                <td width="33%"><b>DNI / CUIT:</b> ' . htmlspecialchars((string) ($this->factura['cliente_cuit'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>
                <td width="33%"><b>Teléfono:</b> ' . htmlspecialchars((string) ($this->factura['cliente_telefono'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>
            </tr>
            <tr>
                <td width="50%"><b>Email:</b> ' . htmlspecialchars((string) ($this->factura['cliente_email'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>
                <td width="50%"><b>Dirección:</b> ' . htmlspecialchars((string) ($this->factura['cliente_direccion'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>
            </tr>
        </table>';

        $vehiculoHtml = '<table cellpadding="3" cellspacing="0" border="1" style="font-size: 9pt;">
            <tr style="background-color: #f4f4f4; font-weight: bold;">
                <td width="50%">Datos de Vehículo</td>
                <td width="20%"><b>Número de Orden</b></td>
                <td width="30%">' . htmlspecialchars((string) ($this->factura['numero_orden'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>
            </tr>
            <tr>
                <td width="50%"><b>Marca / Modelo:</b> ' . htmlspecialchars(trim((string) $this->factura['marca'] . ' ' . (string) $this->factura['modelo']), ENT_QUOTES, 'UTF-8') . '</td>
                <td width="50%"><b>Matrícula:</b> ' . htmlspecialchars((string) ($this->factura['matricula'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>
            </tr>
            <tr>
                <td width="50%"><b>Año:</b> ' . htmlspecialchars((string) ($this->factura['anio'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>
                <td width="50%"><b>VIN:</b> ' . htmlspecialchars((string) ($this->factura['vin'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>
            </tr>
        </table>';

        $currentY = 58;
        $this->writeHTMLCell(186, 0, 12, $currentY, $clienteHtml, 0, 1, false, true, 'L');
        $currentY = $this->GetY() + 3;

        $this->writeHTMLCell(186, 0, 12, $currentY, $vehiculoHtml, 0, 1, false, true, 'L');
        $this->SetY($this->GetY() + 4);
    }

    private function drawItems(): void
    {
        $currentY = $this->GetY();
        $rows = '';

        foreach ($this->repuestos as $item) {
            $rows .= '<tr>
                <td width="14%" align="left">' . number_format((float) $item['cantidad'], 2, ',', '.') . '</td>
                <td width="50%">' . htmlspecialchars((string) $item['nombre'], ENT_QUOTES, 'UTF-8') . '</td>
                <td width="18%" align="right">$ ' . number_format((float) $item['precio_unitario'], 2, ',', '.') . '</td>
                <td width="18%" align="right">$ ' . number_format((float) $item['subtotal'], 2, ',', '.') . '</td>
            </tr>';
        }

        foreach ($this->tareas as $tarea) {
            $descripcion = trim((string) ($tarea['descripcion'] ?? 'Mano de obra'));
            $rows .= '<tr>
                <td width="14%" align="left">' . number_format((float) $tarea['tiempo_horas'], 2, ',', '.') . '</td>
                <td width="50%">' . htmlspecialchars($descripcion, ENT_QUOTES, 'UTF-8') . '</td>
                <td width="18%" align="right">$ ' . number_format((float) $tarea['costo_hora'], 2, ',', '.') . '</td>
                <td width="18%" align="right">$ ' . number_format((float) $tarea['subtotal'], 2, ',', '.') . '</td>
            </tr>';
        }

        if ($rows === '') {
            $rows = '<tr><td width="100%" align="center">Sin ítems asociados.</td></tr>';
        }

        $html = '<table cellpadding="2" cellspacing="0" border="1" style="font-size: 8pt;">
            <tr style="font-weight: bold; background-color: #f4f4f4;">
                <td width="14%" align="left">Cant.</td>
                <td width="50%">Detalle</td>
                <td width="18%" align="right">P. Unit.</td>
                <td width="18%" align="right">Subtotal</td>
            </tr>
            ' . $rows . '
        </table>';

        $this->writeHTMLCell(186, 0, 12, $currentY, $html, 0, 1, false, true, 'L');
    }

    private function drawTotals(): void
    {
        $currentY = 260;
        $leftX = 126;
        $valueX = 168;

        $this->SetFont('helvetica', '', 10);
        $this->SetTextColor(25, 25, 25);

        $this->SetXY($leftX, $currentY);
        $this->Cell(30, 5, 'Subtotal', 0, 0, 'L');
        $this->SetXY($valueX, $currentY);
        $this->Cell(30, 5, '$ ' . number_format((float) $this->factura['subtotal'], 2, ',', '.'), 0, 1, 'R');

        $this->SetXY($leftX, $currentY + 6);
        $this->Cell(30, 5, 'Impuestos', 0, 0, 'L');
        $this->SetXY($valueX, $currentY + 6);
        $this->Cell(30, 5, '$ ' . number_format((float) $this->factura['impuestos_total'], 2, ',', '.'), 0, 1, 'R');

        $this->SetFont('helvetica', 'B', 11);
        $this->SetXY($leftX, $currentY + 13);
        $this->Cell(30, 6, 'Total', 0, 0, 'L');
        $this->SetXY($valueX, $currentY + 13);
        $this->Cell(30, 6, '$ ' . number_format((float) $this->factura['total'], 2, ',', '.'), 0, 1, 'R');
    }

    private function drawNotes(): void
    {
        $currentY = min($this->GetY() + 4, 245);
        $this->SetXY(12, $currentY);
        $this->SetFont('helvetica', '', 8);

        $observaciones = trim((string) ($this->factura['observaciones'] ?? ''));
        if ($observaciones !== '') {
            $this->MultiCell(186, 8, 'Observaciones: ' . $observaciones, 0, 'L', false, 1);
            $currentY = $this->GetY() + 5;
        }

        $this->SetXY(12, 286);
        $this->SetTextColor(95, 95, 95);
        $this->Cell(186, 5, 'Documento generado por Sur Bateria', 0, 1, 'C');
    }
}

$pdf = new FacturaComercialPDF($factura, $repuestos, $tareas, $empresaRazonSocial, $empresaCuit, $empresaDireccion);
$pdf->render();
$pdf->Output('factura-' . preg_replace('/[^A-Za-z0-9\-]/', '-', (string) $factura['numero_factura']) . '.pdf', 'I');
exit();