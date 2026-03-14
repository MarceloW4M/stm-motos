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
    $nombre = $_POST['nombre'];
    $telefono = $_POST['telefono'];
    $email = $_POST['email'];
    $direccion = $_POST['direccion'];
    $cuit = $_POST['cuit'];
    
    $query = "INSERT INTO clientes (nombre, telefono, email, direccion, cuit) 
              VALUES (:nombre, :telefono, :email, :direccion, :cuit)";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':nombre', $nombre);
    $stmt->bindParam(':telefono', $telefono);
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':direccion', $direccion);
    $stmt->bindParam(':cuit', $cuit);
    $stmt->execute();
    
    header("Location: clientes.php");
    exit();
}

// Procesar eliminación de cliente
if (isset($_GET['eliminar'])) {
    $id = $_GET['eliminar'];
    $query = "DELETE FROM clientes WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    
    header("Location: clientes.php");
    exit();
}

// Obtener lista de clientes
$query = "SELECT * FROM clientes ORDER BY nombre";
$stmt = $db->prepare($query);
$stmt->execute();
$clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container">
    <h2>Gestión de Clientes</h2>    
    
    <!-- Búsqueda -->
    <div class="search-container">
        <input type="text" id="searchInput" placeholder="Buscar cliente por nombre o número de teléfono..." onkeyup="searchClientes()">
    </div>
    
    <!-- Tabla de clientes -->
    <table class="table" id="clientesTable">
        <thead>
            <tr>
                <th>ID</th>
                <th>Nombre</th>
                <th>Teléfono</th>
                <th>Email</th>
                <th>Dirección</th>               
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($clientes as $cliente): ?>
            <tr>
                <td><?php echo $cliente['id']; ?></td>
                <td><?php echo htmlspecialchars($cliente['nombre']); ?></td>
                <td><?php echo htmlspecialchars($cliente['telefono']); ?></td>
                <td><?php echo htmlspecialchars($cliente['email']); ?></td>
                <td><?php echo htmlspecialchars($cliente['direccion']); ?></td>               
                <td>
                    <a href="clientes_m.php?id=<?php echo $cliente['id']; ?>" class="btn btn-primary">Editar</a>
                    <a href="clientes.php?eliminar=<?php echo $cliente['id']; ?>" class="btn btn-danger" onclick="return confirm('¿Está seguro de eliminar este cliente?')">Eliminar</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Formulario para agregar cliente -->
    <div class="form-container">
        <h3>Agregar Nuevo Cliente</h3>
        <form method="POST">
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
            
            <div class="form-group">
                <label for="direccion">Dirección:</label>
                <input type="text" id="direccion" name="direccion">
            </div>           
            
            <button type="submit" name="agregar_cliente" class="btn btn-primary">Agregar Cliente</button>
        </form>
    </div>
</div>

<script>
function searchClientes() {
    const input = document.getElementById('searchInput');
    const filter = input.value.toUpperCase();
    const table = document.getElementById('clientesTable');
    const tbody = table.getElementsByTagName('tbody')[0];
    const tr = tbody.getElementsByTagName('tr');
    
    // Comenzar desde 0 para recorrer todas las filas del tbody
    for (let i = 0; i < tr.length; i++) {
        const tds = tr[i].getElementsByTagName('td');
        let showRow = false;
        
        // Buscar en las columnas: nombre (índice 1) y teléfono (índice 2)
        const nombre = tds[1] ? tds[1].textContent || tds[1].innerText : '';
        const telefono = tds[2] ? tds[2].textContent || tds[2].innerText : '';
        
        if (nombre.toUpperCase().indexOf(filter) > -1 || 
            telefono.toUpperCase().indexOf(filter) > -1) {
            showRow = true;
        }
        
        tr[i].style.display = showRow ? '' : 'none';
    }
}

// Función alternativa que también incluye búsqueda por CUIT
function searchClientesAdvanced() {
    const input = document.getElementById('searchInput');
    const filter = input.value.toUpperCase();
    const table = document.getElementById('clientesTable');
    const tbody = table.getElementsByTagName('tbody')[0];
    const tr = tbody.getElementsByTagName('tr');
    
    for (let i = 0; i < tr.length; i++) {
        const tds = tr[i].getElementsByTagName('td');
        let showRow = false;
        
        // Buscar en las columnas: nombre (1), teléfono (2), CUIT (5)
        const searchColumns = [1, 2, 5];
        
        for (let j = 0; j < searchColumns.length; j++) {
            const colIndex = searchColumns[j];
            if (tds[colIndex]) {
                const txtValue = tds[colIndex].textContent || tds[colIndex].innerText;
                if (txtValue.toUpperCase().indexOf(filter) > -1) {
                    showRow = true;
                    break;
                }
            }
        }
        
        tr[i].style.display = showRow ? '' : 'none';
    }
}
</script>

<?php include 'includes/footer.php'; ?>