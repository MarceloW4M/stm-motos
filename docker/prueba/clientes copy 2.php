<?php
require_once 'includes/auth.php';
require_once 'includes/database.php';
require_once 'includes/header.php';

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
    
    header("Location: clientes.php?success=added");
    exit();
}

// Procesar eliminación de cliente
if (isset($_GET['eliminar'])) {
    $id = $_GET['eliminar'];
    $query = "DELETE FROM clientes WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    
    header("Location: clientes.php?success=deleted");
    exit();
}

// Obtener lista de clientes
$query = "SELECT * FROM clientes ORDER BY nombre";
$stmt = $db->prepare($query);
$stmt->execute();
$clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
:root {
    --primary: #2563eb;
    --primary-dark: #1e40af;
    --primary-light: #dbeafe;
    --success: #10b981;
    --danger: #ef4444;
    --warning: #f59e0b;
    --secondary: #64748b;
    --light: #f8fafc;
    --dark: #1e293b;
    --border: #e2e8f0;
    --shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
    --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: 100vh;
    padding: 20px;
}

.container {
    max-width: 1400px;
    margin: 0 auto;
}

/* Header Section */
.page-header {
    background: white;
    border-radius: 16px;
    padding: 30px;
    margin-bottom: 24px;
    box-shadow: var(--shadow-lg);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 20px;
}

.page-header h2 {
    color: var(--dark);
    font-size: 28px;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 12px;
}

.page-header h2 i {
    color: var(--primary);
}

.header-actions {
    display: flex;
    gap: 12px;
}

.btn {
    padding: 10px 20px;
    border-radius: 8px;
    border: none;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    text-decoration: none;
}

.btn-primary {
    background: var(--primary);
    color: white;
}

.btn-primary:hover {
    background: var(--primary-dark);
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

.btn-success {
    background: var(--success);
    color: white;
}

.btn-success:hover {
    background: #059669;
    transform: translateY(-2px);
}

.btn-danger {
    background: var(--danger);
    color: white;
}

.btn-danger:hover {
    background: #dc2626;
}

.btn-sm {
    padding: 6px 12px;
    font-size: 12px;
}

/* Alert Messages */
.alert {
    padding: 16px 20px;
    border-radius: 12px;
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    gap: 12px;
    animation: slideDown 0.3s ease;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.alert-success {
    background: #d1fae5;
    color: #065f46;
    border-left: 4px solid var(--success);
}

.alert i {
    font-size: 20px;
}

/* Search Section */
.search-section {
    background: white;
    border-radius: 16px;
    padding: 24px;
    margin-bottom: 24px;
    box-shadow: var(--shadow-lg);
}

.search-container {
    position: relative;
    margin-bottom: 16px;
}

.search-input-wrapper {
    position: relative;
}

.search-input-wrapper i {
    position: absolute;
    left: 16px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--secondary);
    font-size: 18px;
}

.search-input {
    width: 100%;
    padding: 14px 50px 14px 50px;
    border: 2px solid var(--border);
    border-radius: 12px;
    font-size: 16px;
    transition: all 0.3s ease;
}

.search-input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 4px var(--primary-light);
}

.clear-search {
    position: absolute;
    right: 16px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: var(--secondary);
    cursor: pointer;
    padding: 4px;
    display: none;
    transition: color 0.2s;
}

.clear-search:hover {
    color: var(--danger);
}

.search-stats {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
}

.result-count {
    color: var(--secondary);
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.result-count i {
    color: var(--primary);
}

.filter-tags {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.filter-tag {
    padding: 6px 12px;
    background: var(--primary-light);
    color: var(--primary);
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}

/* Table Section */
.table-section {
    background: white;
    border-radius: 16px;
    box-shadow: var(--shadow-lg);
    overflow: hidden;
    margin-bottom: 24px;
}

.table-wrapper {
    overflow-x: auto;
    max-height: 600px;
    position: relative;
}

.table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    min-width: 1200px;
}

.table thead {
    position: sticky;
    top: 0;
    z-index: 10;
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
}

.table th {
    padding: 16px 12px;
    text-align: left;
    color: white;
    font-weight: 600;
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    white-space: nowrap;
}

.table tbody tr {
    border-bottom: 1px solid var(--border);
    transition: all 0.2s ease;
}

.table tbody tr:hover {
    background: var(--light);
    transform: scale(1.01);
    box-shadow: var(--shadow);
}

.table td {
    padding: 16px 12px;
    vertical-align: middle;
    font-size: 14px;
    color: var(--dark);
}

/* Column Widths */
.col-id { width: 80px; }
.col-nombre { width: 200px; }
.col-telefono { width: 140px; }
.col-email { width: 220px; }
.col-direccion { width: 250px; }
.col-cuit { width: 150px; }
.col-acciones { width: 180px; text-align: center; }

.text-truncate {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 100%;
    display: block;
}

.badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
}

.badge-id {
    background: var(--primary-light);
    color: var(--primary);
}

.acciones-btns {
    display: flex;
    gap: 6px;
    justify-content: center;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: var(--secondary);
}

.empty-state i {
    font-size: 64px;
    color: var(--border);
    margin-bottom: 16px;
}

.empty-state h3 {
    color: var(--dark);
    margin-bottom: 8px;
}

/* Form Section */
.form-section {
    background: white;
    border-radius: 16px;
    padding: 30px;
    box-shadow: var(--shadow-lg);
}

.form-section h3 {
    color: var(--dark);
    font-size: 22px;
    font-weight: 700;
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.form-section h3 i {
    color: var(--primary);
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 20px;
    margin-bottom: 24px;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-group label {
    margin-bottom: 8px;
    color: var(--dark);
    font-weight: 600;
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.form-group label i {
    color: var(--primary);
    font-size: 12px;
}

.form-control {
    padding: 12px 16px;
    border: 2px solid var(--border);
    border-radius: 8px;
    font-size: 14px;
    transition: all 0.3s ease;
}

.form-control:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 4px var(--primary-light);
}

.form-actions {
    display: flex;
    gap: 12px;
    justify-content: flex-end;
    padding-top: 20px;
    border-top: 2px solid var(--border);
}

/* Custom Scrollbar */
.table-wrapper::-webkit-scrollbar {
    width: 10px;
    height: 10px;
}

.table-wrapper::-webkit-scrollbar-track {
    background: var(--light);
    border-radius: 10px;
}

.table-wrapper::-webkit-scrollbar-thumb {
    background: var(--primary);
    border-radius: 10px;
}

.table-wrapper::-webkit-scrollbar-thumb:hover {
    background: var(--primary-dark);
}

/* Responsive */
@media (max-width: 768px) {
    .page-header {
        flex-direction: column;
        align-items: stretch;
    }
    
    .header-actions {
        flex-direction: column;
    }
    
    .form-grid {
        grid-template-columns: 1fr;
    }
    
    .search-stats {
        flex-direction: column;
        align-items: flex-start;
    }
}

/* Loading Animation */
.loading {
    display: inline-block;
    width: 20px;
    height: 20px;
    border: 3px solid rgba(255,255,255,.3);
    border-radius: 50%;
    border-top-color: white;
    animation: spin 1s ease-in-out infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* Tooltip */
.tooltip-text {
    position: relative;
    cursor: help;
}

.tooltip-text:hover::after {
    content: attr(data-tooltip);
    position: absolute;
    bottom: 100%;
    left: 50%;
    transform: translateX(-50%);
    padding: 8px 12px;
    background: var(--dark);
    color: white;
    border-radius: 6px;
    font-size: 12px;
    white-space: nowrap;
    z-index: 100;
    margin-bottom: 5px;
}
</style>

<div class="container">
    <!-- Page Header -->
    <div class="page-header">
        <h2>
            <i class="fas fa-users"></i>
            Gestión de Clientes
        </h2>
        <div class="header-actions">
            <button class="btn btn-primary" onclick="scrollToForm()">
                <i class="fas fa-plus"></i>
                Nuevo Cliente
            </button>
        </div>
    </div>

    <!-- Success Messages -->
    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success" id="alertMessage">
            <i class="fas fa-check-circle"></i>
            <span>
                <?php 
                if ($_GET['success'] === 'added') echo 'Cliente agregado correctamente';
                if ($_GET['success'] === 'deleted') echo 'Cliente eliminado correctamente';
                ?>
            </span>
        </div>
    <?php endif; ?>

    <!-- Search Section -->
    <div class="search-section">
        <div class="search-container">
            <div class="search-input-wrapper">
                <i class="fas fa-search"></i>
                <input 
                    type="text" 
                    id="searchInput" 
                    class="search-input"
                    placeholder="Buscar por nombre, teléfono, email o CUIT..." 
                    oninput="searchClientes()">
                <button class="clear-search" id="clearSearch" onclick="clearSearch()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        <div class="search-stats">
            <div class="result-count" id="resultCount">
                <i class="fas fa-database"></i>
                <span>Total: <?php echo count($clientes); ?> clientes</span>
            </div>
        </div>
    </div>

    <!-- Table Section -->
    <div class="table-section">
        <div class="table-wrapper">
            <table class="table" id="clientesTable">
                <thead>
                    <tr>
                        <th class="col-id">ID</th>
                        <th class="col-nombre">Nombre</th>
                        <th class="col-telefono">Teléfono</th>
                        <th class="col-email">Email</th>
                        <th class="col-direccion">Dirección</th>
                        <th class="col-cuit">CUIT</th>
                        <th class="col-acciones">Acciones</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                    <?php foreach ($clientes as $cliente): ?>
                    <tr>
                        <td class="col-id">
                            <span class="badge badge-id">#<?php echo $cliente['id']; ?></span>
                        </td>
                        <td class="col-nombre">
                            <span class="text-truncate" title="<?php echo htmlspecialchars($cliente['nombre']); ?>">
                                <i class="fas fa-user" style="color: var(--primary); margin-right: 6px;"></i>
                                <?php echo htmlspecialchars($cliente['nombre']); ?>
                            </span>
                        </td>
                        <td class="col-telefono">
                            <i class="fas fa-phone" style="color: var(--success); margin-right: 6px;"></i>
                            <?php echo htmlspecialchars($cliente['telefono']); ?>
                        </td>
                        <td class="col-email">
                            <span class="text-truncate" title="<?php echo htmlspecialchars($cliente['email']); ?>">
                                <i class="fas fa-envelope" style="color: var(--warning); margin-right: 6px;"></i>
                                <?php echo htmlspecialchars($cliente['email']); ?>
                            </span>
                        </td>
                        <td class="col-direccion">
                            <span class="text-truncate" title="<?php echo htmlspecialchars($cliente['direccion']); ?>">
                                <?php echo htmlspecialchars($cliente['direccion']); ?>
                            </span>
                        </td>
                        <td class="col-cuit"><?php echo htmlspecialchars($cliente['cuit']); ?></td>
                        <td class="col-acciones">
                            <div class="acciones-btns">
                                <a href="clientes_m.php?id=<?php echo $cliente['id']; ?>" class="btn btn-primary btn-sm">
                                    <i class="fas fa-edit"></i> Editar
                                </a>
                                <a href="clientes.php?eliminar=<?php echo $cliente['id']; ?>" 
                                   class="btn btn-danger btn-sm" 
                                   onclick="return confirm('¿Está seguro de eliminar este cliente?')">
                                    <i class="fas fa-trash"></i> Eliminar
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div class="empty-state" id="emptyState" style="display: none;">
                <i class="fas fa-search"></i>
                <h3>No se encontraron clientes</h3>
                <p>Intenta con otros términos de búsqueda</p>
            </div>
        </div>
    </div>

    <!-- Form Section -->
    <div class="form-section" id="formSection">
        <h3>
            <i class="fas fa-user-plus"></i>
            Agregar Nuevo Cliente
        </h3>
        <form method="POST" id="clienteForm">
            <div class="form-grid">
                <div class="form-group">
                    <label for="nombre">
                        <i class="fas fa-user"></i>
                        Nombre Completo *
                    </label>
                    <input type="text" id="nombre" name="nombre" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="telefono">
                        <i class="fas fa-phone"></i>
                        Teléfono *
                    </label>
                    <input type="text" id="telefono" name="telefono" class="form-control" required>
                </div>

                <div class="form-group">
                    <label for="email">
                        <i class="fas fa-envelope"></i>
                        Email
                    </label>
                    <input type="email" id="email" name="email" class="form-control">
                </div>

                <div class="form-group">
                    <label for="cuit">
                        <i class="fas fa-id-card"></i>
                        CUIT
                    </label>
                    <input type="text" id="cuit" name="cuit" class="form-control" placeholder="XX-XXXXXXXX-X">
                </div>

                <div class="form-group" style="grid-column: 1 / -1;">
                    <label for="direccion">
                        <i class="fas fa-map-marker-alt"></i>
                        Dirección
                    </label>
                    <input type="text" id="direccion" name="direccion" class="form-control">
                </div>
            </div>

            <div class="form-actions">
                <button type="reset" class="btn btn-secondary">
                    <i class="fas fa-undo"></i>
                    Limpiar
                </button>
                <button type="submit" name="agregar_cliente" class="btn btn-success">
                    <i class="fas fa-save"></i>
                    Guardar Cliente
                </button>
            </div>
        </form>
    </div>
</div>

<script>
let searchTimeout;

function searchClientes() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(performSearch, 150);
    
    const input = document.getElementById('searchInput');
    const clearBtn = document.getElementById('clearSearch');
    clearBtn.style.display = input.value ? 'block' : 'none';
}

function performSearch() {
    const input = document.getElementById('searchInput');
    const filter = input.value.trim().toUpperCase();
    const tableBody = document.getElementById('tableBody');
    const rows = tableBody.getElementsByTagName('tr');
    const emptyState = document.getElementById('emptyState');
    const resultCount = document.getElementById('resultCount');
    
    let count = 0;
    
    for (let i = 0; i < rows.length; i++) {
        const cells = rows[i].getElementsByTagName('td');
        const nombre = cells[1]?.textContent || '';
        const telefono = cells[2]?.textContent || '';
        const email = cells[3]?.textContent || '';
        const cuit = cells[5]?.textContent || '';
        
        const match = nombre.toUpperCase().includes(filter) || 
                     telefono.toUpperCase().includes(filter) ||
                     email.toUpperCase().includes(filter) ||
                     cuit.toUpperCase().includes(filter);
        
        rows[i].style.display = match ? '' : 'none';
        if (match) count++;
    }
    
    emptyState.style.display = count === 0 ? 'block' : 'none';
    tableBody.style.display = count === 0 ? 'none' : '';
    
    resultCount.innerHTML = `
        <i class="fas fa-database"></i>
        <span>${filter ? `Encontrados: ${count} de ${rows.length}` : `Total: ${rows.length} clientes`}</span>
    `;
}

function clearSearch() {
    const input = document.getElementById('searchInput');
    input.value = '';
    input.focus();
    searchClientes();
}

function scrollToForm() {
    document.getElementById('formSection').scrollIntoView({ 
        behavior: 'smooth',
        block: 'start'
    });
    setTimeout(() => document.getElementById('nombre').focus(), 500);
}

// Auto-hide alert messages
setTimeout(() => {
    const alert = document.getElementById('alertMessage');
    if (alert) {
        alert.style.animation = 'slideDown 0.3s ease reverse';
        setTimeout(() => alert.remove(), 300);
    }
}, 5000);

// Keyboard shortcuts
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') clearSearch();
    if (e.ctrlKey && e.key === 'f') {
        e.preventDefault();
        document.getElementById('searchInput').focus();
    }
});

// Form validation
document.getElementById('clienteForm').addEventListener('submit', (e) => {
    const nombre = document.getElementById('nombre').value.trim();
    const telefono = document.getElementById('telefono').value.trim();
    
    if (!nombre || !telefono) {
        e.preventDefault();
        alert('Por favor complete los campos obligatorios');
    }
});
</script>

<?php include 'includes/footer.php'; ?>