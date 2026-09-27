<?php
session_start();

// Si ya está autenticado, redirigir al dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

require_once 'includes/config.php';

// Procesar el formulario de login
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    try {
        $db = getDBConnection();

        // Consultar usuario (campo en la BD es `usuario`)
        $query = "SELECT id, usuario, password, COALESCE(must_change_password,0) AS must_change_password FROM usuarios WHERE usuario = :username";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->execute();

        if ($stmt->rowCount() === 1) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Verificar contraseña (hash bcrypt o texto plano)
            if (password_verify($password, $user['password']) || $password === $user['password']) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['usuario'];
                // Forzar cambio de contraseña si el flag está activo
                if ((int)$user['must_change_password'] === 1) {
                    header("Location: change_password.php");
                    exit();
                }

                header("Location: dashboard.php");
                exit();
            }
        }

        $error = "Credenciales incorrectas";
    } catch(PDOException $e) {
        $error = "Error de conexión: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/styleess.css">
    <style>
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }
        .login-container {
            margin: 0;
        }
    </style>
</head>
<body>
    <div class="login-container">
         <div class="logo-container">
                <?php if (file_exists('css/img/logo01.png')): ?>
                    <img src="css/img/logo01.png" alt="Logo del Sistema" class="logo">
                <?php else: ?>
                    <i class="fas fa-lock" style="font-size: 50px; color: #2a72cf;"></i>
                <?php endif; ?> 
        </div>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">                
                <input type="text" id="username" name="username" placeholder="Usuario" required autocomplete="username">
            </div>
            
            <div class="form-group">                
                <input type="password" id="password" name="password" placeholder="Password" required autocomplete="current-password">
            </div>
            
            <button type="submit" class="btn btn-primary">Iniciar Sesión</button>
        </form>
    </div>
</body>
</html>
