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

// Procesar formulario de agregar vehículo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['agregar_vehiculo'])) {
    $cliente_id = $_POST['cliente_id'];
    $marca = $_POST['marca'];
    $modelo = $_POST['modelo'];
    $matricula = $_POST['matricula'];
    $anio = $_POST['anio'];
    $vin = $_POST['vin'];
    
    try {
        $query = "INSERT INTO vehiculos (cliente_id, marca, modelo, matricula, anio, vin) 
                  VALUES (:cliente_id, :marca, :modelo, :matricula, :anio, :vin)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':cliente_id', $cliente_id);
        $stmt->bindParam(':marca', $marca);
        $stmt->bindParam(':modelo', $modelo);
        $stmt->bindParam(':matricula', $matricula);
        $stmt->bindParam(':anio', $anio);
        $stmt->bindParam(':vin', $vin);
        $stmt->execute();
        
        $mensaje = "✅ Vehículo agregado exitosamente";
    } catch (PDOException $e) {
        $mensaje = "❌ Error al agregar vehículo: " . $e->getMessage();
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
                <span class="search-icon">🔍</span>
                <input type="text" name="buscar" id="searchInput" placeholder="Buscar por marca, modelo o matrícula..." value="<?php echo htmlspecialchars($busqueda); ?>">
            </div>
            <button type="submit" class="btn-buscar">Buscar</button>
            <?php if (!empty($busqueda)): ?>
            <a href="vehiculos.php" class="btn-limpiar">Limpiar</a>
            <?php endif; ?>
        </form>
        <div id="resultCount" class="result-count">
            Mostrando <?php echo count($vehiculos); ?> de <?php echo $total_registros; ?> vehículos
        </div>
    </div>

    <!-- tabla con scroll -->
    <div class="table-wrapper-scroll">
        <?php if (count($vehiculos) > 0): ?>
        <table class="table-fixed" id="vehiculosTable">
            <thead>
                <tr>
                    <th class="th-id">ID</th>
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
                <tr>
                    <td class="td-id"><?php echo $vehiculo['id']; ?></td>
                    <td class="td-marca"><?php echo htmlspecialchars($vehiculo['marca']); ?></td>
                    <td class="td-modelo"><?php echo htmlspecialchars($vehiculo['modelo']); ?></td>
                    <td class="td-matricula"><?php echo htmlspecialchars($vehiculo['matricula']); ?></td>
                    <td class="td-anio"><?php echo $vehiculo['anio']; ?></td>
                    <td class="td-cliente"><?php echo htmlspecialchars($vehiculo['cliente_nombre']); ?></td>
                    <td class="td-telefono"><?php echo htmlspecialchars($vehiculo['cliente_telefono']); ?></td>
                    <td class="td-acciones">
                        <a href="editar_vehiculo.php?id=<?php echo $vehiculo['id']; ?>" class="btn btn-primary btn-sm">Editar</a>
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

    <!-- Modal para buscar y seleccionar cliente -->
    <div id="modalCliente" class="modal" style="display: none;">
        <div class="modal-content" style="max-width: 600px;">
            <span class="close" onclick="cerrarModalCliente()">&times;</span>
            <h3>Seleccionar Cliente</h3>
            <div class="search-container" style="margin-bottom: 20px;">
                <input type="text" id="buscarClienteInput" placeholder="Buscar por nombre o teléfono..." 
                       style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;" 
                       onkeyup="buscarCliente()">
            </div>
            <div style="max-height: 400px; overflow-y: auto;">
                <table class="table" id="tablaClientesModal" style="margin: 0;">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Teléfono</th>
                            <th>Email</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clientes as $cliente): ?>
                        <tr class="cliente-row" data-cliente-id="<?php echo $cliente['id']; ?>" data-cliente-nombre="<?php echo htmlspecialchars($cliente['nombre']); ?>">
                            <td><?php echo htmlspecialchars($cliente['nombre']); ?></td>
                            <td><?php echo htmlspecialchars($cliente['telefono']); ?></td>
                            <td><?php echo htmlspecialchars($cliente['email'] ?? ''); ?></td>
                            <td><button type="button" class="btn btn-primary btn-sm" onclick="seleccionarCliente(this)">Seleccionar</button></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- formulario de alta (debajo de la paginación) -->
    <div class="form-container">
        <h3>Agregar Nuevo Vehículo</h3>
        <form method="POST" onsubmit="validarFormulario(event)">
            <div class="form-row">
            <div class="form-group">
                <label>Cliente:</label>
                <div style="display: flex; gap: 10px; align-items: flex-start;">
                    <input type="text" id="clienteNombre" placeholder="Seleccionar cliente..." readonly style="flex: 1; background-color: #f5f5f5;">
                    <input type="hidden" id="cliente_id" name="cliente_id" value="">
                    <button type="button" class="btn btn-secondary" onclick="abrirModalCliente()" style="margin-top: 0;">Buscar</button>
                </div>
            </div>
                <div class="form-group">
                    <label for="marca">Marca:</label>
                    <input type="text" id="marca" name="marca" required>
                </div>
                <div class="form-group">
                    <label for="modelo">Modelo:</label>
                    <input type="text" id="modelo" name="modelo" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="matricula">Matrícula:</label>
                    <input type="text" id="matricula" name="matricula" required>
                </div>
                <div class="form-group">
                    <label for="anio">Año:</label>
                    <input type="number" id="anio" name="anio" min="1900" max="2030" required>
                </div>
                <div class="form-group">
                    <label for="vin">VIN:</label>
                    <input type="text" id="vin" name="vin">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group" style="align-self: flex-end;">
                    <button type="submit" name="agregar_vehiculo" class="btn btn-primary">Agregar Vehículo</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
function validarFormulario(event) {
    const clienteId = document.getElementById('cliente_id').value;
    if (!clienteId) {
        event.preventDefault();
        alert('Por favor, selecciona un cliente');
        return false;
    }
    return true;
}

function abrirModalCliente() {
    document.getElementById('modalCliente').style.display = 'block';
    document.getElementById('buscarClienteInput').value = '';
    buscarCliente();
    document.getElementById('buscarClienteInput').focus();
}

function cerrarModalCliente() {
    document.getElementById('modalCliente').style.display = 'none';
}

function buscarCliente() {
    const input = document.getElementById('buscarClienteInput');
    const filtro = input.value.toUpperCase();
    const tabla = document.getElementById('tablaClientesModal');
    const filas = tabla.getElementsByClassName('cliente-row');
    
    for (let i = 0; i < filas.length; i++) {
        const nombre = filas[i].cells[0].textContent.toUpperCase();
        const telefono = filas[i].cells[1].textContent.toUpperCase();
        
        if (nombre.indexOf(filtro) > -1 || telefono.indexOf(filtro) > -1) {
            filas[i].style.display = '';
        } else {
            filas[i].style.display = 'none';
        }
    }
}

function seleccionarCliente(btn) {
    const fila = btn.parentNode.parentNode;
    const clienteId = fila.getAttribute('data-cliente-id');
    const clienteNombre = fila.getAttribute('data-cliente-nombre');
    
    document.getElementById('cliente_id').value = clienteId;
    document.getElementById('clienteNombre').value = clienteNombre;
    cerrarModalCliente();
}

window.onclick = function(event) {
    const modal = document.getElementById('modalCliente');
    if (event.target === modal) {
        modal.style.display = 'none';
    }
}
</script>

<?php include 'includes/footer.php'; ?>