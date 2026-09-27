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

// Obtener ID del vehículo a editar
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;

// Obtener datos del vehículo
$vehiculo = null;
if ($id) {
    $query = "SELECT * FROM vehiculos WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    $vehiculo = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Si no existe el vehículo, redirigir
if (!$vehiculo) {
    header("Location: vehiculos.php");
    exit();
}

// Procesar actualización
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $marca = $_POST['marca'];
    $modelo = $_POST['modelo'];
    $matricula = $_POST['matricula'];
    $anio = $_POST['anio'];
    $vin = $_POST['vin'];
    $cliente_id = filter_input(INPUT_POST, 'cliente_id', FILTER_VALIDATE_INT);

    if (!$cliente_id) {
        $mensaje = "❌ Debe seleccionar un cliente válido";
    } else {
    
        try {
            $query = "UPDATE vehiculos SET marca = :marca, modelo = :modelo, matricula = :matricula, 
                      anio = :anio, vin = :vin, cliente_id = :cliente_id WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':marca', $marca);
            $stmt->bindParam(':modelo', $modelo);
            $stmt->bindParam(':matricula', $matricula);
            $stmt->bindParam(':anio', $anio);
            $stmt->bindParam(':vin', $vin);
            $stmt->bindParam(':cliente_id', $cliente_id, PDO::PARAM_INT);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            
            $mensaje = "✅ Vehículo actualizado exitosamente";
            // Recargar datos
            $vehiculo = array_merge($vehiculo, $_POST);
            $vehiculo['cliente_id'] = $cliente_id;
        } catch (PDOException $e) {
            $mensaje = "❌ Error al actualizar vehículo: " . $e->getMessage();
        }
    }
}

// Obtener lista de clientes
$query_clientes = "SELECT id, nombre, telefono FROM clientes ORDER BY nombre";
$stmt_clientes = $db->prepare($query_clientes);
$stmt_clientes->execute();
$clientes = $stmt_clientes->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container">
    <h2>Editar Vehículo</h2>
    
    <?php if ($mensaje): ?>
    <div class="alert"><?php echo $mensaje; ?></div>
    <?php endif; ?>
    
    <div class="form-container">
        <form method="POST" onsubmit="return validarFormulario(event)">
            <div class="form-row">
                <div class="form-group">
                    <label for="clienteNombreTurno">Cliente:</label>
                    <div style="display: flex; gap: 8px;">
                        <?php
                        $cliente_nombre_actual = '';
                        foreach ($clientes as $cliente) {
                            if ((int)$cliente['id'] === (int)$vehiculo['cliente_id']) {
                                $cliente_nombre_actual = $cliente['nombre'];
                                break;
                            }
                        }
                        ?>
                        <input type="text" id="clienteNombreTurno" placeholder="Seleccionar cliente..." readonly
                               value="<?php echo htmlspecialchars($cliente_nombre_actual); ?>"
                               style="flex: 1; background-color: #f5f5f5; padding: 8px 12px; border: 1px solid #ced4da; border-radius: 4px;">
                        <button type="button" class="btn btn-primary btn-sm" onclick="abrirModalBuscar()">Buscar</button>
                    </div>
                    <input type="hidden" id="cliente_id" name="cliente_id" value="<?php echo (int)$vehiculo['cliente_id']; ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="marca">Marca:</label>
                    <input type="text" id="marca" name="marca" value="<?php echo htmlspecialchars($vehiculo['marca']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="modelo">Modelo:</label>
                    <input type="text" id="modelo" name="modelo" value="<?php echo htmlspecialchars($vehiculo['modelo']); ?>" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="matricula">Matrícula:</label>
                    <input type="text" id="matricula" name="matricula" value="<?php echo htmlspecialchars($vehiculo['matricula']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="anio">Año:</label>
                    <input type="number" id="anio" name="anio" value="<?php echo $vehiculo['anio']; ?>" min="1900" max="2030" required>
                </div>
                
                <div class="form-group">
                    <label for="vin">VIN:</label>
                    <input type="text" id="vin" name="vin" value="<?php echo htmlspecialchars($vehiculo['vin']); ?>">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group" style="align-self: flex-end;">
                    <button type="submit" class="btn btn-primary">Actualizar Vehículo</button>
                    <a href="vehiculos.php" class="btn btn-secondary">Cancelar</a>
                </div>
            </div>
        </form>
    </div>

    <!-- Modal para buscar cliente -->
    <div id="modalBuscarCliente" class="modal" style="display: none;">
        <div class="modal-content" style="max-width: 600px;">
            <span class="close" onclick="cerrarModalBuscar()">&times;</span>
            <h3>Seleccionar Cliente</h3>
            <div class="search-container" style="margin-bottom: 20px;">
                <input type="text" id="buscarClienteTurnoInput" placeholder="Buscar por nombre o teléfono..."
                       style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;"
                       onkeyup="buscarClienteTurno()">
            </div>
            <div style="max-height: 400px; overflow-y: auto;">
                <table class="table" id="tablaClientesTurnoModal" style="margin: 0;">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Teléfono</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clientes as $cliente): ?>
                        <tr class="cliente-row"
                            data-cliente-id="<?php echo (int)$cliente['id']; ?>"
                            data-cliente-nombre="<?php echo htmlspecialchars($cliente['nombre']); ?>">
                            <td><?php echo htmlspecialchars($cliente['nombre']); ?></td>
                            <td><?php echo htmlspecialchars($cliente['telefono'] ?? ''); ?></td>
                            <td>
                                <button type="button" class="btn btn-primary btn-sm" onclick="seleccionarClienteTurno(this)">
                                    Seleccionar
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
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

function abrirModalBuscar() {
    document.getElementById('modalBuscarCliente').style.display = 'block';
    document.getElementById('buscarClienteTurnoInput').value = '';
    buscarClienteTurno();
    document.getElementById('buscarClienteTurnoInput').focus();
}

function cerrarModalBuscar() {
    document.getElementById('modalBuscarCliente').style.display = 'none';
}

function buscarClienteTurno() {
    const input = document.getElementById('buscarClienteTurnoInput');
    const filtro = input.value.toUpperCase();
    const tabla = document.getElementById('tablaClientesTurnoModal');
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

function seleccionarClienteTurno(btn) {
    const fila = btn.parentNode.parentNode;
    const clienteId = fila.getAttribute('data-cliente-id');
    const clienteNombre = fila.getAttribute('data-cliente-nombre');

    document.getElementById('cliente_id').value = clienteId;
    document.getElementById('clienteNombreTurno').value = clienteNombre;
    cerrarModalBuscar();
}

window.addEventListener('click', function(event) {
    const modal = document.getElementById('modalBuscarCliente');
    if (event.target === modal) {
        cerrarModalBuscar();
    }
});
</script>

<?php include 'includes/footer.php'; ?>
