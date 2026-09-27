<?php
session_start();
require_once 'includes/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    if (empty($new) || $new !== $confirm) {
        $error = 'Las contraseñas no coinciden o están vacías.';
    } else {
        try {
            $db = getDBConnection();
            $hash = password_hash($new, PASSWORD_BCRYPT);
            $sql = 'UPDATE usuarios SET password = :p, must_change_password = 0, data_edit = :d WHERE id = :id';
            $stmt = $db->prepare($sql);
            $stmt->execute([':p' => $hash, ':d' => date('Y-m-d H:i:s'), ':id' => $_SESSION['user_id']]);
            header('Location: dashboard.php');
            exit();
        } catch (Exception $e) {
            $error = 'Error al actualizar la contraseña: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cambiar contraseña - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/styleess.css">
</head>
<body>
    <div class="container">
        <h2>Cambiar contraseña obligatoria</h2>
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        <form method="POST" action="">
            <div class="form-group">
                <input type="password" name="new_password" placeholder="Nueva contraseña" required>
            </div>
            <div class="form-group">
                <input type="password" name="confirm_password" placeholder="Confirmar contraseña" required>
            </div>
            <button type="submit" class="btn btn-primary">Actualizar contraseña</button>
        </form>
    </div>
</body>
</html>
