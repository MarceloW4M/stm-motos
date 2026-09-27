<?php

function tableExists(PDO $db, string $tableName): bool
{
    $stmt = $db->prepare("SHOW TABLES LIKE :table");
    $stmt->bindParam(':table', $tableName, PDO::PARAM_STR);
    $stmt->execute();
    return (bool) $stmt->fetch(PDO::FETCH_COLUMN);
}

function ensureArgentinaUbicacionesTables(PDO $db): void
{
    if (!tableExists($db, 'argentina_provincias')) {
        $db->exec("CREATE TABLE argentina_provincias (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(100) NOT NULL UNIQUE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    if (!tableExists($db, 'argentina_ciudades')) {
        $db->exec("CREATE TABLE argentina_ciudades (
            id INT AUTO_INCREMENT PRIMARY KEY,
            provincia_id INT NOT NULL,
            nombre VARCHAR(100) NOT NULL,
            codigo_postal VARCHAR(20) DEFAULT NULL,
            UNIQUE KEY uk_ciudad_provincia (provincia_id, nombre),
            FOREIGN KEY (provincia_id) REFERENCES argentina_provincias(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    seedArgentinaUbicaciones($db);
}

function seedArgentinaUbicaciones(PDO $db): void
{
    $provincias = [
        ['id' => 1, 'nombre' => 'Buenos Aires'],
        ['id' => 2, 'nombre' => 'Catamarca'],
        ['id' => 3, 'nombre' => 'Chaco'],
        ['id' => 4, 'nombre' => 'Chubut'],
        ['id' => 5, 'nombre' => 'Córdoba'],
        ['id' => 6, 'nombre' => 'Corrientes'],
        ['id' => 7, 'nombre' => 'Entre Ríos'],
        ['id' => 8, 'nombre' => 'Formosa'],
        ['id' => 9, 'nombre' => 'Jujuy'],
        ['id' => 10, 'nombre' => 'La Pampa'],
        ['id' => 11, 'nombre' => 'La Rioja'],
        ['id' => 12, 'nombre' => 'Mendoza'],
        ['id' => 13, 'nombre' => 'Misiones'],
        ['id' => 14, 'nombre' => 'Neuquén'],
        ['id' => 15, 'nombre' => 'Río Negro'],
        ['id' => 16, 'nombre' => 'Salta'],
        ['id' => 17, 'nombre' => 'San Juan'],
        ['id' => 18, 'nombre' => 'San Luis'],
        ['id' => 19, 'nombre' => 'Santa Cruz'],
        ['id' => 20, 'nombre' => 'Santa Fe'],
        ['id' => 21, 'nombre' => 'Santiago del Estero'],
        ['id' => 22, 'nombre' => 'Tierra del Fuego'],
        ['id' => 23, 'nombre' => 'Tucumán'],
        ['id' => 24, 'nombre' => 'Ciudad Autónoma de Buenos Aires']
    ];

    $cities = [
        ['provincia_id' => 24, 'nombre' => 'Ciudad Autónoma de Buenos Aires', 'codigo_postal' => 'C1000'],
        ['provincia_id' => 1, 'nombre' => 'La Plata', 'codigo_postal' => 'B1900'],
        ['provincia_id' => 1, 'nombre' => 'Mar del Plata', 'codigo_postal' => 'B7600'],
        ['provincia_id' => 1, 'nombre' => 'Bahía Blanca', 'codigo_postal' => 'B8000'],
        ['provincia_id' => 1, 'nombre' => 'Tandil', 'codigo_postal' => 'B7000'],
        ['provincia_id' => 1, 'nombre' => 'San Nicolás', 'codigo_postal' => 'B2900'],
        ['provincia_id' => 1, 'nombre' => 'Olavarría', 'codigo_postal' => 'B7400'],
        ['provincia_id' => 1, 'nombre' => 'Junín', 'codigo_postal' => 'B6000'],
        ['provincia_id' => 1, 'nombre' => 'Pergamino', 'codigo_postal' => 'B2700'],
        ['provincia_id' => 1, 'nombre' => 'Morón', 'codigo_postal' => 'B1708'],
        ['provincia_id' => 2, 'nombre' => 'San Fernando del Valle de Catamarca', 'codigo_postal' => 'K4700'],
        ['provincia_id' => 2, 'nombre' => 'Andalgalá', 'codigo_postal' => 'K4730'],
        ['provincia_id' => 2, 'nombre' => 'Belén', 'codigo_postal' => 'K4750'],
        ['provincia_id' => 2, 'nombre' => 'Santa María', 'codigo_postal' => 'K5360'],
        ['provincia_id' => 3, 'nombre' => 'Resistencia', 'codigo_postal' => 'H3500'],
        ['provincia_id' => 3, 'nombre' => 'Sáenz Peña', 'codigo_postal' => 'H3700'],
        ['provincia_id' => 3, 'nombre' => 'Villa Ángela', 'codigo_postal' => 'H3540'],
        ['provincia_id' => 3, 'nombre' => 'Charata', 'codigo_postal' => 'H3730'],
        ['provincia_id' => 4, 'nombre' => 'Rawson', 'codigo_postal' => 'U9200'],
        ['provincia_id' => 4, 'nombre' => 'Comodoro Rivadavia', 'codigo_postal' => 'U9000'],
        ['provincia_id' => 4, 'nombre' => 'Puerto Madryn', 'codigo_postal' => 'U9120'],
        ['provincia_id' => 4, 'nombre' => 'Trelew', 'codigo_postal' => 'U9100'],
        ['provincia_id' => 4, 'nombre' => 'Esquel', 'codigo_postal' => 'U9201'],
        ['provincia_id' => 4, 'nombre' => 'Puerto Pirámides', 'codigo_postal' => 'U9121'],
        ['provincia_id' => 4, 'nombre' => 'Gaiman', 'codigo_postal' => 'U9128'],
        ['provincia_id' => 4, 'nombre' => 'Dolavon', 'codigo_postal' => 'U9122'],
        ['provincia_id' => 4, 'nombre' => 'Sarmiento', 'codigo_postal' => 'U9001'],
        ['provincia_id' => 4, 'nombre' => 'Trevelin', 'codigo_postal' => 'U9203'],
        ['provincia_id' => 4, 'nombre' => 'Lago Puelo', 'codigo_postal' => 'U9125'],
        ['provincia_id' => 4, 'nombre' => 'Esquel', 'codigo_postal' => 'U9201'],
        ['provincia_id' => 5, 'nombre' => 'Córdoba', 'codigo_postal' => 'X5000'],
        ['provincia_id' => 5, 'nombre' => 'Villa Carlos Paz', 'codigo_postal' => 'X5009'],
        ['provincia_id' => 5, 'nombre' => 'Río Cuarto', 'codigo_postal' => 'X5800'],
        ['provincia_id' => 5, 'nombre' => 'Villa María', 'codigo_postal' => 'X5900'],
        ['provincia_id' => 5, 'nombre' => 'Jesús María', 'codigo_postal' => 'X5889'],
        ['provincia_id' => 5, 'nombre' => 'Villa Allende', 'codigo_postal' => 'X5109'],
        ['provincia_id' => 6, 'nombre' => 'Corrientes', 'codigo_postal' => 'W3400'],
        ['provincia_id' => 6, 'nombre' => 'Goya', 'codigo_postal' => 'W3412'],
        ['provincia_id' => 6, 'nombre' => 'Paso de los Libres', 'codigo_postal' => 'W3403'],
        ['provincia_id' => 6, 'nombre' => 'Mercedes', 'codigo_postal' => 'W3405'],
        ['provincia_id' => 6, 'nombre' => 'Ituzaingó', 'codigo_postal' => 'W3440'],
        ['provincia_id' => 7, 'nombre' => 'Paraná', 'codigo_postal' => 'E3100'],
        ['provincia_id' => 7, 'nombre' => 'Concordia', 'codigo_postal' => 'E3200'],
        ['provincia_id' => 7, 'nombre' => 'Gualeguaychú', 'codigo_postal' => 'E2820'],
        ['provincia_id' => 7, 'nombre' => 'Concepción del Uruguay', 'codigo_postal' => 'E3260'],
        ['provincia_id' => 7, 'nombre' => 'Villaguay', 'codigo_postal' => 'E3280'],
        ['provincia_id' => 8, 'nombre' => 'Formosa', 'codigo_postal' => 'P3600'],
        ['provincia_id' => 8, 'nombre' => 'Clorinda', 'codigo_postal' => 'P3610'],
        ['provincia_id' => 8, 'nombre' => 'Pirané', 'codigo_postal' => 'P3636'],
        ['provincia_id' => 8, 'nombre' => 'Ibarreta', 'codigo_postal' => 'P3628'],
        ['provincia_id' => 9, 'nombre' => 'San Salvador de Jujuy', 'codigo_postal' => 'Y4600'],
        ['provincia_id' => 9, 'nombre' => 'Perico', 'codigo_postal' => 'Y4600'],
        ['provincia_id' => 9, 'nombre' => 'Palpalá', 'codigo_postal' => 'Y4611'],
        ['provincia_id' => 9, 'nombre' => 'Humahuaca', 'codigo_postal' => 'Y4640'],
        ['provincia_id' => 9, 'nombre' => 'Tilcara', 'codigo_postal' => 'Y4654'],
        ['provincia_id' => 10, 'nombre' => 'Santa Rosa', 'codigo_postal' => 'L6300'],
        ['provincia_id' => 10, 'nombre' => 'General Pico', 'codigo_postal' => 'L6300'],
        ['provincia_id' => 10, 'nombre' => 'General Acha', 'codigo_postal' => 'L6307'],
        ['provincia_id' => 10, 'nombre' => 'Victorica', 'codigo_postal' => 'L6323'],
        ['provincia_id' => 11, 'nombre' => 'La Rioja', 'codigo_postal' => 'F5300'],
        ['provincia_id' => 11, 'nombre' => 'Chilecito', 'codigo_postal' => 'F5300'],
        ['provincia_id' => 11, 'nombre' => 'Chepes', 'codigo_postal' => 'F5320'],
        ['provincia_id' => 11, 'nombre' => 'Vinchina', 'codigo_postal' => 'F5360'],
        ['provincia_id' => 12, 'nombre' => 'Mendoza', 'codigo_postal' => 'M5500'],
        ['provincia_id' => 12, 'nombre' => 'San Rafael', 'codigo_postal' => 'M5600'],
        ['provincia_id' => 12, 'nombre' => 'Godoy Cruz', 'codigo_postal' => 'M5501'],
        ['provincia_id' => 12, 'nombre' => 'Luján de Cuyo', 'codigo_postal' => 'M5507'],
        ['provincia_id' => 12, 'nombre' => 'San Martín', 'codigo_postal' => 'M5400'],
        ['provincia_id' => 12, 'nombre' => 'Tunuyán', 'codigo_postal' => 'M5560'],
        ['provincia_id' => 13, 'nombre' => 'Posadas', 'codigo_postal' => 'N3300'],
        ['provincia_id' => 13, 'nombre' => 'Oberá', 'codigo_postal' => 'N3360'],
        ['provincia_id' => 13, 'nombre' => 'Eldorado', 'codigo_postal' => 'N3380'],
        ['provincia_id' => 13, 'nombre' => 'Puerto Iguazú', 'codigo_postal' => 'N3370'],
        ['provincia_id' => 13, 'nombre' => 'Apóstoles', 'codigo_postal' => 'N3315'],
        ['provincia_id' => 14, 'nombre' => 'Neuquén', 'codigo_postal' => 'Q8300'],
        ['provincia_id' => 14, 'nombre' => 'San Martín de los Andes', 'codigo_postal' => 'Q8400'],
        ['provincia_id' => 14, 'nombre' => 'Villa La Angostura', 'codigo_postal' => 'Q8419'],
        ['provincia_id' => 14, 'nombre' => 'Cipolletti', 'codigo_postal' => 'Q8316'],
        ['provincia_id' => 14, 'nombre' => 'Plaza Huincul', 'codigo_postal' => 'Q8307'],
        ['provincia_id' => 15, 'nombre' => 'Viedma', 'codigo_postal' => 'R8500'],
        ['provincia_id' => 15, 'nombre' => 'San Carlos de Bariloche', 'codigo_postal' => 'R8400'],
        ['provincia_id' => 15, 'nombre' => 'Cipolletti', 'codigo_postal' => 'R8320'],
        ['provincia_id' => 15, 'nombre' => 'General Roca', 'codigo_postal' => 'R8332'],
        ['provincia_id' => 15, 'nombre' => 'Allen', 'codigo_postal' => 'R8325'],
        ['provincia_id' => 16, 'nombre' => 'Salta', 'codigo_postal' => 'A4400'],
        ['provincia_id' => 16, 'nombre' => 'Tartagal', 'codigo_postal' => 'A4560'],
        ['provincia_id' => 16, 'nombre' => 'Orán', 'codigo_postal' => 'A4600'],
        ['provincia_id' => 16, 'nombre' => 'Metán', 'codigo_postal' => 'A4440'],
        ['provincia_id' => 16, 'nombre' => 'Cafayate', 'codigo_postal' => 'A4427'],
        ['provincia_id' => 17, 'nombre' => 'San Juan', 'codigo_postal' => 'J5400'],
        ['provincia_id' => 17, 'nombre' => 'Caucete', 'codigo_postal' => 'J5440'],
        ['provincia_id' => 17, 'nombre' => 'Chimbas', 'codigo_postal' => 'J5400'],
        ['provincia_id' => 17, 'nombre' => 'Pocito', 'codigo_postal' => 'J5400'],
        ['provincia_id' => 17, 'nombre' => '9 de Julio', 'codigo_postal' => 'J5400'],
        ['provincia_id' => 18, 'nombre' => 'San Luis', 'codigo_postal' => 'D5700'],
        ['provincia_id' => 18, 'nombre' => 'Villa Mercedes', 'codigo_postal' => 'D5700'],
        ['provincia_id' => 18, 'nombre' => 'Merlo', 'codigo_postal' => 'D5881'],
        ['provincia_id' => 18, 'nombre' => 'La Punta', 'codigo_postal' => 'D5700'],
        ['provincia_id' => 19, 'nombre' => 'Río Gallegos', 'codigo_postal' => 'Z9400'],
        ['provincia_id' => 19, 'nombre' => 'El Calafate', 'codigo_postal' => 'Z9050'],
        ['provincia_id' => 19, 'nombre' => 'Caleta Olivia', 'codigo_postal' => 'Z9405'],
        ['provincia_id' => 19, 'nombre' => 'Puerto Deseado', 'codigo_postal' => 'Z9450'],
        ['provincia_id' => 19, 'nombre' => 'San Julián', 'codigo_postal' => 'Z9407'],
        ['provincia_id' => 20, 'nombre' => 'Santa Fe', 'codigo_postal' => 'S3000'],
        ['provincia_id' => 20, 'nombre' => 'Rosario', 'codigo_postal' => 'S2000'],
        ['provincia_id' => 20, 'nombre' => 'Rafaela', 'codigo_postal' => 'S2300'],
        ['provincia_id' => 20, 'nombre' => 'Venado Tuerto', 'codigo_postal' => 'S2600'],
        ['provincia_id' => 20, 'nombre' => 'Reconquista', 'codigo_postal' => 'S3560'],
        ['provincia_id' => 21, 'nombre' => 'Santiago del Estero', 'codigo_postal' => 'G4300'],
        ['provincia_id' => 21, 'nombre' => 'La Banda', 'codigo_postal' => 'G4300'],
        ['provincia_id' => 21, 'nombre' => 'Quimilí', 'codigo_postal' => 'G4308'],
        ['provincia_id' => 21, 'nombre' => 'Añatuya', 'codigo_postal' => 'G4340'],
        ['provincia_id' => 22, 'nombre' => 'Ushuaia', 'codigo_postal' => 'V9410'],
        ['provincia_id' => 22, 'nombre' => 'Río Grande', 'codigo_postal' => 'V9420'],
        ['provincia_id' => 22, 'nombre' => 'Tolhuin', 'codigo_postal' => 'V9417'],
        ['provincia_id' => 23, 'nombre' => 'San Miguel de Tucumán', 'codigo_postal' => 'T4000'],
        ['provincia_id' => 23, 'nombre' => 'Tafí Viejo', 'codigo_postal' => 'T4100'],
        ['provincia_id' => 23, 'nombre' => 'Concepción', 'codigo_postal' => 'T4105'],
        ['provincia_id' => 23, 'nombre' => 'Monteros', 'codigo_postal' => 'T4107']
    ];

    $insertProvincia = $db->prepare('INSERT IGNORE INTO argentina_provincias (id, nombre) VALUES (:id, :nombre)');
    foreach ($provincias as $provincia) {
        $insertProvincia->execute($provincia);
    }

    $insertCiudad = $db->prepare('INSERT IGNORE INTO argentina_ciudades (provincia_id, nombre, codigo_postal) VALUES (:provincia_id, :nombre, :codigo_postal)');
    foreach ($cities as $ciudad) {
        $insertCiudad->execute($ciudad);
    }
}

function getArgentinaProvincias(PDO $db): array
{
    $stmt = $db->query('SELECT id, nombre FROM argentina_provincias ORDER BY nombre');
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getArgentinaCiudadesPorProvincia(PDO $db): array
{
    $stmt = $db->query('SELECT provincia_id, nombre, codigo_postal FROM argentina_ciudades ORDER BY nombre');
    $ciudades = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $ciudades[$row['provincia_id']][] = [
            'nombre' => $row['nombre'],
            'codigo_postal' => $row['codigo_postal']
        ];
    }
    return $ciudades;
}
