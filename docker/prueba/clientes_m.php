<?php
require_once 'includes/auth.php';
require_once 'includes/database.php';
require_once 'includes/header.php';

// Verificar autenticación
requireAuth();

$database = new Database();
$db = $database->getConnection();

// Obtener el ID del cliente desde la URL
$cliente_id = isset($_GET['id']) ? $_GET['id'] : 0;

// Procesar actualización de datos
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar_cliente'])) {
    $nombre = $_POST['nombre'];
    $telefono = $_POST['telefono'];
    $email = $_POST['email'];
    $direccion = $_POST['direccion'];
    $cuit = $_POST['cuit'];
    $ciudad = $_POST['ciudad'];
    $provincia = $_POST['provincia'];
    $pais = $_POST['pais'];
    $codigo_postal = $_POST['codigo_postal'];
    $observaciones = $_POST['observaciones'];
    $fechaini = $_POST['created_at'];
    
    // Procesar la foto si se subió una
    $foto_path = $_POST['foto_actual']; // Mantener la foto actual por defecto
    
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] == 0) {
        $foto = $_FILES['foto'];
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        
        // Verificar tamaño máximo (2MB)
        $max_size = 2 * 1024 * 1024; // 2MB en bytes
        
        if (in_array($foto['type'], $allowed_types) && $foto['size'] <= $max_size) {
            $upload_dir = 'uploads/clientes/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Generar nombre único para la foto
            $extension = pathinfo($foto['name'], PATHINFO_EXTENSION);
            $filename = 'cliente_' . $cliente_id . '_' . time() . '.' . $extension;
            $filepath = $upload_dir . $filename;
            
            if (move_uploaded_file($foto['tmp_name'], $filepath)) {
                $foto_path = $filename; // Guardar solo el nombre del archivo
                
                // Si había una foto anterior, eliminarla
                if (!empty($_POST['foto_actual']) && $_POST['foto_actual'] != '') {
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
    if (isset($_POST['eliminar_foto']) && $_POST['eliminar_foto'] == '1' && !empty($_POST['foto_actual'])) {
        $old_file = 'uploads/clientes/' . $_POST['foto_actual'];
        if (file_exists($old_file)) {
            unlink($old_file);
        }
        $foto_path = '';
    }
    
    // Actualizar en la base de datos
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
    $stmt->bindParam(':id', $cliente_id);
    
    if ($stmt->execute()) {
        $mensaje = "Cliente actualizado correctamente";
        $tipo_mensaje = "success";
        
        // Recargar los datos del cliente para mostrar la foto actualizada
        $query = "SELECT * FROM clientes WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $cliente_id);
        $stmt->execute();
        $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $mensaje = "Error al actualizar el cliente";
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

<div class="container">
    <!-- Encabezado con QR a la derecha -->
    <div class="row mb-4 align-items-center">
        <div class="col-md-8">
            <h2>Ficha Detallada del Cliente</h2>
        </div>
        <div class="col-md-4">
            <div class="qr-header-wrapper">
                <div class="qr-header">
                    <div class="qr-container">
                        <img src="<?php echo $qr_url; ?>" alt="Código QR" class="qr-img">
                        <div class="qr-overlay">                            
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php if (isset($mensaje)): ?>
    <div class="alert alert-<?php echo $tipo_mensaje; ?>">
        <?php echo $mensaje; ?>
        <?php if (isset($error_foto)): ?>
        <br><small class="text-danger"><?php echo $error_foto; ?></small>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <div class="row">
        <!-- Columna izquierda: Formulario de datos -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">                    
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="foto_actual" value="<?php echo $cliente['foto'] ?? ''; ?>">
                        
                        <div class="row">
                            <div class="col-md-6">
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
                                
                                <div class="form-group">
                                    <label for="email">Email:</label>
                                    <input type="email" id="email" name="email" class="form-control" 
                                           value="<?php echo htmlspecialchars($cliente['email']); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="cuit">CUIT:</label>
                                    <input type="text" id="cuit" name="cuit" class="form-control" 
                                           value="<?php echo htmlspecialchars($cliente['cuit']); ?>" 
                                           placeholder="XX-XXXXXXXX-X">
                                </div>
                            </div>
                            
                            <div class="col-md-6">
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
                                
                                <div class="form-group">
                                    <label for="pais">País:</label>
                                    <input type="text" id="pais" name="pais" class="form-control" 
                                           value="<?php echo htmlspecialchars($cliente['pais'] ?? ''); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="codigo_postal">Código Postal:</label>
                                    <input type="text" id="codigo_postal" name="codigo_postal" class="form-control" 
                                           value="<?php echo htmlspecialchars($cliente['codigo_postal'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="direccion">Dirección:</label>
                            <input type="text" id="direccion" name="direccion" class="form-control" 
                                   value="<?php echo htmlspecialchars($cliente['direccion']); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="observaciones">Observaciones:</label>
                            <textarea id="observaciones" name="observaciones" class="form-control" 
                                      rows="4"><?php echo htmlspecialchars($cliente['observaciones'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="foto">Foto del Cliente:</label>
                            <div class="row">
                                <div class="col-md-4">
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
                                <div class="col-md-8">
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
                        
                        <div class="form-group mt-4">
                            <button type="submit" name="actualizar_cliente" class="btn btn-primary">
                                <i class="fas fa-save"></i> Actualizar Datos
                            </button>
                            <a href="clientes.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Volver a la Lista
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Información adicional -->
            <div class="card mt-3">
                <div class="card-header">
                    <h4>Información del Sistema</h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>ID Cliente:</strong> <?php echo $cliente['id']; ?></p>
                            <?php if (isset($cliente['fecha_creacion'])): ?>
                            <p><strong>Fecha de creación:</strong> <?php echo $cliente['fecha_creacion']; ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <?php if (isset($cliente['fecha_actualizacion'])): ?>
                            <p><strong>Última actualización:</strong> <?php echo $cliente['fecha_actualizacion']; ?></p>
                            <?php endif; ?>
                            <?php if (!empty($cliente['foto'])): ?>
                            <p><strong>Archivo de foto:</strong> <?php echo $cliente['foto']; ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Columna derecha: Información de contacto rápida -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h4>Contacto Rápido</h4>
                </div>
                <div class="card-body">
                    <div class="contact-info">
                        <p><strong><i class="fas fa-phone"></i> Teléfono:</strong><br>
                        <?php echo $cliente['telefono']; ?></p>
                        
                        <p><strong><i class="fas fa-envelope"></i> Email:</strong><br>
                        <?php echo $cliente['email'] ? $cliente['email'] : '<span class="text-muted">No especificado</span>'; ?></p>
                        
                        <p><strong><i class="fas fa-map-marker-alt"></i> Dirección:</strong><br>
                        <?php echo $cliente['direccion']; ?></p>
                        
                        <?php if ($cliente['ciudad'] || $cliente['provincia']): ?>
                        <p><strong><i class="fas fa-city"></i> Localidad:</strong><br>
                        <?php 
                            $localidad = [];
                            if ($cliente['ciudad']) $localidad[] = $cliente['ciudad'];
                            if ($cliente['provincia']) $localidad[] = $cliente['provincia'];
                            echo implode(', ', $localidad);
                        ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Espacio para notas rápidas o acciones -->
                    <div class="mt-3">
                        <a href="tel:<?php echo $cliente['telefono']; ?>" class="btn btn-success btn-sm">
                            <i class="fas fa-phone"></i> Llamar
                        </a>
                        <?php if ($cliente['email']): ?>
                        <a href="mailto:<?php echo $cliente['email']; ?>" class="btn btn-info btn-sm">
                            <i class="fas fa-envelope"></i> Email
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Vista previa de la foto completa -->
            <?php if (!empty($cliente['foto']) && file_exists('uploads/clientes/' . $cliente['foto'])): ?>
            <div class="card mt-3">
                <div class="card-header">
                    <h4>Foto Completa</h4>
                </div>
                <div class="card-body text-center">
                    <a href="<?php echo $foto_url; ?>" target="_blank" class="photo-full-link">
                        <img src="<?php echo $foto_url; ?>" alt="Foto completa" 
                             class="img-fluid rounded photo-full-size">
                        <p class="small text-muted mt-2">Click para ver tamaño completo</p>
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function descargarQR() {
    const qrImg = document.querySelector('.qr-img');
    const link = document.createElement('a');
    link.href = qrImg.src;
    link.download = 'qr_cliente_<?php echo $cliente["id"]; ?>.png';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
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

// Mostrar/ocultar foto completa
document.querySelectorAll('.photo-full-link').forEach(link => {
    link.addEventListener('click', function(e) {
        if (!confirm('¿Abrir la foto en una nueva pestaña?')) {
            e.preventDefault();
        }
    });
});
</script>

<style>
.container {
    padding: 20px;
    position: relative;
}

.qr-header-wrapper {
    display: flex;
    justify-content: flex-end;
}

.qr-header {
    display: inline-block;
    text-align: center;
    background: white;
    padding: 10px;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    border: 1px solid #e9ecef;
    position: relative;
}

.qr-container {
    position: relative;
    display: inline-block;
}

.qr-img {
    width: 100px;
    height: 100px;
    display: block;
}

.qr-overlay {
    position: absolute;
    top: 5px;
    right: 5px;
    opacity: 0;
    transition: opacity 0.3s;
    z-index: 10;
}

.qr-container:hover .qr-overlay {
    opacity: 1;
}

.qr-caption {
    font-size: 0.8rem;
    color: #6c757d;
    margin-top: 5px !important;
    margin-bottom: 0;
}

.photo-preview {
    padding: 10px;
    background: #f8f9fa;
    border-radius: 4px;
    text-align: center;
    height: 150px;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    overflow: hidden;
}

.photo-preview img {
    max-height: 130px;
    max-width: 100%;
    object-fit: contain;
}

.no-photo {
    color: #999;
    position: relative;
}

.photo-full-link {
    display: block;
    text-decoration: none;
    color: inherit;
    position: relative;
}

.photo-full-link:hover {
    opacity: 0.9;
}

.photo-full-size {
    max-height: 180px;
    width: auto;
    object-fit: contain;
}

.contact-info p {
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
    position: relative;
}

.contact-info p:last-child {
    border-bottom: none;
}

.card {
    margin-bottom: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    border: none;
    position: relative;
}

.card-header {
    background-color: #f8f9fa;
    border-bottom: 1px solid #e9ecef;
    font-weight: 600;
    position: relative;
}

.form-group {
    margin-bottom: 15px;
    position: relative;
}

.form-control {
    border-radius: 4px;
    border: 1px solid #ced4da;
    position: relative;
}

.btn {
    border-radius: 4px;
    padding: 8px 16px;
    position: relative;
}

.alert {
    padding: 12px;
    margin-bottom: 20px;
    border-radius: 4px;
    border: 1px solid transparent;
    position: relative;
}

.alert-success {
    background-color: #d4edda;
    border-color: #c3e6cb;
    color: #155724;
}

.alert-error {
    background-color: #f8d7da;
    border-color: #f5c6cb;
    color: #721c24;
}

.row.mb-4.align-items-center {
    align-items: center !important;
}

.text-end {
    text-align: right !important;
}
</style>

<?php include 'includes/footer.php'; ?>