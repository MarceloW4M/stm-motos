<?php
require_once 'includes/auth.php';
require_once 'includes/database.php';
require_once 'includes/header.php';

// Verificar autenticación
requireAuth();

// enlace al CSS especializado de tablas y formularios
?>
<link rel="stylesheet" href="css/styleess.css">
<script>
window.showModalOnLoad = <?php echo $showModalOnLoad ? 'true' : 'false'; ?>;
window.modalMode = '<?php echo htmlspecialchars($modalMode, ENT_QUOTES); ?>';
window.modalInitialData = <?php echo json_encode($formData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
</script>
<?php

$database = new Database();
$db = $database->getConnection();

$columnExists = $db->query("SHOW COLUMNS FROM repuestos LIKE 'sku'")->fetch(PDO::FETCH_ASSOC);
if (!$columnExists) {
    $db->exec("ALTER TABLE repuestos ADD COLUMN sku VARCHAR(50) NULL AFTER stock");
    $db->exec("ALTER TABLE repuestos ADD UNIQUE KEY uk_repuestos_sku (sku)");
}

$mensaje = '';

$formError = '';
$showModalOnLoad = false;
$modalMode = 'nuevo';
$formData = [
    'id' => '',
    'sku' => '',
    'nombre' => '',
    'descripcion' => '',
    'precio' => '',
    'stock' => ''
];

function generateUniqueSKU($db) {
    $attempts = 0;
    do {
        $sku = str_pad((string)(mt_rand(100,999) . substr((string)time(), -8) . mt_rand(100,999)), 12, '0', STR_PAD_LEFT);
        $stmt = $db->prepare("SELECT id FROM repuestos WHERE sku = :sku");
        $stmt->bindParam(':sku', $sku);
        $stmt->execute();
        $exists = $stmt->fetch(PDO::FETCH_ASSOC);
        $attempts++;
    } while ($exists && $attempts < 5);
    return $sku;
}

// Procesar formulario de agregar o editar repuesto
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['agregar_repuesto'])) {
    $repuestoId = filter_input(INPUT_POST, 'repuesto_id', FILTER_VALIDATE_INT);
    $nombre = trim($_POST['nombre'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $precio = trim($_POST['precio'] ?? '');
    $stock = trim($_POST['stock'] ?? '');
    
    $sku = trim($_POST['sku'] ?? '');
    $formData = [
        'id' => $repuestoId ?: '',
        'sku' => $sku,
        'nombre' => $nombre,
        'descripcion' => $descripcion,
        'precio' => $precio,
        'stock' => $stock
    ];

    $modalMode = ($repuestoId !== null && $repuestoId !== false && $repuestoId > 0) ? 'editar' : 'nuevo';

    if ($nombre === '' || $precio === '' || $stock === '') {
        $formError = 'Nombre, Precio y Stock son obligatorios.';
        $showModalOnLoad = true;
    } else {
        try {
            if ($sku === '') {
                $sku = generateUniqueSKU($db);
            }

            if ($repuestoId !== null && $repuestoId !== false && $repuestoId > 0) {
                $query = "UPDATE repuestos SET nombre = :nombre, descripcion = :descripcion, precio = :precio, stock = :stock, sku = :sku WHERE id = :id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':id', $repuestoId, PDO::PARAM_INT);
            } else {
                $query = "INSERT INTO repuestos (nombre, descripcion, precio, stock, sku) 
                          VALUES (:nombre, :descripcion, :precio, :stock, :sku)";
                $stmt = $db->prepare($query);
            }

            $stmt->bindParam(':nombre', $nombre);
            $stmt->bindParam(':descripcion', $descripcion);
            $stmt->bindParam(':precio', $precio);
            $stmt->bindParam(':stock', $stock);
            $stmt->bindParam(':sku', $sku);
            $stmt->execute();
            
            header('Location: repuestos.php');
            exit();
        } catch (PDOException $e) {
            $formError = 'Error al guardar repuesto: ' . $e->getMessage();
            $showModalOnLoad = true;
        }
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
    $where = "WHERE nombre LIKE :busqueda OR descripcion LIKE :busqueda OR sku LIKE :busqueda";
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
                <i class="fas fa-search search-icon"></i>
                <input type="text" name="buscar" id="searchInput" placeholder="Buscar por nombre o descripción..." value="<?php echo htmlspecialchars($busqueda); ?>">
            </div>
            <button type="submit" class="btn btn-primary">Buscar</button>
            <button type="button" id="nuevoRepuestoButton" class="btn btn-secondary">Nuevo</button>
            <?php if (!empty($busqueda)): ?>
            <a href="repuestos.php" class="btn btn-secondary">Limpiar</a>
            <?php endif; ?>
        </form>
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
                <tr data-id="<?php echo $repuesto['id']; ?>"
                    data-sku="<?php echo htmlspecialchars($repuesto['sku'] ?? '', ENT_QUOTES); ?>"
                    data-nombre="<?php echo htmlspecialchars($repuesto['nombre'], ENT_QUOTES); ?>"
                    data-descripcion="<?php echo htmlspecialchars($repuesto['descripcion'], ENT_QUOTES); ?>"
                    data-precio="<?php echo htmlspecialchars($repuesto['precio'], ENT_QUOTES); ?>"
                    data-stock="<?php echo htmlspecialchars($repuesto['stock'], ENT_QUOTES); ?>">
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
                            <button type="button" class="btn btn-primary btn-sm editar-btn">Editar</button>
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

    <div id="repuestoModal" class="modal" aria-hidden="true">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="repuestoModalTitle">Nuevo Repuesto</h3>
                <button type="button" class="close-modal" aria-label="Cerrar">&times;</button>
            </div>
            <form method="POST" id="repuestoForm">
                <input type="hidden" name="repuesto_id" id="repuesto_id" value="<?php echo htmlspecialchars($formData['id']); ?>">
                <div class="form-row">
                    <div class="form-group" style="flex:1 1 100%;">
                        <label for="repuestoSku">SKU:</label>
                        <input type="text" id="repuestoSku" name="sku" readonly value="<?php echo htmlspecialchars($formData['sku']); ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group" style="flex:1 1 100%;">
                        <label for="repuestoNombre">Nombre del Repuesto:</label>
                        <input type="text" id="repuestoNombre" name="nombre" required style="text-transform:uppercase;" oninput="this.value = this.value.toUpperCase();" value="<?php echo htmlspecialchars($formData['nombre']); ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="repuestoPrecio">Precio ($):</label>
                        <input type="number" id="repuestoPrecio" name="precio" step="0.01" min="0" required value="<?php echo htmlspecialchars($formData['precio']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="repuestoStock">Stock Inicial:</label>
                        <input type="number" id="repuestoStock" name="stock" min="0" required value="<?php echo htmlspecialchars($formData['stock']); ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group" style="flex:1 1 100%;">
                        <label for="repuestoDescripcion">Descripción:</label>
                        <textarea id="repuestoDescripcion" name="descripcion" rows="3" style="text-transform:uppercase;" oninput="this.value = this.value.toUpperCase();"><?php echo htmlspecialchars($formData['descripcion']); ?></textarea>
                    </div>
                </div>
                <?php if ($formError !== ''): ?>
                <div class="alert alert-error" role="alert">
                    <?php echo htmlspecialchars($formError); ?>
                </div>
                <?php endif; ?>
                <div class="form-actions">
                    <button type="button" id="cancelRepuestoModal" class="btn btn-secondary">Cancelar</button>
                    <button type="submit" name="agregar_repuesto" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const nuevoButton = document.getElementById('nuevoRepuestoButton');
        const modal = document.getElementById('repuestoModal');
        const modalTitle = document.getElementById('repuestoModalTitle');
        const closeModalButtons = modal.querySelectorAll('.close-modal, #cancelRepuestoModal');
        const repuestoForm = document.getElementById('repuestoForm');
        const repuestoIdInput = document.getElementById('repuesto_id');
        const repuestoSkuInput = document.getElementById('repuestoSku');
        const nombreInput = document.getElementById('repuestoNombre');
        const descripcionInput = document.getElementById('repuestoDescripcion');
        const precioInput = document.getElementById('repuestoPrecio');
        const stockInput = document.getElementById('repuestoStock');

        if (!document.querySelector('link[href*="font-awesome"]')) {
            const faLink = document.createElement('link');
            faLink.rel = 'stylesheet';
            faLink.href = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css';
            document.head.appendChild(faLink);
        }

        function generateSKU() {
            const timePart = String(Date.now()).slice(-8);
            const randomPart = String(Math.floor(Math.random() * 9000) + 1000);
            return timePart + randomPart;
        }

        function openModal(mode = 'nuevo', data = {}) {
            modalTitle.textContent = mode === 'editar' ? 'Editar Repuesto' : 'Nuevo Repuesto';
            repuestoIdInput.value = data.id || '';
            repuestoSkuInput.value = data.sku || (mode === 'nuevo' ? generateSKU() : '');
            nombreInput.value = data.nombre || '';
            descripcionInput.value = data.descripcion || '';
            precioInput.value = data.precio || '';
            stockInput.value = data.stock || '';
            
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
                    sku: row.dataset.sku || '',
                    nombre: row.dataset.nombre || '',
                    descripcion: row.dataset.descripcion || '',
                    precio: row.dataset.precio || '',
                    stock: row.dataset.stock || ''
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

        repuestoForm.addEventListener('submit', function() {
            if (!nombreInput.value.trim() || !precioInput.value.trim() || !stockInput.value.trim()) {
                alert('Nombre, Precio y Stock son obligatorios.');
                return false;
            }
        });
    });
</script>
<?php include 'includes/footer.php'; ?>
