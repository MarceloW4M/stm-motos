<?php
require_once 'includes/auth.php';
require_once 'includes/database.php';
require_once 'includes/ubicaciones.php';
require_once 'includes/header.php';

// Verificar autenticación
requireAuth();

$database = new Database();
$db = $database->getConnection();

ensureArgentinaUbicacionesTables($db);
$provincias = getArgentinaProvincias($db);
$ciudadesPorProvincia = getArgentinaCiudadesPorProvincia($db);

$formError = '';
$showModalOnLoad = false;
$modalMode = 'nuevo';
$formData = [
    'id' => '',
    'nombre' => '',
    'telefono' => '',
    'email' => '',
    'direccion' => '',
    'cuit' => '',
    'provincia' => '',
    'ciudad' => '',
    'codigo_postal' => ''
];

// Procesar formulario de agregar o editar cliente
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_cliente'])) {
    $clienteId = filter_input(INPUT_POST, 'cliente_id', FILTER_VALIDATE_INT);
    $nombre = trim(filter_input(INPUT_POST, 'nombre', FILTER_UNSAFE_RAW) ?? '');
    $telefono = trim(filter_input(INPUT_POST, 'telefono', FILTER_UNSAFE_RAW) ?? '');
    $email = trim(filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL) ?? '');
    $direccion = trim(filter_input(INPUT_POST, 'direccion', FILTER_UNSAFE_RAW) ?? '');
    $cuit = trim(filter_input(INPUT_POST, 'cuit', FILTER_UNSAFE_RAW) ?? '');
    $provincia = trim(filter_input(INPUT_POST, 'provincia', FILTER_UNSAFE_RAW) ?? '');
    $ciudad = trim(filter_input(INPUT_POST, 'ciudad', FILTER_UNSAFE_RAW) ?? '');
    $codigo_postal = trim(filter_input(INPUT_POST, 'codigo_postal', FILTER_UNSAFE_RAW) ?? '');
    $pais = 'Argentina';

    $pagina_post = filter_input(INPUT_POST, 'pagina', FILTER_VALIDATE_INT);
    $buscar_post = trim(filter_input(INPUT_POST, 'buscar', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '');

    $formData = [
        'id' => $clienteId ?: '',
        'nombre' => $nombre,
        'telefono' => $telefono,
        'email' => $email,
        'direccion' => $direccion,
        'cuit' => $cuit,
        'provincia' => $provincia,
        'ciudad' => $ciudad,
        'codigo_postal' => $codigo_postal
    ];

    if ($nombre === '' || $telefono === '' || $cuit === '') {
        $formError = 'Nombre, DNI/CUIT y Teléfono son obligatorios.';
        $showModalOnLoad = true;
        $modalMode = ($clienteId !== null && $clienteId !== false && $clienteId > 0) ? 'editar' : 'nuevo';
    } else {
        $duplicateQuery = "SELECT id FROM clientes WHERE cuit = :cuit";
        if ($clienteId !== null && $clienteId !== false && $clienteId > 0) {
            $duplicateQuery .= " AND id != :id";
        }
        $duplicateStmt = $db->prepare($duplicateQuery);
        $duplicateStmt->bindParam(':cuit', $cuit);
        if (isset($clienteId) && $clienteId !== false && $clienteId > 0) {
            $duplicateStmt->bindParam(':id', $clienteId, PDO::PARAM_INT);
        }
        $duplicateStmt->execute();

        if ($duplicateStmt->fetch(PDO::FETCH_ASSOC)) {
            $formError = 'El cliente con ese DNI/CUIT ya existe.';
            $showModalOnLoad = true;
            $modalMode = ($clienteId !== null && $clienteId !== false && $clienteId > 0) ? 'editar' : 'nuevo';
        } else {
            if ($clienteId !== null && $clienteId !== false && $clienteId > 0) {
                $query = "UPDATE clientes SET nombre = :nombre, telefono = :telefono, email = :email, direccion = :direccion, cuit = :cuit, provincia = :provincia, ciudad = :ciudad, codigo_postal = :codigo_postal, pais = :pais WHERE id = :id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':id', $clienteId, PDO::PARAM_INT);
            } else {
                $query = "INSERT INTO clientes (nombre, telefono, email, direccion, cuit, provincia, ciudad, codigo_postal, pais) VALUES (:nombre, :telefono, :email, :direccion, :cuit, :provincia, :ciudad, :codigo_postal, :pais)";
                $stmt = $db->prepare($query);
            }

            $stmt->bindParam(':nombre', $nombre);
            $stmt->bindParam(':telefono', $telefono);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':direccion', $direccion);
            $stmt->bindParam(':cuit', $cuit);
            $stmt->bindParam(':provincia', $provincia);
            $stmt->bindParam(':ciudad', $ciudad);
            $stmt->bindParam(':codigo_postal', $codigo_postal);
            $stmt->bindParam(':pais', $pais);
            $stmt->execute();

            $redirectUrl = 'clientes.php';
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
    }
}

// Procesar eliminación de cliente
$idToDelete = filter_input(INPUT_GET, 'eliminar', FILTER_VALIDATE_INT);
if ($idToDelete !== null && $idToDelete !== false) {
    $query = "DELETE FROM clientes WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $idToDelete, PDO::PARAM_INT);
    $stmt->execute();

    header("Location: clientes.php");
    exit();
}

// Configuración de paginación
$registros_por_pagina = 50;
$pagina_actual = filter_input(INPUT_GET, 'pagina', FILTER_VALIDATE_INT);
$pagina_actual = ($pagina_actual && $pagina_actual > 0) ? $pagina_actual : 1;
$offset = ($pagina_actual - 1) * $registros_por_pagina;

// Obtener búsqueda si existe
$busqueda = trim(filter_input(INPUT_GET, 'buscar', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '');
$where = '';
$params = [];

if (!empty($busqueda)) {
    $where = "WHERE nombre LIKE :busqueda OR telefono LIKE :busqueda OR cuit LIKE :busqueda";
    $params[':busqueda'] = "%$busqueda%";
}

// Obtener total de registros
$query_total = "SELECT COUNT(*) as total FROM clientes $where";
$stmt_total = $db->prepare($query_total);
foreach ($params as $key => $value) {
    $stmt_total->bindValue($key, $value);
}
$stmt_total->execute();
$total_registros = $stmt_total->fetch(PDO::FETCH_ASSOC)['total'];
$total_paginas = ceil($total_registros / $registros_por_pagina);

// Obtener clientes para la página actual
$query = "SELECT * FROM clientes $where ORDER BY nombre LIMIT :limit OFFSET :offset";
$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $registros_por_pagina, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!-- Incluir el CSS externo -->
<link rel="stylesheet" href="css/styleess.css">

<script>
window.showModalOnLoad = <?php echo $showModalOnLoad ? 'true' : 'false'; ?>;
window.modalMode = '<?php echo htmlspecialchars($modalMode, ENT_QUOTES); ?>';
window.modalInitialData = <?php echo json_encode($formData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
</script>

<div class="container">
    <h2>Gestión de Clientes</h2>
    
    <!-- Búsqueda con formulario -->
    <div class="search-container">
        <form method="GET" action="clientes.php" class="search-form">
            <div class="search-box">
                <i class="fas fa-search search-icon"></i>
                <input type="text" name="buscar" id="searchInput" 
                       placeholder="Buscar por nombre, teléfono o CUIT..." 
                       value="<?php echo htmlspecialchars($busqueda); ?>">
            </div>
            <button type="submit" class="btn btn-primary">Buscar</button>
            <button type="button" id="nuevoClienteButton" class="btn btn-secondary">Nuevo</button>
            <?php if (!empty($busqueda)): ?>
            <a href="clientes.php" class="btn btn-secondary">Limpiar</a>
            <?php endif; ?>
        </form>
    </div>
    
    <!-- Tabla de clientes con scroll -->
    <div class="table-wrapper-scroll">
        <?php if (count($clientes) > 0): ?>
        <table class="table-fixed" id="clientesTable">
            <thead>
                <tr>
                    <th class="th-nombre">Nombre</th>
                    <th class="th-cuit">DNI/CUIT</th>
                    <th class="th-telefono">Teléfono</th>
                    <th class="th-direccion">Dirección</th>
                    <th class="th-acciones">Acciones</th>
                </tr>
            </thead>
            <tbody id="tableBody">
                <?php foreach ($clientes as $cliente): ?>
                <tr data-id="<?php echo $cliente['id']; ?>"
                    data-nombre="<?php echo htmlspecialchars($cliente['nombre'], ENT_QUOTES); ?>"
                    data-telefono="<?php echo htmlspecialchars($cliente['telefono'], ENT_QUOTES); ?>"
                    data-email="<?php echo htmlspecialchars($cliente['email'], ENT_QUOTES); ?>"
                    data-direccion="<?php echo htmlspecialchars($cliente['direccion'], ENT_QUOTES); ?>"
                    data-cuit="<?php echo htmlspecialchars($cliente['cuit'], ENT_QUOTES); ?>"
                    data-provincia="<?php echo htmlspecialchars($cliente['provincia'], ENT_QUOTES); ?>"
                    data-ciudad="<?php echo htmlspecialchars($cliente['ciudad'], ENT_QUOTES); ?>"
                    data-codigo-postal="<?php echo htmlspecialchars($cliente['codigo_postal'], ENT_QUOTES); ?>">
                    <td class="td-nombre" title="<?php echo htmlspecialchars($cliente['nombre']); ?>">
                        <?php echo htmlspecialchars($cliente['nombre']); ?>
                    </td>
                    <td class="td-cuit"><?php echo htmlspecialchars($cliente['cuit']); ?></td>
                    <td class="td-telefono"><?php echo htmlspecialchars($cliente['telefono']); ?></td>
                    <td class="td-direccion" title="<?php echo htmlspecialchars($cliente['direccion']); ?>">
                        <?php echo htmlspecialchars($cliente['direccion']); ?>
                    </td>
                    <td class="td-acciones">
                        <div class="acciones-container">
                            <button type="button" class="btn btn-primary btn-sm editar-btn">Editar</button>
                            <a href="clientes.php?eliminar=<?php echo $cliente['id']; ?>&pagina=<?php echo $pagina_actual; ?>&buscar=<?php echo urlencode($busqueda); ?>" class="btn btn-danger btn-sm" onclick="return confirm('¿Está seguro de eliminar este cliente?')">Eliminar</a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="no-results">
            <i class="fas fa-search" style="font-size: 48px; margin-bottom: 15px;"></i><br>
            No se encontraron clientes<?php echo !empty($busqueda) ? " para '{$busqueda}'" : ''; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Paginación -->
    <?php if ($total_paginas > 1): ?>
    <div class="pagination">
        <!-- Botón anterior -->
        <a href="clientes.php?pagina=<?php echo max(1, $pagina_actual - 1); ?>&buscar=<?php echo urlencode($busqueda); ?>" 
           class="btn" <?php echo $pagina_actual <= 1 ? 'disabled' : ''; ?>>
            <i class="fas fa-chevron-left"></i> Anterior
        </a>
        
        <!-- Información de página -->
        <div class="pagination-info">
            Página <?php echo $pagina_actual; ?> de <?php echo $total_paginas; ?>
        </div>
        
        <!-- Números de página (mostrar algunos alrededor de la actual) -->
        <div class="pagination-numbers">
            <?php
            // Mostrar páginas alrededor de la actual
            $inicio = max(1, $pagina_actual - 2);
            $fin = min($total_paginas, $pagina_actual + 2);
            
            if ($inicio > 1) {
                echo '<a href="clientes.php?pagina=1&buscar=' . urlencode($busqueda) . '" class="pagination-number">1</a>';
                if ($inicio > 2) echo '<span>...</span>';
            }
            
            for ($i = $inicio; $i <= $fin; $i++) {
                $active = $i == $pagina_actual ? 'active' : '';
                echo '<a href="clientes.php?pagina=' . $i . '&buscar=' . urlencode($busqueda) . '" class="pagination-number ' . $active . '">' . $i . '</a>';
            }
            
            if ($fin < $total_paginas) {
                if ($fin < $total_paginas - 1) echo '<span>...</span>';
                echo '<a href="clientes.php?pagina=' . $total_paginas . '&buscar=' . urlencode($busqueda) . '" class="pagination-number">' . $total_paginas . '</a>';
            }
            ?>
        </div>
        
        <!-- Botón siguiente -->
        <a href="clientes.php?pagina=<?php echo min($total_paginas, $pagina_actual + 1); ?>&buscar=<?php echo urlencode($busqueda); ?>" 
           class="btn" <?php echo $pagina_actual >= $total_paginas ? 'disabled' : ''; ?>>
            Siguiente <i class="fas fa-chevron-right"></i>
        </a>
    </div>
    <?php endif; ?>

    <!-- Modal para crear o editar cliente -->
    <div id="clienteModal" class="modal" aria-hidden="true">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Nuevo Cliente</h3>
                <button type="button" class="close-modal" aria-label="Cerrar">&times;</button>
            </div>
            <form method="POST" id="clienteForm">
                <input type="hidden" name="cliente_id" id="cliente_id">
                <input type="hidden" name="pagina" id="modalPagina" value="<?php echo htmlspecialchars($pagina_actual); ?>">
                <input type="hidden" name="buscar" id="modalBuscar" value="<?php echo htmlspecialchars($busqueda); ?>">
                <div class="form-row">
                    <div class="form-group" style="flex:1 1 100%;">
                        <label for="nombre">Nombre:</label>
                        <input type="text" id="nombre" name="nombre" required value="<?php echo htmlspecialchars($formData['nombre']); ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="cuit">DNI / CUIT:</label>
                        <input type="text" id="cuit" name="cuit" required value="<?php echo htmlspecialchars($formData['cuit']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="telefono">Teléfono:</label>
                        <input type="text" id="telefono" name="telefono" required value="<?php echo htmlspecialchars($formData['telefono']); ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="email">Email:</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($formData['email']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="direccion">Dirección:</label>
                        <input type="text" id="direccion" name="direccion" value="<?php echo htmlspecialchars($formData['direccion']); ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="provincia">Provincia:</label>
                        <select id="provincia" name="provincia" class="form-control" required>
                            <option value="">Seleccione provincia</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="ciudad">Ciudad:</label>
                        <select id="ciudad" name="ciudad" class="form-control" required disabled>
                            <option value="">Seleccione provincia primero</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group" style="flex:1 1 100%;">
                        <label for="codigo_postal">Código Postal:</label>
                        <input type="text" id="codigo_postal" name="codigo_postal" class="form-control" readonly>
                    </div>
                </div>
                <?php if ($formError !== ''): ?>
                <div class="alert alert-error" role="alert">
                    <?php echo htmlspecialchars($formError); ?>
                </div>
                <?php endif; ?>
                <div class="form-actions form-actions-split">
                    <div class="form-actions-left">
                        <button type="button" id="abrirHistorialBtn" class="btn btn-historial btn-sm" style="display:none;">H</button>
                    </div>
                    <div class="form-actions-right">
                        <button type="button" id="cancelModal" class="btn btn-secondary">Cancelar</button>
                        <button type="submit" name="guardar_cliente" class="btn btn-primary">Guardar</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div id="clienteHistoryModal" class="modal" aria-hidden="true">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="clienteHistoryTitle">Historial de Reparaciones</h3>
                <button type="button" class="close-modal" aria-label="Cerrar">&times;</button>
            </div>
            <div id="clienteHistoryContent" style="max-height:520px; overflow:auto; padding-right:8px;">
                <p>Cargando historial...</p>
            </div>
        </div>
    </div>
</div>

<script>
// Inicializar cuando se carga la página
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    const nuevoButton = document.getElementById('nuevoClienteButton');
    const modal = document.getElementById('clienteModal');
    const modalTitle = document.getElementById('modalTitle');
    const closeModalButtons = modal.querySelectorAll('.close-modal, #cancelModal');
    const clienteForm = document.getElementById('clienteForm');
    const clienteIdInput = document.getElementById('cliente_id');
    const nombreInput = document.getElementById('nombre');
    const telefonoInput = document.getElementById('telefono');
    const emailInput = document.getElementById('email');
    const direccionInput = document.getElementById('direccion');
    const cuitInput = document.getElementById('cuit');
    const provinciaSelect = document.getElementById('provincia');
    const ciudadSelect = document.getElementById('ciudad');
    const codigoPostalInput = document.getElementById('codigo_postal');
    const abrirHistorialBtn = document.getElementById('abrirHistorialBtn');
    const historyModal = document.getElementById('clienteHistoryModal');
    const historyTitle = document.getElementById('clienteHistoryTitle');
    const historyContent = document.getElementById('clienteHistoryContent');
    const provinciasData = <?php echo json_encode($provincias, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const ciudadesData = <?php echo json_encode($ciudadesPorProvincia, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

    if (!document.querySelector('link[href*="font-awesome"]')) {
        const faLink = document.createElement('link');
        faLink.rel = 'stylesheet';
        faLink.href = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css';
        document.head.appendChild(faLink);
    }

    function fillProvinciaOptions() {
        provinciasData.forEach(provincia => {
            const option = document.createElement('option');
            option.value = provincia.nombre;
            option.dataset.provinciaId = provincia.id;
            option.textContent = provincia.nombre;
            provinciaSelect.appendChild(option);
        });
    }

    function populateCiudadOptions(provinciaId, selectedCiudad = '') {
        ciudadSelect.innerHTML = '<option value="">Seleccione ciudad</option>';
        codigoPostalInput.value = '';
        ciudadSelect.disabled = true;

        if (!provinciaId || !ciudadesData[provinciaId]) {
            ciudadSelect.innerHTML = '<option value="">Seleccione provincia primero</option>';
            return;
        }

        ciudadesData[provinciaId].forEach(ciudad => {
            const option = document.createElement('option');
            option.value = ciudad.nombre;
            option.textContent = ciudad.nombre;
            option.dataset.codigoPostal = ciudad.codigo_postal || '';
            ciudadSelect.appendChild(option);
        });

        ciudadSelect.disabled = false;

        if (selectedCiudad) {
            const selectedOption = Array.from(ciudadSelect.options).find(opt => opt.value === selectedCiudad);
            if (selectedOption) {
                selectedOption.selected = true;
                codigoPostalInput.value = selectedOption.dataset.codigoPostal || '';
            }
        }
    }

    function updateCodigoPostal() {
        const option = ciudadSelect.selectedOptions[0];
        codigoPostalInput.value = option ? option.dataset.codigoPostal || '' : '';
    }

    fillProvinciaOptions();

    provinciaSelect.addEventListener('change', function() {
        const selectedOption = this.selectedOptions[0];
        const provinciaId = selectedOption ? selectedOption.dataset.provinciaId : null;
        populateCiudadOptions(provinciaId);
    });

    ciudadSelect.addEventListener('change', updateCodigoPostal);

    if (searchInput && searchInput.value) {
        searchInput.focus();
        searchInput.select();
    }

    const cellsWithTitle = document.querySelectorAll('td[title]');
    cellsWithTitle.forEach(cell => {
        cell.addEventListener('mouseenter', function() {
            if (this.offsetWidth < this.scrollWidth && this.textContent.trim()) {
                this.title = this.textContent;
            }
        });
    });

    function debounce(fn, delay) {
        let timer;
        return function(...args) {
            clearTimeout(timer);
            timer = setTimeout(() => fn.apply(this, args), delay);
        };
    }

    if (searchInput) {
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                this.form.submit();
            }
        });
    }

    function openModal(mode, data = {}) {
        modalTitle.textContent = mode === 'editar' ? 'Editar Cliente' : 'Nuevo Cliente';
        clienteIdInput.value = data.id || '';
        nombreInput.value = data.nombre || '';
        telefonoInput.value = data.telefono || '';
        emailInput.value = data.email || '';
        direccionInput.value = data.direccion || '';
        cuitInput.value = data.cuit || '';

        if (data.provincia) {
            provinciaSelect.value = data.provincia;
        } else {
            provinciaSelect.value = '';
        }

        const selectedProvinceOption = provinciaSelect.selectedOptions[0];
        const provinciaId = selectedProvinceOption ? selectedProvinceOption.dataset.provinciaId : null;

        if (provinciaId) {
            populateCiudadOptions(provinciaId, data.ciudad || '');
        } else {
            ciudadSelect.innerHTML = '<option value="">Seleccione provincia primero</option>';
            ciudadSelect.disabled = true;
            codigoPostalInput.value = data.codigo_postal || '';
        }

        if (data.ciudad && provinciaSelect.value) {
            const selectedOption = Array.from(ciudadSelect.options).find(opt => opt.value === data.ciudad);
            if (selectedOption) {
                selectedOption.selected = true;
                codigoPostalInput.value = selectedOption.dataset.codigoPostal || data.codigo_postal || '';
            }
        }

        modal.classList.add('show');
        modal.setAttribute('aria-hidden', 'false');
        nombreInput.focus();

        if (mode === 'editar') {
            abrirHistorialBtn.style.display = 'inline-flex';
        } else {
            abrirHistorialBtn.style.display = 'none';
        }
    }

    function closeModal() {
        modal.classList.remove('show');
        modal.setAttribute('aria-hidden', 'true');
    }

    if (window.showModalOnLoad) {
        openModal(window.modalMode || 'nuevo', window.modalInitialData || {});
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
                telefono: row.dataset.telefono || '',
                email: row.dataset.email || '',
                direccion: row.dataset.direccion || '',
                cuit: row.dataset.cuit || '',
                provincia: row.dataset.provincia || '',
                ciudad: row.dataset.ciudad || '',
                codigo_postal: row.dataset.codigoPostal || ''
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

    if (historyModal) {
        const historyCloseButtons = historyModal.querySelectorAll('.close-modal');
        historyCloseButtons.forEach(button => {
            button.addEventListener('click', closeHistoryModal);
        });
        historyModal.addEventListener('click', function(event) {
            if (event.target === historyModal) {
                closeHistoryModal();
            }
        });
    }

    if (abrirHistorialBtn) {
        abrirHistorialBtn.addEventListener('click', function() {
            const clienteId = clienteIdInput.value;
            if (clienteId) {
                openHistoryModal(clienteId);
            }
        });
    }

    function openHistoryModal(clienteId) {
        if (!historyModal) return;
        historyModal.classList.add('show');
        historyModal.setAttribute('aria-hidden', 'false');

        fetch('historico_cliente.php?cliente_id=' + encodeURIComponent(clienteId) + '&ajax=1')
            .then(response => response.text())
            .then(html => {
                historyContent.innerHTML = html;
            })
            .catch(() => {
                historyContent.innerHTML = '<p>Error cargando el historial. Intente nuevamente.</p>';
            });
    }

    function closeHistoryModal() {
        if (!historyModal) return;
        historyModal.classList.remove('show');
        historyModal.setAttribute('aria-hidden', 'true');
    }

    clienteForm.addEventListener('submit', function() {
        if (!nombreInput.value.trim() || !telefonoInput.value.trim() || !cuitInput.value.trim()) {
            alert('Nombre, DNI/CUIT y teléfono son obligatorios.');
            return false;
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>