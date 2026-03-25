<?php
require_once __DIR__ . '/includes/database.php';
// Página pública para alta/edición rápida de clientes (compatible con flujo n8n)

$db = (new \Database())->getConnection();
$message = '';
$showClose = false;

// Determinar longitud máxima del campo cuit en la tabla clientes (fallback 20)
$max_cuit_len_db = 20;
try {
    $col = $db->query("SHOW COLUMNS FROM clientes LIKE 'cuit'")->fetch(PDO::FETCH_ASSOC);
    if ($col && isset($col['Type'])) {
        if (preg_match('/varchar\((\d+)\)/i', $col['Type'], $m)) {
            $max_cuit_len_db = (int)$m[1];
        }
    }
} catch (Exception $e) {
    // ignore and keep fallback
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$id_boot = isset($_GET['id_boot']) ? trim((string)$_GET['id_boot']) : (isset($_POST['id_boot'])?trim((string)$_POST['id_boot']):'');
$cuit = isset($_GET['cuit']) ? trim((string)$_GET['cuit']) : (isset($_POST['cuit'])?trim((string)$_POST['cuit']):'');

$cliente = null;
if ($id) {
    $stmt = $db->prepare('SELECT id,cuit,nombre,telefono,email,id_boot FROM clientes WHERE id = :id LIMIT 1');
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

// precargar por id_boot o cuit si existe
if (!$cliente && !empty($id_boot)) {
    $stmt = $db->prepare('SELECT id,cuit,nombre,telefono,email,id_boot FROM clientes WHERE id_boot = :id_boot LIMIT 1');
    $stmt->bindParam(':id_boot', $id_boot);
    $stmt->execute();
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}
if (!$cliente && !empty($cuit)) {
    $stmt = $db->prepare('SELECT id,cuit,nombre,telefono,email,id_boot FROM clientes WHERE cuit = :cuit LIMIT 1');
    $stmt->bindParam(':cuit', $cuit);
    $stmt->execute();
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

// Valor a mostrar para id_boot (cliente tiene prioridad)
$display_id_boot = '';
if ($cliente && !empty($cliente['id_boot'])) {
    $display_id_boot = $cliente['id_boot'];
} elseif (!empty($id_boot)) {
    $display_id_boot = $id_boot;
}

// Procesar POST (crear/actualizar)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $post_cuit = trim((string)($_POST['cuit'] ?? ''));
    $post_nombre = trim((string)($_POST['nombre'] ?? ''));
    $post_telefono = trim((string)($_POST['telefono'] ?? ''));
    $post_email = trim((string)($_POST['email'] ?? ''));
    $post_id_boot = trim((string)($_POST['id_boot'] ?? '')) ?: null;

    // Normalizar y validar CUIT
    $post_cuit = preg_replace('/\s+/', '', $post_cuit);
    if ($post_cuit === '' || $post_nombre === '') {
        $message = 'CUIT y Nombre son obligatorios.';
    } elseif (mb_strlen($post_cuit) > $max_cuit_len_db) {
        $message = 'CUIT demasiado largo; máximo ' . $max_cuit_len_db . ' caracteres.';
    } else {
        if ($post_id) {
            // actualizar
            $upd = $db->prepare('UPDATE clientes SET cuit = :cuit, nombre = :nombre, telefono = :telefono, email = :email, id_boot = :id_boot WHERE id = :id');
            $upd->bindParam(':cuit', $post_cuit);
            $upd->bindParam(':nombre', $post_nombre);
            $upd->bindParam(':telefono', $post_telefono);
            $upd->bindParam(':email', $post_email);
            $upd->bindParam(':id_boot', $post_id_boot);
            $upd->bindParam(':id', $post_id, PDO::PARAM_INT);
            try {
                $upd->execute();
                $message = '✅ Cliente actualizado.';
                if (isset($_POST['__auto_close']) && $_POST['__auto_close'] == '1') {
                    $showClose = true;
                } else {
                    header('Location: clientes.php');
                    exit;
                }
            } catch (PDOException $e) {
                $message = 'Error al actualizar: ' . $e->getMessage();
            }
        } else {
            // insertar
            $ins = $db->prepare('INSERT INTO clientes (cuit,nombre,telefono,email,id_boot,created_at,activo) VALUES (:cuit,:nombre,:telefono,:email,:id_boot,NOW(),1)');
            $ins->bindParam(':cuit', $post_cuit);
            $ins->bindParam(':nombre', $post_nombre);
            $ins->bindParam(':telefono', $post_telefono);
            $ins->bindParam(':email', $post_email);
            $ins->bindParam(':id_boot', $post_id_boot);
            try {
                $ins->execute();
                $newId = $db->lastInsertId();
                $message = '✅ Cliente creado (ID ' . $newId . ').';
                if (isset($_POST['__auto_close']) && $_POST['__auto_close'] == '1') {
                    $showClose = true;
                } else {
                    header('Location: clientes.php');
                    exit;
                }
            } catch (PDOException $e) {
                $message = 'Error al crear cliente: ' . $e->getMessage();
            }
        }
    }
}

?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?php echo $cliente ? 'Editar Cliente' : 'Alta de Cliente'; ?></title>
    <link rel="stylesheet" href="css/styleess.css">
    <style>body{background:#f4f7fb;margin:0;font-family:Inter,Segoe UI,Roboto,Arial,sans-serif}</style>
</head>
<body>
<main>
    <div class="mobile-card">
        <h2><?php echo $cliente ? 'Editar Cliente' : 'Alta de Cliente'; ?></h2>
        <?php if ($message): ?>
            <div class="alert"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <form method="POST">
            <?php if ($cliente): ?>
                <input type="hidden" name="id" value="<?php echo (int)$cliente['id']; ?>">
            <?php endif; ?>

            <div class="form-group">
                <label>ID Boot</label>
                <div class="id-boot-btn" aria-hidden="true"><?php echo htmlspecialchars($display_id_boot ?: 'Sin id_boot', ENT_QUOTES, 'UTF-8'); ?></div>
                <input type="hidden" name="id_boot" value="<?php echo htmlspecialchars($display_id_boot, ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>CUIT</label>
                    <input class="form-control" maxlength="<?php echo (int)$max_cuit_len_db; ?>" type="text" name="cuit" required value="<?php echo htmlspecialchars($cliente['cuit'] ?? $cuit, ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="form-group">
                    <label>Nombre</label>
                    <input class="form-control" type="text" name="nombre" required value="<?php echo htmlspecialchars($cliente['nombre'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Teléfono</label>
                    <input class="form-control" type="text" name="telefono" value="<?php echo htmlspecialchars($cliente['telefono'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input class="form-control" type="email" name="email" value="<?php echo htmlspecialchars($cliente['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
            </div>

            <div class="form-actions">
                <button type="button" id="cancelBtn" class="btn btn-secondary">Cancelar</button>
                <button type="submit" class="btn btn-primary"><?php echo $cliente ? 'Actualizar' : 'Crear'; ?></button>
            </div>
        </form>
    </div>

    <?php if ($showClose === true): ?>
        <div style="position:fixed;left:0;right:0;top:0;bottom:0;display:flex;align-items:center;justify-content:center;">
            <div style="background:#fff;padding:18px;border-radius:10px;box-shadow:0 8px 30px rgba(15,23,42,0.12);text-align:center;max-width:420px;">
                <h3>Cliente guardado</h3>
                <p>La ventana se cerrará en 5 segundos.</p>
            </div>
        </div>
        <script>setTimeout(function(){ window.close(); }, 5000);</script>
    <?php endif; ?>
    <script>
    (function(){
        var cancel = document.getElementById('cancelBtn');
        if (!cancel) return;
        cancel.addEventListener('click', function(){
            try { window.close(); return; } catch(e) {}
            try { window.open('', '_self'); window.close(); return; } catch(e) {}
            try { window.location.href = 'about:blank'; setTimeout(function(){ try{ window.close(); }catch(e){} }, 200); } catch(e) {}
        });
    })();
    </script>
</main>
</body>
</html>


