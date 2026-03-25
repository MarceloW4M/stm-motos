<?php
// Página móvil para n8n cuando NO se encuentra el CUIT en la base
$id_telegram = trim((string)($_GET['id_telegram'] ?? ''));
$cuit = trim((string)($_GET['cuit'] ?? ''));
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Cliente no encontrado</title>
    <link rel="stylesheet" href="css/styleess.css">
    <style>
        body{padding:14px;background:var(--color-bg,#f9f9f9);} .mobile-card{max-width:480px;margin:0 auto;text-align:center}
        .warn{background:#fff;border-radius:10px;padding:14px;border:1px solid #fdecea;color:#7a2a2a}
        .id-value{font-weight:700;color:#123f85;word-break:break-all;margin-top:8px}
        .actions{margin-top:12px;display:flex;gap:8px;justify-content:center}
        .btn{padding:10px 12px;border-radius:8px;border:none}
        .btn-primary{background:#123f85;color:#fff}
        .btn-outline{background:#fff;border:1px solid #c7d7f5;color:#123f85}
    </style>
</head>
<body>
    <div class="mobile-card">
        <h2>Cliente no encontrado</h2>
        <div class="warn">
            <p>No se encontró ningún cliente con el CUIT: <strong><?php echo htmlspecialchars($cuit ?: '-'); ?></strong></p>
            <p>Se recibió el siguiente ID de Telegram desde n8n:</p>
            <div class="id-value" id="tgId"><?php echo htmlspecialchars($id_telegram); ?></div>
        </div>

        <div class="actions">
            <button class="btn btn-primary" onclick="copyId()">Copiar ID</button>
            <a class="btn btn-outline" href="clientes.php">Buscar manualmente</a>
        </div>

        <p style="font-size:0.9rem;color:#6b7280;margin-top:12px">Si querés, agregá el cliente desde la administración y luego vinculá el ID de Telegram.</p>
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
