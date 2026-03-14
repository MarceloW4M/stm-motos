<?php
require_once 'includes/auth.php';
require_once 'includes/database.php';
require_once 'includes/header.php';

// Verificar autenticación
requireAuth();

// enlace al CSS especializado
?>
<link rel="stylesheet" href="css/styleess.css">
<?php

$database = new Database();
$db = $database->getConnection();

$mensaje = '';

// Obtener ID del repuesto a editar
$id = $_GET['id'] ?? 0;

// Obtener datos del repuesto
$repuesto = null;
if ($id) {
    $query = "SELECT * FROM repuestos WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    $repuesto = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Si no existe el repuesto, redirigir
if (!$repuesto) {
    header("Location: repuestos.php");
    exit();
}

// Procesar actualización
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = $_POST['nombre'];
    $descripcion = $_POST['descripcion'];
    $precio = $_POST['precio'];
    $stock = $_POST['stock'];
    
    try {
        $query = "UPDATE repuestos SET nombre = :nombre, descripcion = :descripcion, 
                  precio = :precio, stock = :stock WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':nombre', $nombre);
        $stmt->bindParam(':descripcion', $descripcion);
        $stmt->bindParam(':precio', $precio);
        $stmt->bindParam(':stock', $stock);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        $mensaje = "✅ Repuesto actualizado exitosamente";
        // Recargar datos
        $repuesto = array_merge($repuesto, $_POST);
    } catch (PDOException $e) {
        $mensaje = "❌ Error al actualizar repuesto: " . $e->getMessage();
    }
}
?>

<div class="container">
    <h2>Editar Repuesto</h2>
    
    <?php if ($mensaje): ?>
    <div class="alert"><?php echo $mensaje; ?></div>
    <?php endif; ?>
    
    <div class="form-container">
        <form method="POST">
            <div class="form-group">
                <label for="nombre">Nombre del Repuesto:</label>
                <input type="text" id="nombre" name="nombre" value="<?php echo htmlspecialchars($repuesto['nombre']); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="descripcion">Descripción:</label>
                <textarea id="descripcion" name="descripcion" rows="3"><?php echo htmlspecialchars($repuesto['descripcion']); ?></textarea>
            </div>
            
            <div class="form-group">
                <label for="precio">Precio ($):</label>
                <input type="number" id="precio" name="precio" step="0.01" min="0" 
                       value="<?php echo $repuesto['precio']; ?>" required>
            </div>
            
            <div class="form-group">
                <label for="stock">Stock:</label>
                <input type="number" id="stock" name="stock" min="0" 
                       value="<?php echo $repuesto['stock']; ?>" required>
            </div>
            
            <button type="submit" class="btn btn-primary">Actualizar Repuesto</button>
            <a href="repuestos.php" class="btn btn-secondary">Cancelar</a>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
