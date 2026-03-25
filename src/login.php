<?php
session_start();

// Si ya está autenticado, redirigir al dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

// Datos de conexión a la base de datos
$db_host = '10.50.0.30';
$db_port = '3406';
$db_name = 'stm_taller';
$db_user = 'root'; // Cambiar por tu usuario
$db_pass = 'w1f14m3d1a'; // Cambiar por tu contraseña

// Procesar el formulario de login
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    try {
        // Conexión a la base de datos
        $dsn = "mysql:host=$db_host;port=$db_port;dbname=$db_name;charset=utf8";
        $db = new PDO($dsn, $db_user, $db_pass);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Consultar usuario
        $query = "SELECT id, username, password FROM usuarios WHERE username = :username";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->execute();
        
        if ($stmt->rowCount() == 1) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Verificar contraseña (asumiendo que está hasheada con password_hash)
            if ($password === $user['password']) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                header("Location: dashboard.php");
                exit();
            } else {
                $error = "Credenciales incorrectas";
            }
        } else {
            $error = "Credenciales incorrectas";
        }
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
    <title>Login - STM Taller de Motos</title>
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
