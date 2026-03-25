<?php
require_once 'includes/auth.php';
require_once 'includes/database.php';
require_once 'includes/header.php';

// Verificar autenticación
requireAuth();

$database = new Database();
$db = $database->getConnection();

// Obtener el ID del cliente desde la URL (solo entero válido)
$cliente_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$cliente_id) {
    header("Location: clientes.php");
    exit();
}

// Procesar actualización de datos
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar_cliente'])) {
    $nombre = trim(filter_input(INPUT_POST, 'nombre', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '');
    $telefono = trim(filter_input(INPUT_POST, 'telefono', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '');
    $email = trim(filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL) ?? '');
    $direccion = trim(filter_input(INPUT_POST, 'direccion', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '');
    $cuit = trim(filter_input(INPUT_POST, 'cuit', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '');
    $ciudad = trim(filter_input(INPUT_POST, 'ciudad', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '');
    $provincia = trim(filter_input(INPUT_POST, 'provincia', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '');
    $pais = trim(filter_input(INPUT_POST, 'pais', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '');
    $codigo_postal = trim(filter_input(INPUT_POST, 'codigo_postal', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '');
    $observaciones = trim(filter_input(INPUT_POST, 'observaciones', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '');

    // Procesar la foto si se subió una
    $foto_path = trim(filter_input(INPUT_POST, 'foto_actual', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? ''); // Mantener la foto actual por defecto
    
    if (isset($_FILES['foto']) && is_uploaded_file($_FILES['foto']['tmp_name']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $foto = $_FILES['foto'];
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        
        // Verificar tamaño máximo (2MB)
        $max_size = 2 * 1024 * 1024; // 2MB en bytes
        
        if (in_array($foto['type'], $allowed_types, true) && $foto['size'] <= $max_size) {
            $upload_dir = 'uploads/clientes/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Generar nombre único para la foto
            $extension = strtolower(pathinfo($foto['name'], PATHINFO_EXTENSION));
            $filename = 'cliente_' . $cliente_id . '_' . time() . '.' . $extension;
            $filepath = $upload_dir . $filename;
            
            if (move_uploaded_file($foto['tmp_name'], $filepath)) {
                $foto_path = $filename; // Guardar solo el nombre del archivo
                
                // Si había una foto anterior, eliminarla
                if (!empty($_POST['foto_actual'])) {
                    $old_file = $upload_dir . $_POST['foto_actual'];
                    if (file_exists($old_file)) {
                        unlink($old_file);
                    }
                }
            } else {
                $error_foto = "Error al subir la foto. Verifique los permisos del directorio 'uploads/clientes/'";
            }
        } else {
            if ($foto['size'] > $max_size) {
                $error_foto = "La foto es demasiado grande. Máximo 2MB permitidos.";
            } else {
                $error_foto = "Tipo de archivo no permitido. Use JPG, PNG, GIF o WebP";
            }
        }
    }
    
    // Procesar eliminación de foto
    $eliminar_foto = filter_input(INPUT_POST, 'eliminar_foto', FILTER_VALIDATE_INT);
    if ($eliminar_foto === 1 && !empty($_POST['foto_actual'])) {
        $old_file = 'uploads/clientes/' . $_POST['foto_actual'];
        if (file_exists($old_file)) {
            unlink($old_file);
        }
        $foto_path = '';
    }
    
    // Actualizar en la base de datos
    try {
        $query = "UPDATE clientes SET 
                  nombre = :nombre, 
                  telefono = :telefono, 
                  email = :email, 
                  direccion = :direccion,
                  cuit = :cuit,
                  ciudad = :ciudad,
                  provincia = :provincia,
                  pais = :pais,
                  codigo_postal = :codigo_postal,
                  observaciones = :observaciones,
                  foto = :foto
                  WHERE id = :id";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':nombre', $nombre);
        $stmt->bindParam(':telefono', $telefono);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':direccion', $direccion);
        $stmt->bindParam(':cuit', $cuit);
        $stmt->bindParam(':ciudad', $ciudad);
        $stmt->bindParam(':provincia', $provincia);
        $stmt->bindParam(':pais', $pais);
        $stmt->bindParam(':codigo_postal', $codigo_postal);
        $stmt->bindParam(':observaciones', $observaciones);
        $stmt->bindParam(':foto', $foto_path);
        $stmt->bindParam(':id', $cliente_id, PDO::PARAM_INT);
        
        $stmt->execute();
        
        $mensaje = "✅ Cliente actualizado correctamente";
        $tipo_mensaje = "success";
        
        // Recargar los datos del cliente para mostrar la foto actualizada
        $query = "SELECT * FROM clientes WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $cliente_id, PDO::PARAM_INT);
        $stmt->execute();
        $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $mensaje = "❌ Error al actualizar el cliente: " . $e->getMessage();
        $tipo_mensaje = "error";
    }
}


// Obtener datos del cliente (si no se recargó después de actualizar)
if (!isset($cliente) || !$cliente) {
    $query = "SELECT * FROM clientes WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $cliente_id);
    $stmt->execute();
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Si no existe el cliente, redirigir
if (!$cliente) {
    header("Location: clientes.php");
    exit();
}

// Generar QR (URL para mostrar en el QR)
$qr_data = "CLIENTE ID: " . $cliente['id'] . "\n" .
           "Nombre: " . $cliente['nombre'] . "\n" .
           "Tel: " . $cliente['telefono'] . "\n" .
           "Email: " . $cliente['email'] . "\n" .
           "CUIT: " . $cliente['cuit'];
$qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode($qr_data);

// Ruta para mostrar la foto
$foto_url = '';
if (!empty($cliente['foto'])) {
    $foto_url = 'uploads/clientes/' . $cliente['foto'];
}
?>
<!-- Incluir el CSS externo -->
<link rel="stylesheet" href="css/styleess.css">

<div class="container">
    <h2>Editar Cliente</h2>
    
    <?php if (isset($mensaje)): ?>
    <div class="alert <?php echo isset($tipo_mensaje) ? $tipo_mensaje : ''; ?>">
        <?php echo $mensaje; ?>
        <?php if (isset($error_foto)): ?>
        <br><small class="text-danger"><?php echo $error_foto; ?></small>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-12">
            <div class="form-container">
                    <form method="POST" enctype="multipart/form-data" onsubmit="return validarFormulario(event)">
                        <input type="hidden" name="foto_actual" value="<?php echo $cliente['foto'] ?? ''; ?>">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="nombre">Nombre:</label>
                                <input type="text" id="nombre" name="nombre" class="form-control"
                                       value="<?php echo htmlspecialchars($cliente['nombre']); ?>" required>
                            </div>

                            <div class="form-group">
                                <label for="telefono">Teléfono:</label>
                                <input type="text" id="telefono" name="telefono" class="form-control"
                                       value="<?php echo htmlspecialchars($cliente['telefono']); ?>" required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="direccion">Dirección:</label>
                                <input type="text" id="direccion" name="direccion" class="form-control"
                                       value="<?php echo htmlspecialchars($cliente['direccion']); ?>">
                            </div>

                            <div class="form-group">
                                <label for="email">Email:</label>
                                <input type="email" id="email" name="email" class="form-control"
                                       value="<?php echo htmlspecialchars($cliente['email']); ?>">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="ciudad">Ciudad:</label>
                                <input type="text" id="ciudad" name="ciudad" class="form-control"
                                       value="<?php echo htmlspecialchars($cliente['ciudad'] ?? ''); ?>">
                            </div>

                            <div class="form-group">
                                <label for="provincia">Provincia:</label>
                                <input type="text" id="provincia" name="provincia" class="form-control"
                                       value="<?php echo htmlspecialchars($cliente['provincia'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="pais">País:</label>
                                <input type="text" id="pais" name="pais" class="form-control"
                                       value="<?php echo htmlspecialchars($cliente['pais'] ?? ''); ?>">
                            </div>

                            <div class="form-group">
                                <label for="cuit">CUIT:</label>
                                <input type="text" id="cuit" name="cuit" class="form-control"
                                       value="<?php echo htmlspecialchars($cliente['cuit']); ?>"
                                       placeholder="XX-XXXXXXXX-X">
                            </div>

                            <div class="form-group">
                                <label for="codigo_postal">Código Postal:</label>
                                <input type="text" id="codigo_postal" name="codigo_postal" class="form-control"
                                       value="<?php echo htmlspecialchars($cliente['codigo_postal'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="observaciones">Observaciones:</label>
                            <textarea id="observaciones" name="observaciones" class="form-control"
                                      rows="4"><?php echo htmlspecialchars($cliente['observaciones'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="foto">Foto del Cliente:</label>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="photo-preview">
                                        <?php if (!empty($cliente['foto']) && file_exists('uploads/clientes/' . $cliente['foto'])): ?>
                                            <img src="<?php echo $foto_url; ?>" alt="Foto actual" 
                                                 class="img-thumbnail" id="current-photo">
                                        <?php else: ?>
                                            <div class="no-photo text-center">
                                                <i class="fas fa-user fa-3x text-muted"></i>
                                                <p class="small text-muted mt-2">Sin foto</p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-md-6 text-center">
                                    <h6>Código QR del Cliente</h6>
                                    <img src="<?php echo $qr_url; ?>" alt="Código QR" style="max-width: 160px; height: auto;">
                                    <p class="small text-muted">Escanea para datos del cliente</p>
                                </div>
                            </div>

                            <div class="row mt-3">
                                <div class="col-12">
                                    <input type="file" id="foto" name="foto" class="form-control" accept="image/*">
                                    <small class="text-muted">Formatos permitidos: JPG, PNG, GIF, WebP (Max. 2MB)</small>
                                    <?php if (!empty($cliente['foto'])): ?>
                                    <div class="form-check mt-2">
                                        <input type="checkbox" id="eliminar_foto" name="eliminar_foto" value="1" class="form-check-input">
                                        <label for="eliminar_foto" class="form-check-label">Eliminar foto actual</label>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group" style="align-self: flex-end;">
                                <button type="submit" name="actualizar_cliente" class="btn btn-primary">Actualizar Cliente</button>
                                <button type="button" class="btn btn-secondary" onclick="abrirHistorico()">Histórico</button>
                                <a href="clientes.php" class="btn btn-secondary">Cancelar</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="modalHistorico" class="modal" style="display:none;">
    <div class="modal-content" style="max-width: 600px; width: 95%;">
        <span class="close" onclick="cerrarHistorico()">&times;</span>
        <div id="historicoContenido">
            <p>Cargando histórico...</p>
        </div>
        <div style="margin-top: 15px; text-align: right;">
            <button type="button" class="btn btn-secondary" onclick="cerrarHistorico()">Cerrar</button>
        </div>
    </div>
</div>

<script>
function validarFormulario(event) {
    const nombre = document.getElementById('nombre').value.trim();
    const telefono = document.getElementById('telefono').value.trim();
    if (!nombre || !telefono) {
        event.preventDefault();
        alert('Por favor, complete el Nombre y el Teléfono antes de guardar.');
        return false;
    }
    return true;
}

function abrirHistorico() {
    const modal = document.getElementById('modalHistorico');
    const contenido = document.getElementById('historicoContenido');
    modal.style.display = 'block';
    contenido.innerHTML = '<p>Cargando histórico...</p>';

    fetch('historico_cliente.php?ajax=1&cliente_id=<?php echo (int)$cliente['id']; ?>')
        .then(response => response.text())
        .then(html => {
            contenido.innerHTML = html;
        })
        .catch(() => {
            contenido.innerHTML = '<p>No se pudo cargar el histórico.</p>';
        });
}

function cerrarHistorico() {
    const modal = document.getElementById('modalHistorico');
    modal.style.display = 'none';
}

// Previsualización de la nueva foto
document.getElementById('foto').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.getElementById('current-photo');
            if (preview) {
                preview.src = e.target.result;
            } else {
                const photoPreview = document.querySelector('.photo-preview');
                const noPhoto = document.querySelector('.no-photo');
                if (noPhoto) {
                    noPhoto.style.display = 'none';
                }
                const img = document.createElement('img');
                img.src = e.target.result;
                img.className = 'img-thumbnail';
                img.id = 'current-photo';
                img.style.maxWidth = '100%';
                photoPreview.appendChild(img);
            }
        }
        reader.readAsDataURL(file);
    }
});

window.addEventListener('click', function(e) {
    const modalHistorico = document.getElementById('modalHistorico');
    if (e.target === modalHistorico) {
        cerrarHistorico();
    }
});
</script>



<?php include 'includes/footer.php'; ?>