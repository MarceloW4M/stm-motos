<?php
// Script de mantenimiento: listar y crear usuarios en la tabla `usuarios`
// Uso:
//   php manage_usuarios.php list
//   php manage_usuarios.php create <username> <email>

require __DIR__ . '/../includes/config.php';

function die_msg($msg, $code = 1) {
    fwrite(STDERR, $msg . PHP_EOL);
    exit($code);
}

try {
    $db = getDBConnection();
} catch (Exception $e) {
    die_msg('No se pudo conectar a la BD: ' . $e->getMessage());
}

$action = $argv[1] ?? 'list';
if ($action === 'list') {
    try {
        $stmt = $db->query('SELECT * FROM usuarios LIMIT 200');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
        exit(0);
    } catch (Exception $e) {
        die_msg('Error al leer `usuarios`: ' . $e->getMessage());
    }
}

if ($action === 'create') {
    $username = $argv[2] ?? 'admin';
    $email = $argv[3] ?? 'admin@example.com';
    // Generar contraseña segura
    try {
        $plainPass = bin2hex(random_bytes(8)); // 16 hex chars ~= 8 bytes
    } catch (Exception $e) {
        $plainPass = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 12);
    }
    $hashedPass = password_hash($plainPass, PASSWORD_BCRYPT);

    // Inspeccionar estructura de la tabla para rellenar columnas obligatorias
    try {
        $cols = $db->query('DESCRIBE usuarios')->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        die_msg('No se pudo describir la tabla `usuarios`: ' . $e->getMessage());
    }

    $insertCols = [];
    $insertPlaceholders = [];
    $insertValues = [];

    foreach ($cols as $col) {
        $field = $col['Field'];
        $type = $col['Type'];
        $nullAllowed = ($col['Null'] === 'YES');
        $default = $col['Default'];
        $extra = $col['Extra'];

        if (stripos($extra, 'auto_increment') !== false) {
            continue;
        }

        // Si tiene valor por defecto o permite NULL, podemos omitirlo salvo que sea una columna que queremos establecer
        $needValue = (!$nullAllowed && $default === null);

        // Heurísticas para asignar valores razonables
        if (stripos($field, 'pass') !== false || stripos($field, 'pwd') !== false) {
            $val = $hashedPass;
            $needValue = true;
        } elseif (stripos($field, 'email') !== false) {
            $val = $email;
            $needValue = true;
        } elseif (stripos($field, 'user') !== false || stripos($field, 'nombre') !== false) {
            $val = $username;
            $needValue = true;
        } elseif (stripos($type, 'enum') !== false) {
            // tomar la primera opción del enum
            if (preg_match("/enum\\((.*)\\)/i", $type, $m)) {
                $opts = array_map(function($s){ return trim($s, " '\""); }, explode(',', $m[1]));
                $val = $opts[0] ?? '';
                $needValue = true;
            }
        } elseif (stripos($field, 'fecha') !== false || stripos($field, 'date') !== false || stripos($field, 'created') !== false || stripos($field, 'time') !== false) {
            $val = date('Y-m-d H:i:s');
            $needValue = true;
        } elseif (preg_match('/int|tinyint|smallint|bigint|decimal|float/', $type)) {
            // Asumir valor por defecto 0 si es requerido
            $val = 0;
            $needValue = $needValue || (stripos($field, 'role') !== false);
        } else {
            // Texto u otros: asignar cadena vacía si es obligatorio
            $val = '';
        }

        if ($needValue) {
            $insertCols[] = $field;
            $ph = ':' . $field;
            $insertPlaceholders[] = $ph;
            $insertValues[$ph] = $val;
        }
    }

    if (empty($insertCols)) {
        die_msg('No se identificaron columnas obligatorias para insertar en `usuarios`.');
    }

    $sql = 'INSERT INTO usuarios (' . implode(', ', $insertCols) . ') VALUES (' . implode(', ', $insertPlaceholders) . ')';

    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($insertValues);
        $lastId = $db->lastInsertId();
        echo "Usuario creado con ID: $lastId" . PHP_EOL;
        echo "Credenciales (cámbialas al iniciar sesión):" . PHP_EOL;
        echo "  usuario: $username" . PHP_EOL;
        echo "  email: $email" . PHP_EOL;
        echo "  password: $plainPass" . PHP_EOL;
        exit(0);
    } catch (Exception $e) {
        die_msg('Error al insertar usuario: ' . $e->getMessage());
    }
}

die_msg('Acción desconocida. Uso: php manage_usuarios.php list|create <username> <email>');
