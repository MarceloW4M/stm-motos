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

// Asegurar campo kilometros en la tabla de vehículos
$columnExists = $db->query("SHOW COLUMNS FROM vehiculos LIKE 'kilometros'")->fetch(PDO::FETCH_ASSOC);
if (!$columnExists) {
    $db->exec("ALTER TABLE vehiculos ADD kilometros INT DEFAULT NULL");
}

$mensaje = '';
$formError = '';
$showModalOnLoad = false;
$modalMode = 'nuevo';
$formData = [
    'id' => '',
    'cliente_id' => '',
    'cliente_name' => '',
    'marca' => '',
    'modelo' => '',
    'matricula' => '',
    'anio' => '',
    'kilometros' => '',
    'vin' => ''
];

// Procesar formulario de agregar o editar vehículo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_vehiculo'])) {
    $vehiculoId = filter_input(INPUT_POST, 'vehiculo_id', FILTER_VALIDATE_INT);
    $cliente_id = trim($_POST['cliente_id'] ?? '');
    $cliente_name = trim($_POST['cliente_name'] ?? '');
    $marca = strtoupper(trim($_POST['marca'] ?? ''));
    $modelo = strtoupper(trim($_POST['modelo'] ?? ''));
    $matricula = strtoupper(trim($_POST['matricula'] ?? ''));
    $anio = trim($_POST['anio'] ?? '');
    $kilometros = trim($_POST['kilometros'] ?? '');
    $vin = strtoupper(trim($_POST['vin'] ?? ''));

    $formData = [
        'id' => $vehiculoId ?: '',
        'cliente_id' => $cliente_id,
        'cliente_name' => $cliente_name,
        'marca' => $marca,
        'modelo' => $modelo,
        'matricula' => $matricula,
        'anio' => $anio,
        'kilometros' => $kilometros,
        'vin' => $vin
    ];

    if ($cliente_id === '' || $marca === '' || $modelo === '' || $matricula === '') {
        $formError = 'Cliente, Marca, Modelo y Matrícula son obligatorios.';
        $showModalOnLoad = true;
        $modalMode = ($vehiculoId !== null && $vehiculoId !== false && $vehiculoId > 0) ? 'editar' : 'nuevo';
    } else {
        $isEdit = ($vehiculoId !== null && $vehiculoId !== false && $vehiculoId > 0);

        if (!$isEdit) {
            $duplicateQuery = "SELECT id FROM vehiculos WHERE matricula = :matricula";
            $duplicateStmt = $db->prepare($duplicateQuery);
            $duplicateStmt->bindParam(':matricula', $matricula);
            $duplicateStmt->execute();

            if ($duplicateStmt->fetch(PDO::FETCH_ASSOC)) {
                $formError = 'La matrícula ya existe. Por favor, ingrese una matrícula diferente.';
                $showModalOnLoad = true;
                $modalMode = 'nuevo';
            }
        }

        if ($formError === '') {
            try {
                if ($vehiculoId !== null && $vehiculoId !== false && $vehiculoId > 0) {
                    $query = "UPDATE vehiculos SET cliente_id = :cliente_id, marca = :marca, modelo = :modelo, matricula = :matricula, anio = :anio, kilometros = :kilometros, vin = :vin WHERE id = :id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':id', $vehiculoId, PDO::PARAM_INT);
                } else {
                    $query = "INSERT INTO vehiculos (cliente_id, marca, modelo, matricula, anio, kilometros, vin) VALUES (:cliente_id, :marca, :modelo, :matricula, :anio, :kilometros, :vin)";
                    $stmt = $db->prepare($query);
                }

                $stmt->bindParam(':cliente_id', $cliente_id, PDO::PARAM_INT);
                $stmt->bindParam(':marca', $marca);
                $stmt->bindParam(':modelo', $modelo);
                $stmt->bindParam(':matricula', $matricula);
                $stmt->bindParam(':anio', $anio, PDO::PARAM_INT);
                $stmt->bindValue(':kilometros', $kilometros !== '' ? $kilometros : null, $kilometros !== '' ? PDO::PARAM_INT : PDO::PARAM_NULL);
                $stmt->bindParam(':vin', $vin);
                $stmt->execute();

                header('Location: vehiculos.php');
                exit();
            } catch (PDOException $e) {
                $formError = 'Error al guardar vehículo: ' . $e->getMessage();
                $showModalOnLoad = true;
                $modalMode = ($vehiculoId !== null && $vehiculoId !== false && $vehiculoId > 0) ? 'editar' : 'nuevo';
            }
        }
    }
}

// Procesar eliminación de vehículo
if (isset($_GET['eliminar'])) {
    $id = $_GET['eliminar'];
    try {
        $query = "DELETE FROM vehiculos WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        $mensaje = "✅ Vehículo eliminado exitosamente";
    } catch (PDOException $e) {
        $mensaje = "❌ Error al eliminar vehículo: " . $e->getMessage();
    }
}

// --- búsqueda y paginación similares a clientes.php ---
$registros_por_pagina = 50;
$pagina_actual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($pagina_actual - 1) * $registros_por_pagina;

$busqueda = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';
$where = '';
$params = [];

if (!empty($busqueda)) {
    $where = "WHERE v.marca LIKE :busqueda OR v.modelo LIKE :busqueda OR v.matricula LIKE :busqueda";
    $params[':busqueda'] = "%$busqueda%";
}

// Obtener total de registros
$query_total = "SELECT COUNT(*) as total FROM vehiculos v $where";
$stmt_total = $db->prepare($query_total);
foreach ($params as $key => $value) {
    $stmt_total->bindValue($key, $value);
}
$stmt_total->execute();
$total_registros = $stmt_total->fetch(PDO::FETCH_ASSOC)['total'];
$total_paginas = ceil($total_registros / $registros_por_pagina);

// Obtener vehículos paginados con join
$query = "
    SELECT v.*, c.nombre as cliente_nombre, c.telefono as cliente_telefono
    FROM vehiculos v 
    LEFT JOIN clientes c ON v.cliente_id = c.id 
    $where
    ORDER BY v.marca, v.modelo
    LIMIT :limit OFFSET :offset
";
$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $registros_por_pagina, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$vehiculos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener lista de clientes para el dropdown
$query_clientes = "SELECT id, nombre FROM clientes ORDER BY nombre";
$stmt_clientes = $db->prepare($query_clientes);
$stmt_clientes->execute();
$clientes = $stmt_clientes->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container">
    <h2>Gestión de Vehículos</h2>
    
    <?php if ($mensaje): ?>
    <div class="alert <?php echo strpos($mensaje,'✅')===0 ? 'success' : 'error'; ?>">
        <?php echo $mensaje; ?>
    </div>
    <?php endif; ?>


    <!-- búsqueda similar a clientes -->
    <div class="search-container">
        <form method="GET" action="vehiculos.php" class="search-form">
            <div class="search-box">
                <i class="fas fa-search search-icon"></i>
                <input type="text" name="buscar" id="searchInput" placeholder="Buscar por marca, modelo o matrícula..." value="<?php echo htmlspecialchars($busqueda); ?>">
            </div>
            <button type="submit" class="btn btn-primary">Buscar</button>
            <button type="button" id="nuevoVehiculoButton" class="btn btn-secondary">Nuevo</button>
            <?php if (!empty($busqueda)): ?>
            <a href="vehiculos.php" class="btn btn-secondary">Limpiar</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- tabla con scroll -->
    <div class="table-wrapper-scroll">
        <?php if (count($vehiculos) > 0): ?>
        <table class="table-fixed" id="vehiculosTable">
            <thead>
                <tr>
                    <th class="th-marca">Marca</th>
                    <th class="th-modelo">Modelo</th>
                    <th class="th-matricula">Matrícula</th>
                    <th class="th-anio">Año</th>
                    <th class="th-cliente">Cliente</th>
                    <th class="th-telefono">Teléfono</th>
                    <th class="th-acciones">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($vehiculos as $vehiculo): ?>
                <tr data-id="<?php echo $vehiculo['id']; ?>"
                    data-cliente-id="<?php echo $vehiculo['cliente_id']; ?>"
                    data-cliente-nombre="<?php echo htmlspecialchars($vehiculo['cliente_nombre'], ENT_QUOTES); ?>"
                    data-marca="<?php echo htmlspecialchars($vehiculo['marca'], ENT_QUOTES); ?>"
                    data-modelo="<?php echo htmlspecialchars($vehiculo['modelo'], ENT_QUOTES); ?>"
                    data-matricula="<?php echo htmlspecialchars($vehiculo['matricula'], ENT_QUOTES); ?>"
                    data-anio="<?php echo htmlspecialchars($vehiculo['anio'], ENT_QUOTES); ?>"
                    data-kilometros="<?php echo htmlspecialchars($vehiculo['kilometros'] ?? '', ENT_QUOTES); ?>"
                    data-vin="<?php echo htmlspecialchars($vehiculo['vin'], ENT_QUOTES); ?>">
                    <td class="td-marca"><?php echo htmlspecialchars($vehiculo['marca']); ?></td>
                    <td class="td-modelo"><?php echo htmlspecialchars($vehiculo['modelo']); ?></td>
                    <td class="td-matricula"><?php echo htmlspecialchars($vehiculo['matricula']); ?></td>
                    <td class="td-anio"><?php echo $vehiculo['anio']; ?></td>
                    <td class="td-cliente"><?php echo htmlspecialchars($vehiculo['cliente_nombre']); ?></td>
                    <td class="td-telefono"><?php echo htmlspecialchars($vehiculo['cliente_telefono']); ?></td>
                    <td class="td-acciones">
                        <button type="button" class="btn btn-primary btn-sm editar-btn">Editar</button>
                        <a href="vehiculos.php?eliminar=<?php echo $vehiculo['id']; ?>&pagina=<?php echo $pagina_actual; ?>&buscar=<?php echo urlencode($busqueda); ?>" class="btn btn-danger btn-sm" onclick="return confirm('¿Está seguro de eliminar este vehículo?')">Eliminar</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="no-results">
            🔍<br>
            No se encontraron vehículos<?php echo !empty($busqueda) ? " para '{$busqueda}'" : ''; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- paginación -->
    <?php if ($total_paginas > 1): ?>
    <div class="pagination">
        <a href="vehiculos.php?pagina=<?php echo max(1,$pagina_actual-1); ?>&buscar=<?php echo urlencode($busqueda); ?>" class="btn" <?php echo $pagina_actual<=1?'disabled':'';?>><i class="fas fa-chevron-left"></i> Anterior</a>
        <div class="pagination-info">Página <?php echo $pagina_actual; ?> de <?php echo $total_paginas; ?></div>
        <div class="pagination-numbers">
            <?php
            $inicio = max(1,$pagina_actual-2);
            $fin = min($total_paginas,$pagina_actual+2);
            if ($inicio>1){
                echo '<a href="vehiculos.php?pagina=1&buscar='.urlencode($busqueda).'" class="pagination-number">1</a>';
                if ($inicio>2) echo '<span>...</span>';
            }
            for($i=$inicio;$i<=$fin;$i++){
                $active = $i==$pagina_actual?'active':'';
                echo '<a href="vehiculos.php?pagina='.$i.'&buscar='.urlencode($busqueda).'" class="pagination-number '.$active.'">'.$i.'</a>';
            }
            if ($fin<$total_paginas){
                if ($fin<$total_paginas-1) echo '<span>...</span>';
                echo '<a href="vehiculos.php?pagina='.$total_paginas.'&buscar='.urlencode($busqueda).'" class="pagination-number">'.$total_paginas.'</a>';
            }
            ?>
        </div>
        <a href="vehiculos.php?pagina=<?php echo min($total_paginas,$pagina_actual+1); ?>&buscar=<?php echo urlencode($busqueda); ?>" class="btn" <?php echo $pagina_actual>=$total_paginas?'disabled':'';?>>Siguiente <i class="fas fa-chevron-right"></i></a>
    </div>
    <?php endif; ?>

    <div id="vehiculoModal" class="modal" aria-hidden="true">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="vehiculoModalTitle">Nuevo Vehículo</h3>
                <button type="button" class="close-modal" aria-label="Cerrar">&times;</button>
            </div>
            <form method="POST" id="vehiculoForm">
                <input type="hidden" name="vehiculo_id" id="vehiculo_id" value="<?php echo htmlspecialchars($formData['id']); ?>">
                <div class="form-row">
                    <div class="form-group" style="flex: 1 1 100%; position: relative;">
                        <label for="clienteSearch">Cliente:</label>
                        <div class="inline-input-button" style="display: flex; gap: 8px; align-items: flex-end;">
                            <input type="text" id="clienteSearch" name="cliente_name" class="form-control" style="flex:1; min-width:0;" placeholder="Buscar cliente..." autocomplete="off" value="<?php echo htmlspecialchars($formData['cliente_name']); ?>" readonly>
                            <button type="button" id="clienteEditButton" class="btn btn-primary btn-sm" style="min-width: 34px; padding: 0 10px; line-height:1; display: inline-flex; align-items: center; justify-content: center;">C</button>
                        </div>
                        <div id="clienteSuggestions" class="cliente-suggestions" style="position: absolute; top: 100%; left: 0; right: 0; z-index: 1000; border: 1px solid #ccc; background: #fff; max-height: 260px; overflow-y: auto; display: none;"></div>
                        <input type="hidden" name="cliente_id" id="cliente_id" value="<?php echo htmlspecialchars($formData['cliente_id']); ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="marca">Marca:</label>
                        <input type="text" id="marca" name="marca" required style="text-transform:uppercase;" oninput="this.value = this.value.toUpperCase();" value="<?php echo htmlspecialchars($formData['marca']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="modelo">Modelo:</label>
                        <input type="text" id="modelo" name="modelo" required style="text-transform:uppercase;" oninput="this.value = this.value.toUpperCase();" value="<?php echo htmlspecialchars($formData['modelo']); ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="anio">Año:</label>
                        <input type="number" id="anio" name="anio" min="1900" max="2030" value="<?php echo htmlspecialchars($formData['anio']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="kilometros">Kilómetros:</label>
                        <input type="number" id="kilometros" name="kilometros" min="0" value="<?php echo htmlspecialchars($formData['kilometros']); ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="matricula">Matrícula:</label>
                        <input type="text" id="matricula" name="matricula" required style="text-transform:uppercase;" oninput="this.value = this.value.toUpperCase();" value="<?php echo htmlspecialchars($formData['matricula']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="vin">VIN:</label>
                        <input type="text" id="vin" name="vin" style="text-transform:uppercase;" oninput="this.value = this.value.toUpperCase();" value="<?php echo htmlspecialchars($formData['vin']); ?>">
                    </div>
                </div>
                <?php if ($formError !== ''): ?>
                <div id="vehiculoFormError" class="alert alert-error" role="alert">
                    <?php echo htmlspecialchars($formError); ?>
                </div>
                <?php endif; ?>
                <div class="form-actions">
                    <button type="button" id="cancelVehiculoModal" class="btn btn-secondary">Cancelar</button>
                    <button type="submit" name="guardar_vehiculo" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>

</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const nuevoButton = document.getElementById('nuevoVehiculoButton');
        const modal = document.getElementById('vehiculoModal');
        const modalTitle = document.getElementById('vehiculoModalTitle');
        const closeModalButtons = modal.querySelectorAll('.close-modal, #cancelVehiculoModal');

        if (!document.querySelector('link[href*="font-awesome"]')) {
            const faLink = document.createElement('link');
            faLink.rel = 'stylesheet';
            faLink.href = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css';
            document.head.appendChild(faLink);
        }
        const vehiculoForm = document.getElementById('vehiculoForm');
        const vehiculoIdInput = document.getElementById('vehiculo_id');
        const clienteSearchInput = document.getElementById('clienteSearch');
        const clienteEditButton = document.getElementById('clienteEditButton');
        const clienteSuggestions = document.getElementById('clienteSuggestions');
        const clienteIdInput = document.getElementById('cliente_id');
        const marcaInput = document.getElementById('marca');
        const modeloInput = document.getElementById('modelo');
        const matriculaInput = document.getElementById('matricula');
        const anioInput = document.getElementById('anio');
        const kilometrosInput = document.getElementById('kilometros');
        const vinInput = document.getElementById('vin');
        const clienteData = <?php echo json_encode($clientes, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        const formErrorAlert = document.getElementById('vehiculoFormError');
        let preserveError = <?php echo $formError !== '' ? 'true' : 'false'; ?>;

        function openModal(mode, data = {}, keepError = false) {
            modalTitle.textContent = mode === 'editar' ? 'Editar Vehículo' : 'Nuevo Vehículo';
            vehiculoIdInput.value = data.id || '';
            clienteSearchInput.value = data.cliente_name || '';
            clienteIdInput.value = data.cliente_id || '';
            marcaInput.value = data.marca || '';
            modeloInput.value = data.modelo || '';
            matriculaInput.value = data.matricula || '';
            anioInput.value = data.anio || '';
            kilometrosInput.value = data.kilometros || '';
            vinInput.value = data.vin || '';

            clienteSearchInput.readOnly = mode === 'editar';
            clienteEditButton.style.display = 'inline-flex';
            if (mode === 'editar') {
                clienteEditButton.title = 'Modificar cliente';
            } else {
                clienteEditButton.title = 'Seleccionar cliente';
            }

            if (formErrorAlert) {
                if (keepError && preserveError) {
                    formErrorAlert.style.display = formErrorAlert.textContent.trim() ? 'block' : 'none';
                } else {
                    formErrorAlert.style.display = 'none';
                    preserveError = false;
                }
            }

            filterClienteSuggestions();
            clienteSuggestions.style.display = 'none';

            modal.classList.add('show');
            modal.setAttribute('aria-hidden', 'false');
            clienteSearchInput.focus();
        }

        function closeModal() {
            modal.classList.remove('show');
            modal.setAttribute('aria-hidden', 'true');
        }

        function renderClienteSuggestions(filtered) {
            clienteSuggestions.innerHTML = '';
            if (!filtered.length) {
                clienteSuggestions.style.display = 'none';
                return;
            }

            filtered.forEach(cliente => {
                const item = document.createElement('div');
                item.className = 'suggestion-item';
                item.style.padding = '10px';
                item.style.cursor = 'pointer';
                item.style.borderBottom = '1px solid #f0f0f0';
                item.textContent = cliente.nombre;
                item.addEventListener('click', function() {
                    clienteSearchInput.value = cliente.nombre;
                    clienteIdInput.value = cliente.id;
                    clienteSuggestions.style.display = 'none';
                });
                clienteSuggestions.appendChild(item);
            });
            clienteSuggestions.style.display = 'block';
        }

        function filterClienteSuggestions() {
            const searchTerm = clienteSearchInput.value.trim().toLowerCase();
            const filtered = clienteData.filter(cliente => cliente.nombre.toLowerCase().includes(searchTerm));
            renderClienteSuggestions(filtered);
        }

        clienteSearchInput.addEventListener('input', function() {
            clienteIdInput.value = '';
            filterClienteSuggestions();
        });

        clienteSearchInput.addEventListener('focus', function() {
            if (clienteSearchInput.readOnly) return;
            if (clienteSearchInput.value.trim()) {
                filterClienteSuggestions();
            }
        });

        clienteEditButton.addEventListener('click', function() {
            clienteSearchInput.readOnly = false;
            clienteSearchInput.focus();
            filterClienteSuggestions();
        });

        document.addEventListener('click', function(event) {
            if (!modal.contains(event.target) || event.target === clienteSearchInput) return;
            clienteSuggestions.style.display = 'none';
        });

        if (nuevoButton) {
            nuevoButton.addEventListener('click', function(event) {
                event.preventDefault();
                openModal('nuevo', {}, false);
            });
        }

        document.querySelectorAll('.editar-btn').forEach(button => {
            button.addEventListener('click', function() {
                const row = this.closest('tr');
                openModal('editar', {
                    id: row.dataset.id,
                    cliente_id: row.dataset.clienteId,
                    cliente_name: row.dataset.clienteNombre || '',
                    marca: row.dataset.marca || '',
                    modelo: row.dataset.modelo || '',
                    matricula: row.dataset.matricula || '',
                    anio: row.dataset.anio || '',
                    kilometros: row.dataset.kilometros || '',
                    vin: row.dataset.vin || ''
                }, false);
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

        vehiculoForm.addEventListener('submit', function() {
            if (!clienteIdInput.value || !marcaInput.value.trim() || !modeloInput.value.trim() || !matriculaInput.value.trim()) {
                alert('Cliente, Marca, Modelo y Matrícula son obligatorios.');
                return false;
            }
        });
    });
</script>

<?php include 'includes/footer.php'; ?>