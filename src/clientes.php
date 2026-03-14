<?php
require_once 'includes/auth.php';
require_once 'includes/database.php';
require_once 'includes/header.php';

// Verificar autenticación
requireAuth();

$database = new Database();
$db = $database->getConnection();

// Procesar formulario de agregar cliente
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['agregar_cliente'])) {
    $nombre = trim(filter_input(INPUT_POST, 'nombre', FILTER_UNSAFE_RAW) ?? '');
    $telefono = trim(filter_input(INPUT_POST, 'telefono', FILTER_UNSAFE_RAW) ?? '');
    $email = trim(filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL) ?? '');
    $direccion = trim(filter_input(INPUT_POST, 'direccion', FILTER_UNSAFE_RAW) ?? '');
    $cuit = trim(filter_input(INPUT_POST, 'cuit', FILTER_UNSAFE_RAW) ?? '');

    if ($nombre !== '' && $telefono !== '') {
        $query = "INSERT INTO clientes (nombre, telefono, email, direccion, cuit) 
                  VALUES (:nombre, :telefono, :email, :direccion, :cuit)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':nombre', $nombre);
        $stmt->bindParam(':telefono', $telefono);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':direccion', $direccion);
        $stmt->bindParam(':cuit', $cuit);
        $stmt->execute();
    }

    header("Location: clientes.php");
    exit();
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
            <?php if (!empty($busqueda)): ?>
            <a href="clientes.php" class="btn btn-secondary">Limpiar</a>
            <?php endif; ?>
        </form>
        <div id="resultCount" class="result-count">
            Mostrando <?php echo count($clientes); ?> de <?php echo $total_registros; ?> clientes
        </div>
    </div>
    
    <!-- Tabla de clientes con scroll -->
    <div class="table-wrapper-scroll">
        <?php if (count($clientes) > 0): ?>
        <table class="table-fixed" id="clientesTable">
            <thead>
                <tr>
                    <th class="th-id">ID</th>
                    <th class="th-nombre">Nombre</th>
                    <th class="th-telefono">Teléfono</th>
                    <th class="th-direccion">Dirección</th>
                    <th class="th-acciones">Acciones</th>
                </tr>
            </thead>
            <tbody id="tableBody">
                <?php foreach ($clientes as $cliente): ?>
                <tr data-id="<?php echo $cliente['id']; ?>">
                    <td class="td-id"><?php echo $cliente['id']; ?></td>
                    <td class="td-nombre" title="<?php echo htmlspecialchars($cliente['nombre']); ?>">
                        <?php echo htmlspecialchars($cliente['nombre']); ?>
                    </td>
                    <td class="td-telefono"><?php echo htmlspecialchars($cliente['telefono']); ?></td>
                    <td class="td-direccion" title="<?php echo htmlspecialchars($cliente['direccion']); ?>">
                        <?php echo htmlspecialchars($cliente['direccion']); ?>
                    </td>
                    <td class="td-acciones">
                        <div class="acciones-container">
                            <a href="clientes_m.php?id=<?php echo $cliente['id']; ?>" class="btn btn-primary btn-sm">Editar</a>
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

    <!-- Formulario para agregar cliente -->
    <div class="form-container">
        <h3>Agregar Nuevo Cliente</h3>
        <form method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label for="nombre">Nombre:</label>
                    <input type="text" id="nombre" name="nombre" required>
                </div>
                
                <div class="form-group">
                    <label for="telefono">Teléfono:</label>
                    <input type="text" id="telefono" name="telefono" required>
                </div> 

                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="direccion">Dirección:</label>
                    <input type="text" id="direccion" name="direccion">
                </div>

                <div class="form-group">
                    <label for="cuit">CUIT:</label>
                    <input type="text" id="cuit" name="cuit" placeholder="XX-XXXXXXXX-X">
                </div>
                
                <div class="form-group" style="align-self: flex-end;">
                    <button type="submit" name="agregar_cliente" class="btn btn-primary">Agregar Cliente</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
// Inicializar cuando se carga la página
document.addEventListener('DOMContentLoaded', function() {
    // Agregar Font Awesome si no está
    if (!document.querySelector('link[href*="font-awesome"]')) {
        const faLink = document.createElement('link');
        faLink.rel = 'stylesheet';
        faLink.href = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css';
        document.head.appendChild(faLink);
    }
    
    // Enfocar el campo de búsqueda si hay texto
    const searchInput = document.getElementById('searchInput');
    if (searchInput && searchInput.value) {
        searchInput.focus();
        searchInput.select();
    }
    
    // Agregar tooltips para texto truncado
    const cellsWithTitle = document.querySelectorAll('td[title]');
    cellsWithTitle.forEach(cell => {
        cell.addEventListener('mouseenter', function() {
            if (this.offsetWidth < this.scrollWidth && this.textContent.trim()) {
                this.title = this.textContent;
            }
        });
    });
    
    // Tecla Enter en búsqueda para enviar formulario
    if (searchInput) {
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                this.form.submit();
            }
        });
    }
});
</script>

<?php include 'includes/footer.php'; ?>