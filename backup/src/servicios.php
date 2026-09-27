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
$formError = '';
$showModalOnLoad = false;
$modalMode = 'nuevo';
$formData = [
    'id' => '',
    'nombre' => '',
    'descripcion' => '',
    'precio_estimado' => '0.00',
    'duracion_estimada' => '60',
    'activo' => 1
];

$db->exec("CREATE TABLE IF NOT EXISTS servicios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT,
    precio_estimado DECIMAL(10,2) NOT NULL DEFAULT 0,
    duracion_estimada INT NOT NULL DEFAULT 60,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_servicio'])) {
    $servicioId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    $nombre = mb_strtoupper(trim(filter_input(INPUT_POST, 'nombre', FILTER_UNSAFE_RAW) ?? ''), 'UTF-8');
    $descripcion = mb_strtoupper(trim(filter_input(INPUT_POST, 'descripcion', FILTER_UNSAFE_RAW) ?? ''), 'UTF-8');
    $precio = trim(filter_input(INPUT_POST, 'precio_estimado', FILTER_UNSAFE_RAW) ?? '');
    $duracion = filter_input(INPUT_POST, 'duracion_estimada', FILTER_VALIDATE_INT, ['options' => ['default' => 0]]);
    $activo = isset($_POST['activo']) ? 1 : 0;

    $pagina_post = filter_input(INPUT_POST, 'pagina', FILTER_VALIDATE_INT);
    $buscar_post = trim(filter_input(INPUT_POST, 'buscar', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '');

    $activo = ($servicioId !== null && $servicioId !== false && $servicioId > 0)
        ? filter_input(INPUT_POST, 'activo', FILTER_VALIDATE_INT, ['options' => ['default' => 1]])
        : 1;

    $formData = [
        'id' => $servicioId ?: '',
        'nombre' => $nombre,
        'descripcion' => $descripcion,
        'precio_estimado' => $precio,
        'duracion_estimada' => $duracion > 0 ? $duracion : '60',
        'activo' => $activo
    ];
    $modalMode = ($servicioId !== null && $servicioId !== false && $servicioId > 0) ? 'editar' : 'nuevo';

    if ($nombre === '' || $precio === '' || $duracion <= 0) {
        $formError = 'Nombre, Precio y Duración son obligatorios.';
        $showModalOnLoad = true;
    } else {
        try {
            if ($servicioId !== null && $servicioId !== false && $servicioId > 0) {
                $query = 'UPDATE servicios SET nombre = :nombre, descripcion = :descripcion, precio_estimado = :precio, duracion_estimada = :duracion, activo = :activo WHERE id = :id';
                $stmt = $db->prepare($query);
                $stmt->bindValue(':id', $servicioId, PDO::PARAM_INT);
            } else {
                $query = 'INSERT INTO servicios (nombre, descripcion, precio_estimado, duracion_estimada, activo) VALUES (:nombre, :descripcion, :precio, :duracion, :activo)';
                $stmt = $db->prepare($query);
            }

            $stmt->bindValue(':nombre', $nombre);
            $stmt->bindValue(':descripcion', $descripcion);
            $stmt->bindValue(':precio', $precio);
            $stmt->bindValue(':duracion', max(1, $duracion), PDO::PARAM_INT);
            $stmt->bindValue(':activo', $activo, PDO::PARAM_INT);
            $stmt->execute();

            $redirectUrl = 'servicios.php';
            $params = [];
            if ($buscar_post !== '') {
                $params[] = 'buscar=' . urlencode($buscar_post);
            }
            if ($pagina_post !== null && $pagina_post !== false && $pagina_post > 0) {
                $params[] = 'pagina=' . urlencode($pagina_post);
            }
            if (count($params) > 0) {
                $redirectUrl .= '?' . implode('&', $params);
            }
            header('Location: ' . $redirectUrl);
            exit();
        } catch (PDOException $e) {
            $formError = 'Error al guardar servicio: ' . $e->getMessage();
            $showModalOnLoad = true;
        }
    }
}

if (isset($_GET['eliminar'])) {
    $idEliminar = filter_input(INPUT_GET, 'eliminar', FILTER_VALIDATE_INT);
    $pagina_post = filter_input(INPUT_GET, 'pagina', FILTER_VALIDATE_INT);
    $buscar_post = trim(filter_input(INPUT_GET, 'buscar', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '');

    if ($idEliminar !== null && $idEliminar !== false && $idEliminar > 0) {
        try {
            $query = 'DELETE FROM servicios WHERE id = :id';
            $stmt = $db->prepare($query);
            $stmt->bindValue(':id', $idEliminar, PDO::PARAM_INT);
            $stmt->execute();
        } catch (PDOException $e) {
            $mensaje = '❌ Error al eliminar servicio: ' . $e->getMessage();
        }
    }

    $redirectUrl = 'servicios.php';
    $params = [];
    if ($buscar_post !== '') {
        $params[] = 'buscar=' . urlencode($buscar_post);
    }
    if ($pagina_post !== null && $pagina_post !== false && $pagina_post > 0) {
        $params[] = 'pagina=' . urlencode($pagina_post);
    }
    if (count($params) > 0) {
        $redirectUrl .= '?' . implode('&', $params);
    }
    header('Location: ' . $redirectUrl);
    exit();
}

$idEditar = filter_input(INPUT_GET, 'editar', FILTER_VALIDATE_INT);
if ($idEditar !== null && $idEditar !== false && $idEditar > 0) {
    $stmtEdit = $db->prepare('SELECT * FROM servicios WHERE id = :id LIMIT 1');
    $stmtEdit->bindValue(':id', $idEditar, PDO::PARAM_INT);
    $stmtEdit->execute();
    $servicio = $stmtEdit->fetch(PDO::FETCH_ASSOC);
    if ($servicio) {
        $formData = [
            'id' => $servicio['id'],
            'nombre' => $servicio['nombre'],
            'descripcion' => $servicio['descripcion'],
            'precio_estimado' => $servicio['precio_estimado'],
            'duracion_estimada' => $servicio['duracion_estimada'],
            'activo' => $servicio['activo']
        ];
        $modalMode = 'editar';
        $showModalOnLoad = true;
    }
}

$registros_por_pagina = 50;
$pagina_actual = filter_input(INPUT_GET, 'pagina', FILTER_VALIDATE_INT);
$pagina_actual = ($pagina_actual !== null && $pagina_actual !== false && $pagina_actual > 0) ? $pagina_actual : 1;
$offset = ($pagina_actual - 1) * $registros_por_pagina;
$busqueda = trim(filter_input(INPUT_GET, 'buscar', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '');

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
        <form method="GET" action="servicios.php" class="search-form">
            <div class="search-box">
                <i class="fas fa-search search-icon"></i>
                <input type="text" name="buscar" placeholder="Buscar por nombre o descripción..." value="<?php echo htmlspecialchars($busqueda, ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <button type="submit" class="btn btn-primary">Buscar</button>
            <button type="button" id="nuevoServicioButton" class="btn btn-secondary">Nuevo</button>
            <?php if ($busqueda !== ''): ?>
            <a href="servicios.php" class="btn btn-secondary">Limpiar</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="table-wrapper-scroll">
        <?php if (count($servicios) > 0): ?>
        <table class="table-fixed" id="serviciosTable">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Descripción</th>
                    <th class="th-precio">Precio Estimado</th>
                    <th>Duración (min)</th>
                    <th class="th-acciones">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($servicios as $servicio): ?>
                <tr data-id="<?php echo (int)$servicio['id']; ?>"
                    data-nombre="<?php echo htmlspecialchars($servicio['nombre'], ENT_QUOTES); ?>"
                    data-descripcion="<?php echo htmlspecialchars($servicio['descripcion'], ENT_QUOTES); ?>"
                    data-precio="<?php echo htmlspecialchars($servicio['precio_estimado'], ENT_QUOTES); ?>"
                    data-duracion="<?php echo (int)$servicio['duracion_estimada']; ?>">
                    <td><?php echo htmlspecialchars($servicio['nombre']); ?></td>
                    <td><?php echo htmlspecialchars($servicio['descripcion']); ?></td>
                    <td class="td-precio">$<?php echo number_format((float)$servicio['precio_estimado'], 2, ',', '.'); ?></td>
                    <td><?php echo (int)$servicio['duracion_estimada']; ?></td>
                    <td class="td-acciones">
                        <div class="acciones-container">
                            <button type="button" class="btn btn-primary btn-sm editar-btn">Editar</button>
                            <a href="servicios.php?eliminar=<?php echo (int)$servicio['id']; ?>&pagina=<?php echo $pagina_actual; ?>&buscar=<?php echo urlencode($busqueda); ?>" class="btn btn-danger btn-sm" onclick="return confirm('¿Está seguro de eliminar este servicio?')">Eliminar</a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="no-results">
            🔍<br>
            No se encontraron servicios<?php echo $busqueda !== '' ? " para '{$busqueda}'" : ''; ?>.
        </div>
        <?php endif; ?>
    </div>

    <?php if ($total_paginas > 1): ?>
    <div class="pagination">
        <a href="servicios.php?pagina=<?php echo max(1, $pagina_actual - 1); ?>&buscar=<?php echo urlencode($busqueda); ?>" class="btn" <?php echo $pagina_actual <= 1 ? 'disabled' : ''; ?>><i class="fas fa-chevron-left"></i> Anterior</a>
        <div class="pagination-info">Página <?php echo $pagina_actual; ?> de <?php echo $total_paginas; ?></div>
        <div class="pagination-numbers">
            <?php
            $inicio = max(1, $pagina_actual - 2);
            $fin = min($total_paginas, $pagina_actual + 2);
            if ($inicio > 1) {
                echo '<a href="servicios.php?pagina=1&buscar=' . urlencode($busqueda) . '" class="pagination-number">1</a>';
                if ($inicio > 2) echo '<span>...</span>';
            }
            for ($i = $inicio; $i <= $fin; $i++) {
                $active = $i == $pagina_actual ? 'active' : '';
                echo '<a href="servicios.php?pagina=' . $i . '&buscar=' . urlencode($busqueda) . '" class="pagination-number ' . $active . '">' . $i . '</a>';
            }
            if ($fin < $total_paginas) {
                if ($fin < $total_paginas - 1) echo '<span>...</span>';
                echo '<a href="servicios.php?pagina=' . $total_paginas . '&buscar=' . urlencode($busqueda) . '" class="pagination-number">' . $total_paginas . '</a>';
            }
            ?>
        </div>
        <a href="servicios.php?pagina=<?php echo min($total_paginas, $pagina_actual + 1); ?>&buscar=<?php echo urlencode($busqueda); ?>" class="btn" <?php echo $pagina_actual >= $total_paginas ? 'disabled' : ''; ?>>Siguiente <i class="fas fa-chevron-right"></i></a>
    </div>
    <?php endif; ?>

    <div id="servicioModal" class="modal" aria-hidden="true">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="servicioModalTitle">Nuevo Servicio</h3>
                <button type="button" class="close-modal" aria-label="Cerrar">&times;</button>
            </div>
            <form method="POST" id="servicioForm">
                <input type="hidden" name="id" id="servicio_id" value="<?php echo htmlspecialchars($formData['id'], ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="pagina" id="modalPagina" value="<?php echo htmlspecialchars($pagina_actual, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="buscar" id="modalBuscar" value="<?php echo htmlspecialchars($busqueda, ENT_QUOTES, 'UTF-8'); ?>">
                <div class="form-row">
                    <div class="form-group" style="flex:1 1 100%;">
                        <label for="servicioNombre">Nombre del servicio:</label>
                        <input type="text" id="servicioNombre" name="nombre" required style="text-transform:uppercase;" oninput="this.value = this.value.toUpperCase();" value="<?php echo htmlspecialchars($formData['nombre'], ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group" style="flex:1 1 100%;">
                        <label for="servicioDescripcion">Descripción:</label>
                        <textarea id="servicioDescripcion" name="descripcion" rows="3" style="text-transform:uppercase;" oninput="this.value = this.value.toUpperCase();"><?php echo htmlspecialchars($formData['descripcion'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="servicioPrecio">Precio estimado ($):</label>
                        <input type="number" id="servicioPrecio" name="precio_estimado" min="0" step="0.01" required value="<?php echo htmlspecialchars($formData['precio_estimado'], ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="form-group">
                        <label for="servicioDuracion">Duración estimada (min):</label>
                        <input type="number" id="servicioDuracion" name="duracion_estimada" min="1" step="1" required value="<?php echo htmlspecialchars($formData['duracion_estimada'], ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                </div>
                <input type="hidden" name="activo" id="servicioActivo" value="<?php echo $formData['activo'] ? '1' : '0'; ?>">
                <?php if ($formError !== ''): ?>
                <div class="alert alert-error" role="alert">
                    <?php echo htmlspecialchars($formError, ENT_QUOTES, 'UTF-8'); ?>
                </div>
                <?php endif; ?>
                <div class="form-actions">
                    <button type="button" id="cancelServicioModal" class="btn btn-secondary">Cancelar</button>
                    <button type="submit" name="guardar_servicio" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
window.showModalOnLoad = <?php echo $showModalOnLoad ? 'true' : 'false'; ?>;
window.modalMode = '<?php echo htmlspecialchars($modalMode, ENT_QUOTES); ?>';
window.modalInitialData = <?php echo json_encode($formData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
</script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const nuevoButton = document.getElementById('nuevoServicioButton');
        const modal = document.getElementById('servicioModal');
        const modalTitle = document.getElementById('servicioModalTitle');
        const closeModalButtons = modal.querySelectorAll('.close-modal, #cancelServicioModal');
        const servicioForm = document.getElementById('servicioForm');
        const servicioIdInput = document.getElementById('servicio_id');
        const nombreInput = document.getElementById('servicioNombre');
        const descripcionInput = document.getElementById('servicioDescripcion');
        const precioInput = document.getElementById('servicioPrecio');
        const duracionInput = document.getElementById('servicioDuracion');
        const activoInput = document.getElementById('servicioActivo');

        if (!document.querySelector('link[href*="font-awesome"]')) {
            const faLink = document.createElement('link');
            faLink.rel = 'stylesheet';
            faLink.href = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css';
            document.head.appendChild(faLink);
        }

        function openModal(mode = 'nuevo', data = {}) {
            modalTitle.textContent = mode === 'editar' ? 'Editar Servicio' : 'Nuevo Servicio';
            servicioIdInput.value = data.id || '';
            nombreInput.value = data.nombre || '';
            descripcionInput.value = data.descripcion || '';
            precioInput.value = data.precio || '';
            duracionInput.value = data.duracion || '';
            activoInput.value = data.activo === 1 || data.activo === '1' ? '1' : '0';

            modal.classList.add('show');
            modal.setAttribute('aria-hidden', 'false');
            nombreInput.focus();
        }

        function closeModal() {
            modal.classList.remove('show');
            modal.setAttribute('aria-hidden', 'true');
        }

        if (nuevoButton) {
            nuevoButton.addEventListener('click', function() {
                openModal('nuevo');
            });
        }

        document.querySelectorAll('.editar-btn').forEach(button => {
            button.addEventListener('click', function() {
                const row = this.closest('tr');
                openModal('editar', {
                    id: row.dataset.id,
                    nombre: row.dataset.nombre || '',
                    descripcion: row.dataset.descripcion || '',
                    precio: row.dataset.precio || '',
                    duracion: row.dataset.duracion || ''
                });
            });
        });

        closeModalButtons.forEach(button => {
            button.addEventListener('click', closeModal);
        });

        modal.addEventListener('click', function(event) {
            if (event.target === modal) {
                closeModal();
            }
        });

        if (window.showModalOnLoad) {
            openModal(window.modalMode || 'nuevo', window.modalInitialData || {});
        }

        servicioForm.addEventListener('submit', function() {
            if (!nombreInput.value.trim() || !precioInput.value.trim() || !duracionInput.value.trim()) {
                alert('Nombre, Precio y Duración son obligatorios.');
                return false;
            }
        });
    });
</script>

<?php include 'includes/footer.php'; ?>
