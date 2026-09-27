<?php

function ensureAdministracionSchema(PDO $db): void
{
    $statements = [
        "CREATE TABLE IF NOT EXISTS admin_configuraciones (
            id INT AUTO_INCREMENT PRIMARY KEY,
            clave VARCHAR(100) NOT NULL UNIQUE,
            valor VARCHAR(255) NOT NULL,
            descripcion VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS admin_iva (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(100) NOT NULL UNIQUE,
            porcentaje DECIMAL(6,2) NOT NULL DEFAULT 0,
            activo TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS admin_impuestos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(100) NOT NULL UNIQUE,
            tipo ENUM('porcentaje', 'fijo') NOT NULL DEFAULT 'porcentaje',
            valor DECIMAL(12,2) NOT NULL DEFAULT 0,
            activo TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS admin_tipos_factura (
            id INT AUTO_INCREMENT PRIMARY KEY,
            codigo VARCHAR(10) NOT NULL UNIQUE,
            nombre VARCHAR(100) NOT NULL,
            letra VARCHAR(5) NOT NULL,
            activo TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS admin_depositos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(100) NOT NULL UNIQUE,
            ubicacion VARCHAR(150) NULL,
            descripcion TEXT NULL,
            activo TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS repuestos_stock_deposito (
            id INT AUTO_INCREMENT PRIMARY KEY,
            repuesto_id INT NOT NULL,
            deposito_id INT NOT NULL,
            stock INT NOT NULL DEFAULT 0,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uk_repuesto_deposito (repuesto_id, deposito_id),
            CONSTRAINT fk_rsd_repuesto FOREIGN KEY (repuesto_id) REFERENCES repuestos(id) ON DELETE CASCADE,
            CONSTRAINT fk_rsd_deposito FOREIGN KEY (deposito_id) REFERENCES admin_depositos(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS admin_compras (
            id INT AUTO_INCREMENT PRIMARY KEY,
            numero_compra VARCHAR(30) NOT NULL UNIQUE,
            fecha DATE NOT NULL,
            proveedor VARCHAR(150) NOT NULL,
            deposito_id INT NOT NULL,
            moneda VARCHAR(10) NOT NULL DEFAULT 'ARS',
            cotizacion_dolar DECIMAL(12,4) NOT NULL DEFAULT 1,
            subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
            impuestos_total DECIMAL(12,2) NOT NULL DEFAULT 0,
            total DECIMAL(12,2) NOT NULL DEFAULT 0,
            observaciones TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_admin_compra_deposito FOREIGN KEY (deposito_id) REFERENCES admin_depositos(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS admin_compra_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            compra_id INT NOT NULL,
            repuesto_id INT NOT NULL,
            cantidad INT NOT NULL,
            costo_unitario DECIMAL(12,2) NOT NULL,
            subtotal DECIMAL(12,2) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_admin_compra_item_compra FOREIGN KEY (compra_id) REFERENCES admin_compras(id) ON DELETE CASCADE,
            CONSTRAINT fk_admin_compra_item_repuesto FOREIGN KEY (repuesto_id) REFERENCES repuestos(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS admin_presupuestos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            numero_presupuesto VARCHAR(30) NOT NULL UNIQUE,
            fecha DATE NOT NULL,
            cliente_id INT NOT NULL,
            vehiculo_id INT NULL,
            descripcion TEXT NULL,
            subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
            impuestos_total DECIMAL(12,2) NOT NULL DEFAULT 0,
            total DECIMAL(12,2) NOT NULL DEFAULT 0,
            validez_dias INT NOT NULL DEFAULT 15,
            estado ENUM('borrador', 'emitido', 'aprobado', 'rechazado') NOT NULL DEFAULT 'emitido',
            observaciones TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_admin_presupuesto_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id),
            CONSTRAINT fk_admin_presupuesto_vehiculo FOREIGN KEY (vehiculo_id) REFERENCES vehiculos(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS admin_presupuesto_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            presupuesto_id INT NOT NULL,
            concepto VARCHAR(255) NOT NULL,
            cantidad DECIMAL(10,2) NOT NULL DEFAULT 1,
            precio_unitario DECIMAL(12,2) NOT NULL DEFAULT 0,
            subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_admin_presupuesto_item_presupuesto FOREIGN KEY (presupuesto_id) REFERENCES admin_presupuestos(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS admin_remitos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            numero_remito VARCHAR(30) NOT NULL UNIQUE,
            fecha DATE NOT NULL,
            cliente_id INT NOT NULL,
            vehiculo_id INT NULL,
            orden_id INT NULL,
            descripcion TEXT NULL,
            estado ENUM('emitido', 'entregado', 'anulado') NOT NULL DEFAULT 'emitido',
            observaciones TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_admin_remito_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id),
            CONSTRAINT fk_admin_remito_vehiculo FOREIGN KEY (vehiculo_id) REFERENCES vehiculos(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS admin_remito_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            remito_id INT NOT NULL,
            concepto VARCHAR(255) NOT NULL,
            cantidad DECIMAL(10,2) NOT NULL DEFAULT 1,
            observaciones VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_admin_remito_item_remito FOREIGN KEY (remito_id) REFERENCES admin_remitos(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS admin_facturas (
            id INT AUTO_INCREMENT PRIMARY KEY,
            orden_id INT NOT NULL,
            tipo_factura_id INT NOT NULL,
            numero_factura VARCHAR(30) NOT NULL UNIQUE,
            fecha_emision DATE NOT NULL,
            subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
            impuestos_total DECIMAL(12,2) NOT NULL DEFAULT 0,
            total DECIMAL(12,2) NOT NULL DEFAULT 0,
            moneda VARCHAR(10) NOT NULL DEFAULT 'ARS',
            cotizacion_dolar DECIMAL(12,4) NOT NULL DEFAULT 1,
            observaciones TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uk_admin_factura_orden (orden_id),
            CONSTRAINT fk_admin_factura_tipo FOREIGN KEY (tipo_factura_id) REFERENCES admin_tipos_factura(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];

    foreach ($statements as $sql) {
        $db->exec($sql);
    }

    $db->exec("INSERT IGNORE INTO admin_configuraciones (clave, valor, descripcion) VALUES
        ('valor_dolar', '1.00', 'Cotizacion de referencia del dolar'),
        ('empresa_razon_social', 'Sur Bateria', 'Razon social visible en documentos'),
        ('empresa_cuit', '', 'Identificacion fiscal de la empresa')
    ");

    $db->exec("INSERT IGNORE INTO admin_iva (nombre, porcentaje, activo) VALUES
        ('IVA 0%', 0.00, 1),
        ('IVA 10.5%', 10.50, 1),
        ('IVA 21%', 21.00, 1),
        ('IVA 27%', 27.00, 1)
    ");

    $db->exec("INSERT IGNORE INTO admin_impuestos (nombre, tipo, valor, activo) VALUES
        ('Sin impuesto', 'porcentaje', 0.00, 1),
        ('Percepcion IIBB', 'porcentaje', 3.00, 1),
        ('Impuesto interno', 'porcentaje', 5.00, 1)
    ");

    $db->exec("INSERT IGNORE INTO admin_tipos_factura (codigo, nombre, letra, activo) VALUES
        ('FA', 'Factura A', 'A', 1),
        ('FB', 'Factura B', 'B', 1),
        ('FC', 'Factura C', 'C', 1),
        ('NCA', 'Nota de Credito A', 'A', 1)
    ");

    $db->exec("INSERT IGNORE INTO admin_depositos (nombre, ubicacion, descripcion, activo) VALUES
        ('Principal', 'Taller central', 'Deposito principal de repuestos', 1)
    ");
}

function adminTableExists(PDO $db, string $tableName): bool
{
    $stmt = $db->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table_name');
    $stmt->bindParam(':table_name', $tableName);
    $stmt->execute();

    return (int) $stmt->fetchColumn() > 0;
}

function adminRedirectTo(string $page, string $mensaje, string $tipo = 'success'): void
{
    header('Location: ' . $page . '?mensaje=' . urlencode($mensaje) . '&tipo=' . urlencode($tipo));
    exit();
}

function nextAdminNumber(PDO $db, string $table, string $column, string $prefix): string
{
    $pattern = $prefix . '-' . date('Y') . '-%';
    $stmt = $db->prepare("SELECT $column FROM $table WHERE $column LIKE :pattern ORDER BY id DESC LIMIT 1");
    $stmt->bindParam(':pattern', $pattern);
    $stmt->execute();
    $ultimo = $stmt->fetchColumn();
    $secuencia = 1;

    if ($ultimo) {
        $partes = explode('-', (string) $ultimo);
        $ultimoNumero = (int) end($partes);
        $secuencia = $ultimoNumero + 1;
    }

    return $prefix . '-' . date('Y') . '-' . str_pad((string) $secuencia, 4, '0', STR_PAD_LEFT);
}

function getPorcentajeById(PDO $db, string $table, int $id): float
{
    if ($id <= 0) {
        return 0.0;
    }

    $stmt = $db->prepare("SELECT porcentaje FROM $table WHERE id = :id LIMIT 1");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $valor = $stmt->fetchColumn();

    return $valor === false ? 0.0 : (float) $valor;
}

function getImpuestoData(PDO $db, int $id): array
{
    if ($id <= 0) {
        return ['tipo' => 'porcentaje', 'valor' => 0.0];
    }

    $stmt = $db->prepare('SELECT tipo, valor FROM admin_impuestos WHERE id = :id LIMIT 1');
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$data) {
        return ['tipo' => 'porcentaje', 'valor' => 0.0];
    }

    return ['tipo' => $data['tipo'], 'valor' => (float) $data['valor']];
}

function getAdminConfig(PDO $db, string $clave, string $default = ''): string
{
    $stmt = $db->prepare('SELECT valor FROM admin_configuraciones WHERE clave = :clave LIMIT 1');
    $stmt->bindParam(':clave', $clave);
    $stmt->execute();
    $valor = $stmt->fetchColumn();

    return $valor === false ? $default : (string) $valor;
}

function setAdminConfig(PDO $db, string $clave, string $valor, string $descripcion = ''): void
{
    $sql = "INSERT INTO admin_configuraciones (clave, valor, descripcion)
            VALUES (:clave, :valor, :descripcion)
            ON DUPLICATE KEY UPDATE valor = VALUES(valor), descripcion = VALUES(descripcion)";
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':clave', $clave);
    $stmt->bindParam(':valor', $valor);
    $stmt->bindParam(':descripcion', $descripcion);
    $stmt->execute();
}
