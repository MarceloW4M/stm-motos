<?php
require_once 'includes/auth.php';
require_once 'includes/database.php';
require_once 'includes/header.php';

requireAuth();
?>
<link rel="stylesheet" href="css/styleess.css">
<?php

$database = new Database();
$db = $database->getConnection();
$mensaje = '';

// Garantiza estructura minima para entornos nuevos o incompletos.
$db->exec("CREATE TABLE IF NOT EXISTS servicios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT,
    precio_estimado DECIMAL(10,2) NOT NULL DEFAULT 0,
    duracion_estimada INT NOT NULL DEFAULT 60,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$editando = false;
$servicioEditar = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['agregar_servicio'])) {
    $nombre = trim((string)($_POST['nombre'] ?? ''));
    $descripcion = trim((string)($_POST['descripcion'] ?? ''));
    $precio = (float)($_POST['precio_estimado'] ?? 0);
    $duracion = (int)($_POST['duracion_estimada'] ?? 60);
    $activo = isset($_POST['activo']) ? 1 : 0;

    if ($nombre === '') {
        $mensaje = '❌ El nombre del servicio es obligatorio.';
    } else {
        try {
            $query = 'INSERT INTO servicios (nombre, descripcion, precio_estimado, duracion_estimada, activo)
                      VALUES (:nombre, :descripcion, :precio, :duracion, :activo)';
            $stmt = $db->prepare($query);
            $stmt->bindValue(':nombre', $nombre);
            $stmt->bindValue(':descripcion', $descripcion);
            $stmt->bindValue(':precio', $precio);
            $stmt->bindValue(':duracion', max(1, $duracion), PDO::PARAM_INT);
            $stmt->bindValue(':activo', $activo, PDO::PARAM_INT);
            $stmt->execute();
            $mensaje = '✅ Servicio agregado exitosamente.';
        } catch (PDOException $e) {
            $mensaje = '❌ Error al agregar servicio: ' . $e->getMessage();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar_servicio'])) {
    $id = (int)($_POST['id'] ?? 0);
    $nombre = trim((string)($_POST['nombre'] ?? ''));
    $descripcion = trim((string)($_POST['descripcion'] ?? ''));
    $precio = (float)($_POST['precio_estimado'] ?? 0);
    $duracion = (int)($_POST['duracion_estimada'] ?? 60);
    $activo = isset($_POST['activo']) ? 1 : 0;

    if ($id <= 0 || $nombre === '') {
        $mensaje = '❌ Datos invalidos para actualizar el servicio.';
    } else {
        try {
            $query = 'UPDATE servicios
                      SET nombre = :nombre,
                          descripcion = :descripcion,
                          precio_estimado = :precio,
                          duracion_estimada = :duracion,
                          activo = :activo
                      WHERE id = :id';
            $stmt = $db->prepare($query);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->bindValue(':nombre', $nombre);
            $stmt->bindValue(':descripcion', $descripcion);
            $stmt->bindValue(':precio', $precio);
            $stmt->bindValue(':duracion', max(1, $duracion), PDO::PARAM_INT);
            $stmt->bindValue(':activo', $activo, PDO::PARAM_INT);
            $stmt->execute();
            $mensaje = '✅ Servicio actualizado exitosamente.';
        } catch (PDOException $e) {
            $mensaje = '❌ Error al actualizar servicio: ' . $e->getMessage();
        }
    }
}

if (isset($_GET['eliminar'])) {
    $idEliminar = (int)$_GET['eliminar'];
    if ($idEliminar > 0) {
        try {
            $query = 'DELETE FROM servicios WHERE id = :id';
            $stmt = $db->prepare($query);
            $stmt->bindValue(':id', $idEliminar, PDO::PARAM_INT);
            $stmt->execute();
            $mensaje = '✅ Servicio eliminado exitosamente.';
        } catch (PDOException $e) {
            $mensaje = '❌ Error al eliminar servicio: ' . $e->getMessage();
        }
    }
}

$idEditar = isset($_GET['editar']) ? (int)$_GET['editar'] : 0;
if ($idEditar > 0) {
    $stmtEdit = $db->prepare('SELECT * FROM servicios WHERE id = :id LIMIT 1');
    $stmtEdit->bindValue(':id', $idEditar, PDO::PARAM_INT);
    $stmtEdit->execute();
    $servicioEditar = $stmtEdit->fetch(PDO::FETCH_ASSOC) ?: null;
    $editando = $servicioEditar !== null;
}

$registros_por_pagina = 50;
$pagina_actual = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
$offset = ($pagina_actual - 1) * $registros_por_pagina;
$busqueda = isset($_GET['buscar']) ? trim((string)$_GET['buscar']) : '';

$where = '';
$params = [];
if ($busqueda !== '') {
    $where = 'WHERE nombre LIKE :buscar OR descripcion LIKE :buscar';
    $params[':buscar'] = '%' . $busqueda . '%';
}

$queryTotal = "SELECT COUNT(*) FROM servicios $where";
$stmtTotal = $db->prepare($queryTotal);
foreach ($params as $k => $v) {
    $stmtTotal->bindValue($k, $v);
}
$stmtTotal->execute();
$total_registros = (int)$stmtTotal->fetchColumn();
$total_paginas = max(1, (int)ceil($total_registros / $registros_por_pagina));

$queryListado = "SELECT * FROM servicios $where ORDER BY nombre ASC LIMIT :limit OFFSET :offset";
$stmtListado = $db->prepare($queryListado);
foreach ($params as $k => $v) {
    $stmtListado->bindValue($k, $v);
}
$stmtListado->bindValue(':limit', $registros_por_pagina, PDO::PARAM_INT);
$stmtListado->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmtListado->execute();
$servicios = $stmtListado->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container">
    <h2>Gestión de Servicios</h2>

    <?php if ($mensaje): ?>
    <div class="alert <?php echo strpos($mensaje, '✅') === 0 ? 'success' : 'error'; ?>">
        <?php echo $mensaje; ?>
    </div>
    <?php endif; ?>

    <div class="search-container">
        <form method="GET" action="servicios.php" class="search-form" style="display: flex; gap: 10px; width: 100%;">
            <input type="text" name="buscar" placeholder="Buscar por nombre o descripción..." value="<?php echo htmlspecialchars($busqueda, ENT_QUOTES, 'UTF-8'); ?>">
            <button type="submit" class="btn btn-primary">Buscar</button>
            <?php if ($busqueda !== ''): ?>
                <a href="servicios.php" class="btn btn-secondary">Limpiar</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="table-wrapper-scroll">
        <?php if (!empty($servicios)): ?>
        <table class="table-fixed">
            <thead>
                <tr>
                    <th class="th-id">ID</th>
                    <th>Nombre</th>
                    <th>Descripción</th>
                    <th class="th-precio">Precio Estimado</th>
                    <th>Duración (min)</th>
                    <th class="th-acciones">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($servicios as $servicio): ?>
                <tr>
                    <td class="td-id"><?php echo (int)$servicio['id']; ?></td>
                    <td><?php echo htmlspecialchars((string)$servicio['nombre'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars((string)$servicio['descripcion'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td class="td-precio">$<?php echo number_format((float)$servicio['precio_estimado'], 2, ',', '.'); ?></td>
                    <td><?php echo (int)$servicio['duracion_estimada']; ?></td>
                    <td class="td-acciones">
                        <a href="servicios.php?editar=<?php echo (int)$servicio['id']; ?>&pagina=<?php echo $pagina_actual; ?>&buscar=<?php echo urlencode($busqueda); ?>" class="btn btn-primary btn-sm">Editar</a>
                        <a href="servicios.php?eliminar=<?php echo (int)$servicio['id']; ?>&pagina=<?php echo $pagina_actual; ?>&buscar=<?php echo urlencode($busqueda); ?>" class="btn btn-danger btn-sm" onclick="return confirm('¿Está seguro de eliminar este servicio?')">Eliminar</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="no-results">
            No se encontraron servicios<?php echo $busqueda !== '' ? " para '{$busqueda}'" : ''; ?>.
        </div>
        <?php endif; ?>
    </div>

    <?php if ($total_paginas > 1): ?>
    <div class="pagination">
        <a href="servicios.php?pagina=<?php echo max(1, $pagina_actual - 1); ?>&buscar=<?php echo urlencode($busqueda); ?>" class="btn" <?php echo $pagina_actual <= 1 ? 'disabled' : ''; ?>>Anterior</a>
        <div class="pagination-info">Página <?php echo $pagina_actual; ?> de <?php echo $total_paginas; ?></div>
        <a href="servicios.php?pagina=<?php echo min($total_paginas, $pagina_actual + 1); ?>&buscar=<?php echo urlencode($busqueda); ?>" class="btn" <?php echo $pagina_actual >= $total_paginas ? 'disabled' : ''; ?>>Siguiente</a>
    </div>
    <?php endif; ?>

    <div class="form-container" style="margin-top: 20px;">
        <h3><?php echo $editando ? 'Editar Servicio' : 'Agregar Nuevo Servicio'; ?></h3>
        <form method="POST">
            <?php if ($editando): ?>
                <input type="hidden" name="id" value="<?php echo (int)$servicioEditar['id']; ?>">
            <?php endif; ?>

            <div class="form-row">
                <div class="form-group">
                    <label for="nombre">Nombre del servicio:</label>
                    <input type="text" id="nombre" name="nombre" required value="<?php echo htmlspecialchars((string)($servicioEditar['nombre'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="form-group">
                    <label for="precio_estimado">Precio estimado ($):</label>
                    <input type="number" id="precio_estimado" name="precio_estimado" min="0" step="0.01" required value="<?php echo htmlspecialchars((string)($servicioEditar['precio_estimado'] ?? '0.00'), ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="form-group">
                    <label for="duracion_estimada">Duración estimada (min):</label>
                    <input type="number" id="duracion_estimada" name="duracion_estimada" min="1" step="1" required value="<?php echo htmlspecialchars((string)($servicioEditar['duracion_estimada'] ?? '60'), ENT_QUOTES, 'UTF-8'); ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="descripcion">Descripción:</label>
                    <textarea id="descripcion" name="descripcion" rows="3"><?php echo htmlspecialchars((string)($servicioEditar['descripcion'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>
                <div class="form-group" style="max-width: 180px; align-self: flex-end;">
                    <label style="display: inline-flex; align-items: center; gap: 8px; font-weight: normal;">
                        <input type="checkbox" name="activo" <?php echo !isset($servicioEditar['activo']) || (int)$servicioEditar['activo'] === 1 ? 'checked' : ''; ?>>
                        Servicio activo
                    </label>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group" style="align-self: flex-end; display: flex; gap: 8px;">
                    <button type="submit" name="<?php echo $editando ? 'actualizar_servicio' : 'agregar_servicio'; ?>" class="btn btn-primary">
                        <?php echo $editando ? 'Actualizar Servicio' : 'Agregar Servicio'; ?>
                    </button>
                    <?php if ($editando): ?>
                        <a href="servicios.php" class="btn btn-secondary">Cancelar</a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
