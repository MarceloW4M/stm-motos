<?php
require_once 'includes/auth.php';
require_once 'includes/database.php';
require_once 'includes/header.php';

// Verificar autenticación
requireAuth();

// enlace al CSS especializado de tablas y formularios
?>
<link rel="stylesheet" href="css/styleess.css">
<?php

$database = new Database();
$db = $database->getConnection();

$mensaje = '';

// Procesar formulario de agregar repuesto
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['agregar_repuesto'])) {
    $nombre = $_POST['nombre'];
    $descripcion = $_POST['descripcion'];
    $precio = $_POST['precio'];
    $stock = $_POST['stock'];
    
    try {
        $query = "INSERT INTO repuestos (nombre, descripcion, precio, stock) 
                  VALUES (:nombre, :descripcion, :precio, :stock)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':nombre', $nombre);
        $stmt->bindParam(':descripcion', $descripcion);
        $stmt->bindParam(':precio', $precio);
        $stmt->bindParam(':stock', $stock);
        $stmt->execute();
        
        $mensaje = "✅ Repuesto agregado exitosamente";
    } catch (PDOException $e) {
        $mensaje = "❌ Error al agregar repuesto: " . $e->getMessage();
    }
}

// Procesar eliminación de repuesto
if (isset($_GET['eliminar'])) {
    $id = $_GET['eliminar'];
    try {
        $query = "DELETE FROM repuestos WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        $mensaje = "✅ Repuesto eliminado exitosamente";
    } catch (PDOException $e) {
        $mensaje = "❌ Error al eliminar repuesto: " . $e->getMessage();
    }
}

// Procesar actualización de stock
if (isset($_POST['actualizar_stock'])) {
    $id = $_POST['id'];
    $nuevo_stock = $_POST['nuevo_stock'];
    
    try {
        $query = "UPDATE repuestos SET stock = :stock WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':stock', $nuevo_stock);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        $mensaje = "✅ Stock actualizado exitosamente";
    } catch (PDOException $e) {
        $mensaje = "❌ Error al actualizar stock: " . $e->getMessage();
    }
}

// Obtener lista de repuestos con búsqueda y paginación
$registros_por_pagina = 50;
$pagina_actual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($pagina_actual - 1) * $registros_por_pagina;

$busqueda = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';
$where = '';
$params = [];

if (!empty($busqueda)) {
    $where = "WHERE nombre LIKE :busqueda OR descripcion LIKE :busqueda";
    $params[':busqueda'] = "%$busqueda%";
}

// Obtener total de registros
$query_total = "SELECT COUNT(*) as total FROM repuestos $where";
$stmt_total = $db->prepare($query_total);
foreach ($params as $key => $value) {
    $stmt_total->bindValue($key, $value);
}
$stmt_total->execute();
$total_registros = $stmt_total->fetch(PDO::FETCH_ASSOC)['total'];
$total_paginas = ceil($total_registros / $registros_por_pagina);

// Obtener repuestos paginados
$query = "SELECT * FROM repuestos $where ORDER BY nombre LIMIT :limit OFFSET :offset";
$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $registros_por_pagina, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$repuestos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcular estadísticas
$total_repuestos = count($repuestos);
$stock_bajo = 0;

foreach ($repuestos as $repuesto) {
    if ($repuesto['stock'] < 5) $stock_bajo++;
}
?>

<div class="container">
    <h2>Gestión de Repuestos</h2>
    
    <?php if ($mensaje): ?>
    <div class="alert <?php echo strpos($mensaje,'✅')===0 ? 'success' : 'error'; ?>">
        <?php echo $mensaje; ?>
    </div>
    <?php endif; ?>
    
    <!-- búsqueda similar a clientes y vehículos -->
    <div class="search-container">
        <form method="GET" action="repuestos.php" class="search-form">
            <div class="search-box">
                <span class="search-icon">🔍</span>
                <input type="text" name="buscar" id="searchInput" placeholder="Buscar por nombre o descripción..." value="<?php echo htmlspecialchars($busqueda); ?>">
            </div>
            <button type="submit" class="btn-buscar">Buscar</button>
            <?php if (!empty($busqueda)): ?>
            <a href="repuestos.php" class="btn-limpiar">Limpiar</a>
            <?php endif; ?>
        </form>
        <div id="resultCount" class="result-count">
            Mostrando <?php echo count($repuestos); ?> de <?php echo $total_registros; ?> repuestos
        </div>
    </div>

    <!-- tabla con scroll -->
    <div class="table-wrapper-scroll">
        <?php if (count($repuestos) > 0): ?>
        <table class="table-fixed" id="repuestosTable">
            <thead>
                <tr>
                    <th class="th-id">ID</th>
                    <th class="th-nombre">Nombre</th>
                    <th class="th-descripcion">Descripción</th>
                    <th class="th-precio">Precio</th>
                    <th class="th-stock">Stock</th>
                    <th class="th-acciones">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($repuestos as $repuesto): 
                    $stock_class = '';
                    if ($repuesto['stock'] == 0) {
                        $stock_class = 'style="color: red; font-weight: bold;"';
                    } elseif ($repuesto['stock'] < 5) {
                        $stock_class = 'style="color: orange; font-weight: bold;"';
                    }
                ?>
                <tr>
                    <td class="td-id"><?php echo $repuesto['id']; ?></td>
                    <td class="td-nombre"><?php echo htmlspecialchars($repuesto['nombre']); ?></td>
                    <td class="td-descripcion"><?php echo htmlspecialchars($repuesto['descripcion']); ?></td>
                    <td class="td-precio">$<?php echo number_format($repuesto['precio'], 2); ?></td>
                    <td class="td-stock" <?php echo $stock_class; ?>>
                        <?php echo $repuesto['stock']; ?>
                        <?php if ($repuesto['stock'] == 0): ?>
                            (Agotado)
                        <?php elseif ($repuesto['stock'] < 5): ?>
                            (Stock Bajo)
                        <?php endif; ?>
                    </td>
                    <td class="td-acciones">
                        <div class="acciones-container">
                            <form method="POST" style="display: inline-flex; gap: 5px; align-items: center;">
                                <input type="hidden" name="id" value="<?php echo $repuesto['id']; ?>">
                                <input type="number" name="nuevo_stock" value="<?php echo $repuesto['stock']; ?>" min="0" style="width: 70px; padding: 5px;">
                                <button type="submit" name="actualizar_stock" class="btn btn-secondary btn-sm">Actualizar</button>
                            </form>
                            <a href="editar_repuesto.php?id=<?php echo $repuesto['id']; ?>" class="btn btn-primary btn-sm">Editar</a>
                            <a href="repuestos.php?eliminar=<?php echo $repuesto['id']; ?>&pagina=<?php echo $pagina_actual; ?>&buscar=<?php echo urlencode($busqueda); ?>" class="btn btn-danger btn-sm" onclick="return confirm('¿Está seguro de eliminar este repuesto?')">Eliminar</a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="no-results">
            🔍<br>
            No se encontraron repuestos<?php echo !empty($busqueda) ? " para '{$busqueda}'" : ''; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- paginación -->
    <?php if ($total_paginas > 1): ?>
    <div class="pagination">
        <a href="repuestos.php?pagina=<?php echo max(1,$pagina_actual-1); ?>&buscar=<?php echo urlencode($busqueda); ?>" class="btn" <?php echo $pagina_actual<=1?'disabled':'';?>><i class="fas fa-chevron-left"></i> Anterior</a>
        <div class="pagination-info">Página <?php echo $pagina_actual; ?> de <?php echo $total_paginas; ?></div>
        <div class="pagination-numbers">
            <?php
            $inicio = max(1,$pagina_actual-2);
            $fin = min($total_paginas,$pagina_actual+2);
            if ($inicio>1){
                echo '<a href="repuestos.php?pagina=1&buscar='.urlencode($busqueda).'" class="pagination-number">1</a>';
                if ($inicio>2) echo '<span>...</span>';
            }
            for($i=$inicio;$i<=$fin;$i++){
                $active = $i==$pagina_actual?'active':'';
                echo '<a href="repuestos.php?pagina='.$i.'&buscar='.urlencode($busqueda).'" class="pagination-number '.$active.'">'.$i.'</a>';
            }
            if ($fin<$total_paginas){
                if ($fin<$total_paginas-1) echo '<span>...</span>';
                echo '<a href="repuestos.php?pagina='.$total_paginas.'&buscar='.urlencode($busqueda).'" class="pagination-number">'.$total_paginas.'</a>';
            }
            ?>
        </div>
        <a href="repuestos.php?pagina=<?php echo min($total_paginas,$pagina_actual+1); ?>&buscar=<?php echo urlencode($busqueda); ?>" class="btn" <?php echo $pagina_actual>=$total_paginas?'disabled':'';?>>Siguiente <i class="fas fa-chevron-right"></i></a>
    </div>
    <?php endif; ?>

    <!-- formulario de alta (al final) -->
    <div class="form-container">
        <h3>Agregar Nuevo Repuesto</h3>
        <form method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label for="nombre">Nombre del Repuesto:</label>
                    <input type="text" id="nombre" name="nombre" required>
                </div>
                <div class="form-group">
                    <label for="precio">Precio ($):</label>
                    <input type="number" id="precio" name="precio" step="0.01" min="0" required>
                </div>
                <div class="form-group">
                    <label for="stock">Stock Inicial:</label>
                    <input type="number" id="stock" name="stock" min="0" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="descripcion">Descripción:</label>
                    <textarea id="descripcion" name="descripcion" rows="3"></textarea>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group" style="align-self: flex-end;">
                    <button type="submit" name="agregar_repuesto" class="btn btn-primary">Agregar Repuesto</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
