# STM Taller de Motos - AI Agent Instructions

## Project Overview
STM is a motorcycle workshop management system built with PHP 8.2, MySQL, and Docker. It manages clients, vehicles, parts inventory, repair orders, and schedules. The application uses session-based authentication and serves a single authenticated user interface.

## Architecture

### Core Stack
- **Backend**: PHP 8.2 (PDO + MySQLi)
- **Database**: MySQL with `stm_taller` schema
- **Web Server**: Nginx (Alpine image)
- **Containerization**: Docker Compose with 3 services (nginx, php-fpm, mysql via host)
- **PDF Generation**: TCPDF library (bundled in `/src/tcpdf/`)

### Database Schema (4 Core Tables)
- `usuarios` - Authentication (bcrypt passwords, e.g., admin/admin123 hash)
- `clientes` - Client records with contact info & CUIT
- `vehiculos` - Vehicle registry (marca, modelo, matricula, VIN) linked to clientes
- `repuestos` - Parts inventory with stock tracking
- `turnos` - Service appointments/shifts (has foreign keys to clientes & vehiculos)
- `ordenes_reparacion` - Repair orders linked to turnos

### Directory Structure
```
src/                          # Application root (PHP 8.2)
├── includes/                 # Shared utilities
│   ├── auth.php             # Session management & requireAuth() function
│   ├── config.php           # DB credentials & constants (DB_HOST, DB_NAME, SITE_NAME)
│   ├── database.php         # Database class with PDO getConnection()
│   ├── header.php           # HTML header template with nav menu
│   └── footer.php           # HTML footer template
├── *-list pages             # clientes.php, vehiculos.php, repuestos.php, turnos.php
├── editar_*.php             # Edit pages (editar_repuesto.php, editar_turno.php, etc.)
├── detalle_orden.php        # Repair order detail view
├── generar_pdf.php          # Single-page A4 invoice generation (FacturaSTM class)
├── generar2_pdf.php         # Alternative PDF layout
├── informes.php             # Reporting dashboard
├── login.php                # Auth entry point
├── css/                     # Stylesheets (style.css, styleess.css)
└── tcpdf/                   # Bundled TCPDF library (do not modify)
```

## Key Patterns & Conventions

### Authentication Flow
1. All pages require `require_once 'includes/auth.php'` at the top
2. Call `requireAuth()` after includes to guard page
3. Session stored as `$_SESSION['user_id']` and `$_SESSION['username']`
4. Redirection handled by `auth.php` functions, not inline

### Database Access
- Use `Database` class: `$database = new Database(); $db = $database->getConnection();`
- Always use **parameterized queries** with `bindParam()` or `bindValue()` (prevents SQL injection)
- Example pattern:
  ```php
  $query = "SELECT * FROM clientes WHERE id = :id";
  $stmt = $db->prepare($query);
  $stmt->bindParam(':id', $id);
  $stmt->execute();
  $result = $stmt->fetch(PDO::FETCH_ASSOC);
  ```

### List Page Patterns (clientes.php, vehiculos.php, etc.)
- POST form with CSRF-like protection via `isset($_POST['agregar_cliente'])` checks
- GET parameter for deletion: `?eliminar=id`
- **Pagination**: Hard-coded 50 records per page; uses LIMIT/OFFSET
- **Search**: Optional `?buscar=term` parameter with wildcard LIKE queries
- Redirect to self after mutations (`header("Location: clientes.php")`)

### PDF Generation (Critical!)
- **Output Buffering**: Must call `ob_start()` BEFORE includes, clear with `ob_end_clean()` before TCPDF output
- **TCPDF Class**: Extend `TCPDF` to override `Header()` and `Footer()` (see `FacturaSTM` in generar_pdf.php)
- **Page Layout**: Use `SetAutoPageBreak(false)` for single-page documents; track Y position manually
- **Single-page constraint**: `generar_pdf.php` is optimized for A4 single-page invoices only
- TCPDF requires UTF-8 encoding: `parent::__construct('P', 'mm', 'A4', true, 'UTF-8', false)`

### HTML Templating
- No templating engine; inline PHP with HTML
- `header.php` included on each page; contains logged-in navbar with links to main sections
- `footer.php` for closing tags
- Inline `<?php ?>` blocks for data rendering (no template variables)

## Developer Workflows

### Local Development
```bash
# Start all services (MySQL, PHP-FPM, Nginx on port 8080)
docker-compose up --build

# Access application
http://localhost:8080

# phpMyAdmin access
http://localhost:8080  # (or check docker-compose.yml for actual port mapping)

# Full reinstall (destructive)
bash reinstall.sh
```

### Database Initialization
- `init_database.sql` run automatically on docker-compose up (if MySQL container present)
- Default user: `admin` / `admin123` (bcrypt hash in schema)
- Run schema changes inside container:
  ```bash
  docker exec stm_php mysql -h <host> -u root -p<password> stm_taller < schema.sql
  ```

### Configuration
- DB credentials in `src/includes/config.php` (hardcoded; not environment-based)
- Current DB host: `10.50.0.30:3406` (non-standard port; verify before connecting)
- PHP limits: 256MB memory, 120s execution, 20MB file upload (docker-compose.yml)

### Testing/Verification
- Healthcheck endpoint: `src/ping.php` (checks container is responding)
- No unit tests present; manual testing via UI only

## Critical Implementation Details

### Pagination & CRUD
- Always use `isset($_GET['pagina'])` for page number; default to 1
- Calculate `$offset = ($pagina_actual - 1) * $registros_por_pagina`
- Execute COUNT query for total, then paginated SELECT
- **Important**: Bind pagination params as `PDO::PARAM_INT` to avoid SQL injection

### Encoding & Localization
- Database charset: `utf8mb4_unicode_ci` (supports emoji, accents)
- PHP file header should declare `<meta charset="UTF-8">`
- Language: Spanish (site name "STM - Aventura Motos", Spanish nav labels)

### Security Notes
- No CSRF tokens present (simple POST checks only; vulnerabilities possible)
- No input sanitization (use parameterized queries to mitigate SQL injection)
- Session-based auth only (no JWT, no API tokens)
- Passwords stored as bcrypt (PHP default hash function)

## Common Gotchas

1. **Missing ob_start() before TCPDF**: Will cause "headers already sent" errors. Always clear output buffer before PDF output.
2. **Database host mismatch**: Config uses `10.50.0.30:3406`, not localhost. Verify connectivity before debugging queries.
3. **PDO PARAM_INT binding**: Forget this for LIMIT/OFFSET and you'll get "SQLSTATE[HY093]" errors.
4. **Session not started**: Always check `if (session_status() === PHP_SESSION_NONE)` before calling session functions.
5. **TCPDF UTF-8 flag**: Must pass `true` as 4th constructor arg; otherwise accented characters break in PDFs.

## File Modification Priorities

When making changes:
1. **Core logic**: Modify `includes/database.php`, `includes/auth.php` with extreme caution (affects all pages)
2. **List pages**: Safe to modify clientes.php, vehiculos.php patterns; follow pagination convention
3. **PDF generation**: Only modify generar_pdf.php if you understand TCPDF and ob_* functions
4. **Styling**: Modify `css/style.css` freely; no CSS framework dependencies
5. **HTML structure**: Maintain `header.php` includes and `requireAuth()` checks

## Next Steps for AI Agents
- Always run `docker-compose up --build` before testing changes
- Test CRUD operations (create, read, update, delete) on all list pages
- Verify PDF generation doesn't break with new form fields (test generar_pdf.php output)
- Confirm pagination works with search filters applied
- Check auth redirect on logout
