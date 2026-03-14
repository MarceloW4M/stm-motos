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
    
    $query = "INSERT INTO clientes (nombre, telefono, email, direccion) 
              VALUES (:nombre, :telefono, :email, :direccion)";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':nombre', $nombre);
    $stmt->bindParam(':telefono', $telefono);
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':direccion', $direccion);   
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
        <input type="text" id="searchInput" placeholder="Buscar cliente...">
        <button class="btn btn-primary" onclick="searchClientes()">Buscar</button>
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
                <td><?php echo $cliente['nombre']; ?></td>
                <td><?php echo $cliente['telefono']; ?></td>
                <td><?php echo $cliente['email']; ?></td>
                <td><?php echo $cliente['direccion']; ?></td>
                <td>
                    <a href="editar_cliente.php?id=<?php echo $cliente['id']; ?>" class="btn btn-primary">Editar</a>
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
    const tr = table.getElementsByTagName('tr');
    
    for (let i = 1; i < tr.length; i++) {
        const td = tr[i].getElementsByTagName('td')[1]; // Columna de nombre
        if (td) {
            const txtValue = td.textContent || td.innerText;
            if (txtValue.toUpperCase().indexOf(filter) > -1) {
                tr[i].style.display = '';
            } else {
                tr[i].style.display = 'none';
            }
        }
    }
}
</script>

<?php include 'includes/footer.php'; ?>
