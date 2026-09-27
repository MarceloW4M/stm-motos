<?php
// Endpoint móvil para n8n -> muestra id_telegram y busca cliente por CUIT
require_once 'includes/database.php';

$id_telegram = trim((string)($_GET['id_telegram'] ?? ''));
$cuit = trim((string)($_GET['cuit'] ?? ''));

// Simple validación mínima
if ($id_telegram === '') {
    http_response_code(400);
    echo "Falta parámetro: id_telegram";
    exit;
}

$cliente = null;
if ($cuit !== '') {
    try {
        $db = (new Database())->getConnection();
        $query = "SELECT id,nombre,telefono,email,cuit FROM clientes WHERE cuit = :cuit LIMIT 1";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':cuit', $cuit);
        $stmt->execute();
        $cliente = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Exception $e) {
        // ignorar y mostrar fallback
        $cliente = null;
    }
}

if ($cliente) {
    // Mostrar página móvil con datos del cliente y el id_telegram
    ?>
    <!doctype html>
    <html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width,initial-scale=1">
        <title>Identificador Telegram</title>
        <link rel="stylesheet" href="css/styleess.css">
        <style>body{padding:14px;background:var(--color-bg,#f9f9f9);} .mobile-card{max-width:480px;margin:0 auto;}
        .id-box{background:#fff;border-radius:10px;padding:14px;border:1px solid #e6eefc;text-align:center;}
        .id-value{font-weight:700;font-size:1.1rem;color:#123f85;word-break:break-all}
        .meta{font-size:0.9rem;color:#55607a;margin-top:8px}
        .btn-copy{margin-top:12px;padding:10px 14px;border-radius:8px;background:linear-gradient(90deg,#123f85,#2a72cf);color:#fff;border:none;}
        </style>
    </head>
    <body>
        <div class="mobile-card">
            <h2>Cliente encontrado</h2>
            <div class="id-box">
                <div class="meta">Nombre</div>
                <div class="id-value"><?php echo htmlspecialchars($cliente['nombre']); ?></div>
                <div class="meta">Teléfono: <?php echo htmlspecialchars($cliente['telefono'] ?? ''); ?></div>
                <hr style="margin:12px 0;border:none;border-top:1px solid #eef3ff">
                <div class="meta">ID Telegram recibido por n8n:</div>
                <div class="id-value" id="tgId"><?php echo htmlspecialchars($id_telegram); ?></div>
                <button class="btn-copy" onclick="copyId()">Copiar ID</button>
            </div>
            <p style="text-align:center;margin-top:12px;font-size:0.9rem;color:#7a8290">Si querés vincular este ID al cliente, gestionálo desde el panel de administración.</p>
        </div>

        <script>
        function copyId(){
            const id = document.getElementById('tgId').textContent.trim();
            if (navigator.clipboard) navigator.clipboard.writeText(id);
            alert('ID copiado: ' + id);
        }
        </script>
    </body>
    </html>
    <?php
    exit;
} else {
    // Redirigir al HTML notfound (mantener mobile style)
    header('Location: n8n_telegram_mobile_notfound.php?id_telegram=' . urlencode($id_telegram) . '&cuit=' . urlencode($cuit));
    exit;
}

?>
