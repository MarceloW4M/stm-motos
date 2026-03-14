<?php
// 1. Activar output buffering al INICIO
ob_start();

// 2. Verificar si TCPDF existe
if (!file_exists('tcpdf/tcpdf.php')) {
    ob_end_clean();
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

// 5. Determinar el turno: puede venir directo o bien obtenerlo a partir de una orden
$turno_id = 0;

if (isset($_GET['turno_id'])) {
    $turno_id = intval($_GET['turno_id']);
} elseif (isset($_GET['orden_id'])) {
    $orden_id = intval($_GET['orden_id']);
    if ($orden_id > 0) {
        // necesitamos conexión para resolver el turno
        $database = new Database();
        $db = $database->getConnection();
        $stmt = $db->prepare("SELECT turno_id FROM ordenes_reparacion WHERE id = :orden_id");
        $stmt->bindParam(':orden_id', $orden_id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $turno_id = intval($row['turno_id']);
        }
    }
}

if ($turno_id <= 0) {
    ob_end_clean();
    header('Location: turnos.php?error=no_turno_id');
    exit();
}

// 6. Obtener datos del turno
try {
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "SELECT t.*, c.nombre as cliente_nombre, c.telefono, c.email, 
                     v.marca, v.modelo, v.matricula, v.anio, 
                     o.id as orden_id, o.numero_orden as numero_orden_reparacion
              FROM turnos t 
              INNER JOIN clientes c ON t.cliente_id = c.id 
              INNER JOIN vehiculos v ON t.vehiculo_id = v.id 
              LEFT JOIN ordenes_reparacion o ON o.turno_id = t.id            
              WHERE t.id = :turno_id";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':turno_id', $turno_id);
    $stmt->execute();
    $turno = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$turno) {
        throw new Exception("Turno no encontrado");
    }
    
    // si existe orden asociada, traer repuestos y tareas
    if (!empty($turno['orden_id'])) {
        try {
            // repuestos
            $stmt_r = $db->prepare("SELECT or2.*, r.nombre, or2.precio_unitario 
                                     FROM orden_repuestos or2 
                                     INNER JOIN repuestos r ON or2.repuesto_id = r.id 
                                     WHERE or2.orden_id = :orden_id");
            $stmt_r->bindParam(':orden_id', $turno['orden_id'], PDO::PARAM_INT);
            $stmt_r->execute();
            $turno['repuestos'] = $stmt_r->fetchAll(PDO::FETCH_ASSOC);

            // tareas
            $stmt_t = $db->prepare("SELECT * FROM orden_tareas WHERE orden_id = :orden_id");
            $stmt_t->bindParam(':orden_id', $turno['orden_id'], PDO::PARAM_INT);
            $stmt_t->execute();
            $turno['tareas'] = $stmt_t->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            // ignorar, no es fatal
            $turno['repuestos'] = [];
            $turno['tareas'] = [];
        }
    } else {
        $turno['repuestos'] = [];
        $turno['tareas'] = [];
    }
} catch (Exception $e) {
    ob_end_clean();
    header('Location: turnos.php?error=' . urlencode($e->getMessage()));
    exit();
}

// 7. Limpiar buffer de salida
while (ob_get_level()) {
    ob_end_clean();
}

// 8. Incluir TCPDF
require_once 'tcpdf/tcpdf.php';

// ==============================================
// CLASE OPTIMIZADA PARA UNA PÁGINA A4
// ==============================================
class FacturaSTM extends TCPDF {
    
    private $turno;
    private $currentY; // Para trackear posición Y
    
    public function __construct($turno) {
        parent::__construct('P', 'mm', 'A4', true, 'UTF-8', false);
        $this->turno = $turno;
        
        // CONFIGURACIÓN PARA UNA SOLA PÁGINA
        $this->setPrintHeader(false);
        $this->setPrintFooter(false);
        $this->SetAutoPageBreak(false); // IMPORTANTE: Desactivar auto page break
        $this->SetMargins(10, 10, 10);  // Márgenes mínimos
    }
    
    // Método principal
    public function generarFactura() {
        $this->AddPage();
        
        // ENCABEZADO COMPACTO
        $this->generarEncabezadoCompacto();
        
        // INFORMACIÓN DEL CLIENTE COMPACTA
        $this->generarInfoClienteCompacta();
        
        // DETALLES DEL SERVICIO COMPACTOS
        $this->generarDetallesServicioCompactos();
        
        // FIRMAS ALINEADAS (al final de la página)
        $this->generarFirmasFinal();
    }
    
    private function generarEncabezadoCompacto() {
        // Logo pequeño
        $this->Image('css/img/logo01.png', 10, 3, 40);
        
        // QR pequeño
        if (!empty($this->turno['numero_orden_reparacion'])) {
            $qr_content = "STM|Orden:" . $this->turno['numero_orden_reparacion'] . 
                         "|Cliente:" . substr($this->turno['cliente_nombre'], 0, 20) . 
                         "|Fecha:" . date('d/m/Y');
            
            $this->write2DBarcode($qr_content, 'QRCODE,L', 165, 8, 30, 30, array('border' => 0), 'N');
        }
        
        // Título compacto
        $this->SetFont('helvetica', 'B', 18);
        $this->SetTextColor(211, 47, 47);
        $this->SetXY(40, 10);
        $this->Cell(120, 6, 'STM - AVENTURA MOTOS', 0, 1, 'C');
        $this->Ln(2);
        
        $this->SetFont('helvetica', 'I', 10);
        $this->SetTextColor(100, 100, 100);
        $this->SetX(40);
        $this->Cell(120, 4, 'Taller Especializado YAMAHA', 0, 1, 'C');
              
        
        // Número de orden destacado pero compacto
        $this->SetY(26);
        $this->SetFont('helvetica', 'B', 14);
        $this->SetTextColor(0, 0, 0);
        
        $orden_text = !empty($this->turno['numero_orden_reparacion']) 
            ? 'ORDEN: ' . $this->turno['numero_orden_reparacion']
            : 'INFORME DE SERVICIO';
            
        $this->Cell(0, 6, $orden_text, 0, 1, 'C');
        
        $this->Ln(11);
        $this->currentY = $this->GetY();
    }
    
    private function generarInfoClienteCompacta() {
        // Tabla más compacta
        $html = '<table border="0" cellpadding="3" cellspacing="0" style="width: 100%; font-size: 9pt;">
            <tr style="background-color: #afcdf0;">
                <td colspan="4" style="font-weight: bold; padding: 4px;">DATOS DEL CLIENTE Y VEHÍCULO</td>
            </tr>
            <tr>
                <td width="15%"><strong>Cliente:</strong></td>
                <td width="35%">' . htmlspecialchars(substr($this->turno['cliente_nombre'], 0, 30)) . '</td>
                <td width="15%"><strong>Teléfono:</strong></td>
                <td width="35%">' . htmlspecialchars($this->turno['telefono'] ?? 'N/A') . '</td>
            </tr>
            <tr>
                <td><strong>Email:</strong></td>
                <td>' . htmlspecialchars(substr($this->turno['email'] ?? 'N/A', 0, 25)) . '</td>
                <td><strong>Vehículo:</strong></td>
                <td>' . htmlspecialchars($this->turno['marca'] . ' ' . $this->turno['modelo']) . '</td>
            </tr>
            <tr>
                <td><strong>Matrícula:</strong></td>
                <td>' . htmlspecialchars($this->turno['matricula'] ?? 'N/A') . '</td>
                <td><strong>Año:</strong></td>
                <td>' . htmlspecialchars($this->turno['anio'] ?? 'N/A') . '</td>
            </tr>
        </table>';
        
        $this->writeHTML($html, true, false, true, false, '');
        $this->Ln(2);
        $this->currentY = $this->GetY();
        
        // Verificar que no nos pasemos de la mitad de la página
        if ($this->currentY > 120) {
            $this->AddPage();
            $this->currentY = 10;
        }
    }
    
    private function generarDetallesServicioCompactos() {
        // Tabla de detalles compacta
        $fecha = !empty($this->turno['fecha']) ? date('d/m/Y', strtotime($this->turno['fecha'])) : 'N/A';
        $hora_inicio = !empty($this->turno['hora_inicio']) ? date('H:i', strtotime($this->turno['hora_inicio'])) : 'N/A';
        $hora_fin = !empty($this->turno['hora_fin']) ? date('H:i', strtotime($this->turno['hora_fin'])) : 'N/A';
        
        $html = '<table border="0" cellpadding="3" cellspacing="0" style="width: 100%; font-size: 9pt;">
            <tr style="background-color: #afcdf0;">
                <td colspan="4" style="font-weight: bold; padding: 4px;">DETALLES DEL SERVICIO</td>
            </tr>
            <tr>
                <td width="15%"><strong>Fecha:</strong></td>
                <td width="35%">' . $fecha . '</td>
                <td width="15%"><strong>Hora Inicio:</strong></td>
                <td width="35%">' . $hora_inicio . '</td>
            </tr>
            <tr>
                <td><strong>Hora Fin:</strong></td>
                <td>' . $hora_fin . '</td>
                <td><strong>Servicio:</strong></td>
                <td>' . htmlspecialchars($this->turno['servicio'] ?? 'N/A') . '</td>
            </tr>
        </table>';
        
        $this->writeHTML($html, true, false, true, false, '');
        $this->Ln(6);
        $this->currentY = $this->GetY();
        
        // Descripción con MultiCell limitado
        if (!empty($this->turno['descripcion'])) {
            $this->SetFont('helvetica', 'B', 10);
            $this->Cell(0, 6, 'DESCRIPCIÓN DEL TRABAJO:', 0, 1);
            
            $this->SetFont('helvetica', '', 9);
            $descripcion = htmlspecialchars($this->turno['descripcion']);
            // Limitar descripción si es muy larga
            if (strlen($descripcion) > 500) {
                $descripcion = substr($descripcion, 0, 497) . '...';
            }
            $this->MultiCell(0, 5, $descripcion, 0, 'L');
            $this->Ln(4);
            $this->currentY = $this->GetY();
        }
        
        // Observaciones compactas
        if (!empty($this->turno['observaciones'])) {
            $this->SetFont('helvetica', 'B', 10);
            $this->Cell(0, 6, 'OBSERVACIONES:', 0, 1);
            
            $this->SetFont('helvetica', '', 9);
            $observaciones = htmlspecialchars($this->turno['observaciones']);
            // Limitar observaciones
            if (strlen($observaciones) > 300) {
                $observaciones = substr($observaciones, 0, 297) . '...';
            }
            $this->MultiCell(0, 5, $observaciones, 0, 'L');
            $this->Ln(4);
            $this->currentY = $this->GetY();
        }
        
        // Añadir repuestos y tareas si existen
        if (!empty($this->turno['repuestos']) || !empty($this->turno['tareas'])) {
            $this->Ln(4);
            $this->generarItemsOrden();
        }
        
        // Costo si existe
        if (!empty($this->turno['precio'])) {
            $this->SetFont('helvetica', 'B', 10);
            $this->Cell(0, 6, 'INFORMACIÓN DE COSTO', 0, 1);
            
            $this->SetFont('helvetica', '', 9);
            $this->Cell(40, 6, 'Costo del servicio:', 0, 0);
            $this->SetFont('helvetica', 'B', 10);
            $this->Cell(0, 6, '$ ' . number_format($this->turno['precio'], 2), 0, 1);
            $this->Ln(6);
            $this->currentY = $this->GetY();
        }
        
        // Verificar espacio para firmas (necesitamos ~40mm)
        if ($this->currentY > 240) {
            // Si estamos muy abajo, reducir algo
            $this->SetY(240);
        }
    }
    
    private function generarFirmasFinal() {
        // Posicionar firmas en la parte inferior
        $this->SetY(250); // Posición fija cerca del final
        
        $this->SetFont('helvetica', '', 9);
        
        // Técnico (Izquierda) - Más compacto
        $this->SetX(15);
        $this->Cell(75, 5, '________________________', 0, 0, 'L');
        
        // Espacio
        $this->Cell(20, 5, '', 0, 0, 'C');
        
        // Cliente (Derecha) - Más compacto
        $this->Cell(75, 5, '________________________', 0, 1, 'R');
        
        // Textos
        $this->SetX(15);
        $this->Cell(75, 4, 'Firma del Técnico', 0, 0, 'L');
        
        $this->Cell(20, 4, '', 0, 0, 'C');
        
        $this->Cell(75, 4, 'Firma del Cliente', 0, 1, 'R');
        
        // Información adicional compacta
        $this->SetX(15);
        $this->Cell(75, 4, 'Fecha: ' . date('d/m/Y'), 0, 0, 'L');
        
        $this->Cell(20, 4, '', 0, 0, 'C');
        
        $this->Cell(75, 4, 'Documento válido por 30 días', 0, 1, 'R');
        
        // Pie de página mínimo
        $this->SetY(-12);
        $this->SetFont('helvetica', 'I', 7);
        $this->SetTextColor(150, 150, 150);
        $this->Cell(0, 4, 'STM - Aventura Motos  •  Documento NO VÁLIDO COMO FACTURA  •  Generado: ' . date('d/m/Y H:i'), 0, 1, 'C');
        $this->Cell(0, 4, 'La garantía de los trabajos realizados tiene una validez de 30 días a partir de la fecha de entrega', 0, 1, 'C');
    }

    // ------------------------------------------
    // Si la orden asociada trae repuestos/tareas,
    // los renderizamos en tablas.
    private function generarItemsOrden() {
        $total_repuestos = 0;
        $total_mano_obra = 0;

        if (!empty($this->turno['repuestos'])) {
            $this->SetFont('helvetica', 'B', 10);
            $this->Cell(0, 6, 'REPUESTOS UTILIZADOS', 0, 1);
            $this->SetFont('helvetica', '', 9);

            $html = '<table border="1" cellpadding="3" cellspacing="0" style="width:100%; font-size:8pt;">'
                  . '<tr style="background-color:#eceff1;"><th>Nombre</th><th>Cant.</th><th>U/Precio</th><th>Subtotal</th></tr>';
            foreach ($this->turno['repuestos'] as $r) {
                $sub = $r['cantidad'] * $r['precio_unitario'];
                $total_repuestos += $sub;
                $html .= '<tr><td>' . htmlspecialchars($r['nombre']) . '</td>' .
                         '<td align="center">' . $r['cantidad'] . '</td>' .
                         '<td align="right">$' . number_format($r['precio_unitario'],2) . '</td>' .
                         '<td align="right">$' . number_format($sub,2) . '</td></tr>';
            }
            $html .= '</table>';
            $this->writeHTML($html, true, false, true, false, '');
            $this->Ln(4);
        }
        if (!empty($this->turno['tareas'])) {
            $this->SetFont('helvetica', 'B', 10);
            $this->Cell(0, 6, 'TAREAS REALIZADAS', 0, 1);
            $this->SetFont('helvetica', '', 9);

            $html = '<table border="1" cellpadding="3" cellspacing="0" style="width:100%; font-size:8pt;">'
                  . '<tr style="background-color:#eceff1;"><th>Descripción</th><th>Horas</th><th>Costo/H</th><th>Subtotal</th></tr>';
            foreach ($this->turno['tareas'] as $t) {
                $sub = $t['tiempo_horas'] * $t['costo_hora'];
                $total_mano_obra += $sub;
                $html .= '<tr><td>' . htmlspecialchars($t['descripcion']) . '</td>' .
                         '<td align="center">' . number_format($t['tiempo_horas'],1) . '</td>' .
                         '<td align="right">$' . number_format($t['costo_hora'],2) . '</td>' .
                         '<td align="right">$' . number_format($sub,2) . '</td></tr>';
            }
            $html .= '</table>';
            $this->writeHTML($html, true, false, true, false, '');
            $this->Ln(4);
        }

        // Mostrar totales si hay ítems
        if (!empty($this->turno['repuestos']) || !empty($this->turno['tareas'])) {
            $total_orden = $total_repuestos + $total_mano_obra;
            $this->SetFont('helvetica', 'B', 10);
            $this->Cell(0, 6, 'RESUMEN DE COSTOS', 0, 1);
            $this->SetFont('helvetica', '', 9);

            $html_totales = '<table border="1" cellpadding="3" cellspacing="0" style="width:100%; font-size:9pt;">'
                          . '<tr><td width="70%"><strong>Total Repuestos:</strong></td><td width="30%" align="right"><strong>$' . number_format($total_repuestos, 2) . '</strong></td></tr>'
                          . '<tr><td><strong>Total Mano de Obra:</strong></td><td align="right"><strong>$' . number_format($total_mano_obra, 2) . '</strong></td></tr>'
                          . '<tr style="background-color:#f0f0f0;"><td><strong>TOTAL ORDEN:</strong></td><td align="right"><strong style="font-size:11pt;">$' . number_format($total_orden, 2) . '</strong></td></tr>'
                          . '</table>';
            $this->writeHTML($html_totales, true, false, true, false, '');
            $this->Ln(6);
        }
    }
}

// ==============================================
// GENERAR EL PDF
// ==============================================

try {
    // Crear instancia
    $pdf = new FacturaSTM($turno);
    
    // Generar factura
    $pdf->generarFactura();
    
    // Nombre del archivo
    $filename = 'informe_stm_';
    if (!empty($turno['numero_orden_reparacion'])) {
        $filename .= $turno['numero_orden_reparacion'];
    } else {
        $filename .= 'turno_' . $turno_id;
    }
    $filename .= '.pdf';
    
    // Enviar al navegador
    $pdf->Output($filename, 'I');
    
} catch (Exception $e) {
    // En caso de error
    header('Content-Type: text/html; charset=utf-8');
    echo '<h3>Error al generar el PDF</h3>';
    echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<p><a href="turnos.php">Volver a la lista de turnos</a></p>';
}

exit();
?>