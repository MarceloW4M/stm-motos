<?php
// Página pública mínima para comprobar existencia de cliente por id_boot o cuit
// Si no existe, muestra formulario para dar de alta los datos esenciales

require_once __DIR__ . '/includes/database.php';

$message = '';
$id_boot = isset($_REQUEST['id_boot']) ? trim((string)$_REQUEST['id_boot']) : '';
$cuit_search = isset($_REQUEST['cuit']) ? trim((string)$_REQUEST['cuit']) : '';

$db = (new \Database())->getConnection();

// Si se envía el formulario de verificación (POST), comprobamos el CUIT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verificar_cuit'])) {
    $cuit = trim((string)($_POST['cuit'] ?? ''));
    $id_boot_post = trim((string)($_POST['id_boot'] ?? ''));
    if ($cuit === '') {
        $message = 'Ingrese un CUIT para verificar.';
    } else {
        $stmt = $db->prepare('SELECT id FROM clientes WHERE cuit = :cuit LIMIT 1');
        $stmt->bindParam(':cuit', $cuit);
        $stmt->execute();
        $found = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($found) {
            // Cliente existe: abrir el formulario de alta/edición con datos precargados
            $clientId = (int)$found['id'];
            $loc = 'alta_cliente.php?id=' . $clientId . '&auto_close=1';
            header('Location: ' . $loc);
            exit;
        } else {
            // No existe: redirigir a alta con datos mínimos y flag auto_close
            $params = ['cuit' => $cuit];
            if ($id_boot_post !== '') $params['id_boot'] = $id_boot_post;
            $params['auto_close'] = '1';
            header('Location: alta_cliente.php?' . http_build_query($params));
            exit;
        }
    }
}

// Render del formulario de verificación (GET)
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Verificar CUIT - STM</title>
    <link rel="stylesheet" href="css/styleess.css">
    <style>
        body{background:#f4f7fb;margin:0;font-family:Inter,Segoe UI,Roboto,Arial,sans-serif}
        .box{max-width:720px;margin:18px auto;padding:16px;background:#fff;border:1px solid #e6eefc;border-radius:12px}
        label{display:block;margin-bottom:6px;font-weight:600}
        input{width:100%;padding:12px;border:1px solid #d7e6fb;border-radius:10px}
        .btn{padding:12px 16px;border-radius:10px;border:none;cursor:pointer;font-weight:700}
        .btn-primary{background:#1f5fbf;color:#fff}
        /* Estilo para mostrar id_boot como botón visual (sin acción) */
        .id-boot-btn{display:inline-block;padding:10px 14px;border-radius:10px;background:#f0f4fa;color:#16345d;border:1px solid #d7e6fb;box-shadow:0 4px 14px rgba(31,95,191,0.06);font-weight:700;margin-top:8px}
        .center-row{text-align:center}
    </style>
</head>
<body>
<div class="box">
    <div class="center-row">
        <h2 style="margin:0;display:inline-block;">Verificar Cliente</h2>
    </div>
    <?php if ($message): ?>
        <div class="alert"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <div class="center-row" style="margin:12px 0">
        <div class="id-boot-btn" aria-hidden="true"><?php echo htmlspecialchars($id_boot ?: 'Sin id_boot', ENT_QUOTES, 'UTF-8'); ?></div>
    </div>

    <form method="POST">
        <input type="hidden" name="id_boot" value="<?php echo htmlspecialchars($id_boot ?: '', ENT_QUOTES, 'UTF-8'); ?>">
        <label for="cuit">CUIT</label>
        <input id="cuit" name="cuit" type="text" placeholder="20-12345678-9" value="<?php echo htmlspecialchars($cuit_search ?: '', ENT_QUOTES, 'UTF-8'); ?>" required>
        <div style="margin-top:12px;text-align:right">
            <button type="submit" name="verificar_cuit" class="btn btn-primary">Verificar CUIT</button>
        </div>
    </form>
</div>
</body>
</html>
