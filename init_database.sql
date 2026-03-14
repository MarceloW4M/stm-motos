-- Crear base de datos
CREATE DATABASE IF NOT EXISTS stm_taller CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE stm_taller;

-- Tabla de usuarios
CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insertar usuario por defecto (contraseña: admin123)
INSERT INTO usuarios (username, password, nombre) 
VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrador');

-- Tabla de clientes
CREATE TABLE clientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    telefono VARCHAR(20) NOT NULL,
    email VARCHAR(100),
    direccion TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabla de vehículos
CREATE TABLE vehiculos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT NOT NULL,
    categoria VARCHAR(50),
    marca VARCHAR(50) NOT NULL,
    modelo VARCHAR(50) NOT NULL,
    matricula VARCHAR(20) NOT NULL,
    anio INT,
    vin VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE
);

-- Tabla de repuestos
CREATE TABLE repuestos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT,
    precio DECIMAL(10,2) NOT NULL,
    stock INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabla de servicios
CREATE TABLE servicios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT,
    precio_estimado DECIMAL(10,2) NOT NULL DEFAULT 0,
    duracion_estimada INT NOT NULL DEFAULT 60,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabla de turnos
CREATE TABLE turnos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT NOT NULL,
    vehiculo_id INT NOT NULL,
    mecanico VARCHAR(50),
    fecha DATE NOT NULL,
    hora_inicio TIME NOT NULL,
    hora_fin TIME NOT NULL,
    servicio VARCHAR(100) NOT NULL,
    descripcion TEXT,
    observaciones TEXT,
    estado ENUM('programado', 'en_proceso', 'completado', 'cancelado') DEFAULT 'programado',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE,
    FOREIGN KEY (vehiculo_id) REFERENCES vehiculos(id) ON DELETE CASCADE
);

-- Tabla intermedia para repuestos utilizados en turnos
CREATE TABLE turno_repuestos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    turno_id INT NOT NULL,
    repuesto_id INT NOT NULL,
    cantidad INT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (turno_id) REFERENCES turnos(id) ON DELETE CASCADE,
    FOREIGN KEY (repuesto_id) REFERENCES repuestos(id) ON DELETE CASCADE
);

-- Tabla temporal para insumos historicos migrados desde Access
CREATE TABLE historico_insumos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT NULL,
    id_cliente_access VARCHAR(50) NULL,
    orden INT NOT NULL,
    codigo_equipo VARCHAR(100) NULL,
    pieza_insumo VARCHAR(255) NOT NULL,
    precio_estimado DECIMAL(12,2) NULL,
    abonado DECIMAL(12,2) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_hist_insumo (orden, pieza_insumo, precio_estimado, abonado, id_cliente_access),
    KEY idx_hist_insumo_cliente (cliente_id),
    KEY idx_hist_insumo_access (id_cliente_access),
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE SET NULL
);

-- Insertar algunos datos de ejemplo
INSERT INTO clientes (nombre, telefono, email, direccion) VALUES
('Juan Pérez', '123456789', 'juan@email.com', 'Calle Falsa 123'),
('María García', '987654321', 'maria@email.com', 'Avenida Real 456');

INSERT INTO vehiculos (cliente_id, marca, modelo, matricula, anio, vin) VALUES
(1, 'Yamaha', 'YZF-R3', 'ABC123', 2020, 'YAMAHA123456789'),
(2, 'Honda', 'CBR500R', 'DEF456', 2021, 'HONDA987654321');

INSERT INTO repuestos (nombre, descripcion, precio, stock) VALUES
('Aceite motor 10W-40', 'Aceite semi-sintético para motor', 25.99, 50),
('Filtro de aire', 'Filtro de aire original', 15.50, 30),
('Pastillas de freno', 'Juego de pastillas de freno delanteras', 45.75, 20);

INSERT INTO turnos (cliente_id, vehiculo_id, fecha, hora_inicio, hora_fin, servicio, descripcion, estado) VALUES
(1, 1, CURDATE(), '09:00:00', '10:30:00', 'Cambio de aceite', 'Cambio de aceite y filtro', 'completado'),
(2, 2, CURDATE(), '11:00:00', '12:00:00', 'Revisión frenos', 'Revisión y ajuste de frenos', 'programado');
