# 📋 Verificación Completa del Código - STM Taller de Motos

**Fecha**: 28 de enero de 2026  
**Estado General**: ✅ **CÓDIGO EN BUEN ESTADO**

---

## 📊 Resumen Ejecutivo

El análisis completo del código reveló:
- **✅ Sin errores de sintaxis o compilación**
- **✅ Seguridad SQL adecuada (queries parametrizadas)**
- **✅ XSS protection (htmlspecialchars en outputs)**
- **✅ Manejo de excepciones presente**
- **⚠️ Algunas recomendaciones de mejora detectadas**

---

## ✅ Fortalezas del Código

### 1. **Seguridad de Base de Datos (Muy Buena)**
```
✅ Todas las queries usan PDO prepared statements
✅ Uso correcto de bindParam() y bindValue()
✅ Pagination con PDO::PARAM_INT bindings correctos
✅ No hay SQL injection aparente
```

**Ejemplo correcto** en [clientes.php](src/clientes.php#L77-L78):
```php
$stmt->bindValue(':limit', $registros_por_pagina, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
```

---

### 2. **Protección contra XSS (Buena)**
```
✅ htmlspecialchars() usado en outputs HTML
✅ Escaping de datos en tablas y formularios
✅ Datos de usuarios escapados antes de mostrar
```

**Uso consistente** en múltiples archivos:
- [clientes.php](src/clientes.php#L127): Datos en tabla escapados
- [clientes_m.php](src/clientes_m.php#L207-L225): Valores de formularios protegidos
- [dashboard.php](src/dashboard.php#L139-L143): Información del turno escapada

---

### 3. **Autenticación (Implementada Correctamente)**
```
✅ Session management en auth.php
✅ requireAuth() usado en todas las páginas protegidas
✅ Redirección a login.php en acceso no autorizado
✅ Logout limpia sesiones adecuadamente
```

[Archivo: auth.php](src/includes/auth.php):
```php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function requireAuth() {
    if (!isAuthenticated()) {
        header("Location: ../login.php");
        exit();
    }
}
```

---

### 4. **Manejo de Archivos (Bueno)**
```
✅ Upload de fotos con validación de tipos MIME
✅ Validación de tamaño máximo (2MB)
✅ Nombres de archivos únicos (evita sobrescritura)
✅ Eliminación de archivos antiguos al actualizar
```

**En clientes_m.php líneas 32-67**:
```php
$allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$max_size = 2 * 1024 * 1024; // 2MB
```

---

### 5. **Manejo de Excepciones**
```
✅ Try-catch blocks en operaciones críticas
✅ Mensajes de error informativos
✅ Redirecciones en caso de error
```

Ejemplo en [vehiculos.php](src/vehiculos.php#L42-L58):
```php
try {
    $query = "INSERT INTO vehiculos...";
    $stmt = $db->prepare($query);
    // ... bindParam calls ...
    $stmt->execute();
    $mensaje = "✅ Vehículo agregado exitosamente";
} catch (PDOException $e) {
    $mensaje = "❌ Error al agregar vehículo: " . $e->getMessage();
}
```

---

### 6. **Paginación (Implementada Correctamente)**
```
✅ Límite de 50 registros por página
✅ Cálculo correcto de OFFSET
✅ Validación de número de página
✅ Cálculo de total de páginas
```

**En clientes.php líneas 45-81**:
```php
$pagina_actual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($pagina_actual - 1) * $registros_por_pagina;
```

---

### 7. **Búsqueda/Filtrado (Seguro)**
```
✅ Búsqueda con LIKE segura (parametrizada)
✅ Wildcards en parámetros, no en SQL
✅ Trim de entradas para espacios
```

**En clientes.php líneas 52-57**:
```php
$busqueda = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';
if (!empty($busqueda)) {
    $where = "WHERE nombre LIKE :busqueda OR ...";
    $params[':busqueda'] = "%$busqueda%";
}
```

---

## ⚠️ Áreas de Mejora Recomendadas

### 1. **Validación de Entrada (IMPORTANTE)**
**Nivel de Gravedad**: 🟡 MEDIO

**Problema**: No hay validación de entrada antes de usar los datos.

**Archivos Afectados**:
- [clientes.php](src/clientes.php#L13-L18)
- [clientes_m.php](src/clientes_m.php#L17-L26)
- [vehiculos.php](src/vehiculos.php#L16-L21)
- [repuestos.php](src/repuestos.php#L16-L19)

**Ejemplo Actual**:
```php
$nombre = $_POST['nombre'];
$email = $_POST['email'];
$cuit = $_POST['cuit'];
// Sin validación antes de usar
```

**Recomendación**:
```php
$nombre = isset($_POST['nombre']) ? trim($_POST['nombre']) : '';
if (empty($nombre)) {
    throw new Exception('El nombre es requerido');
}

$email = isset($_POST['email']) ? trim($_POST['email']) : '';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    throw new Exception('Email inválido');
}

$cuit = isset($_POST['cuit']) ? trim($_POST['cuit']) : '';
if (!preg_match('/^\d{2}-\d{8}-\d$/', $cuit)) {
    throw new Exception('CUIT inválido');
}
```

---

### 2. **Validación de IDs desde URLs (IMPORTANTE)**
**Nivel de Gravedad**: 🟡 MEDIO

**Problema**: No se valida que los IDs desde GET sean números válidos.

**Archivos Afectados**:
- [clientes.php](src/clientes.php#L34): `$_GET['eliminar']`
- [clientes_m.php](src/clientes_m.php#L13): `$_GET['id']`
- [editar_repuesto.php](src/editar_repuesto.php#L10): `$_GET['id']`
- [editar_vehiculo.php](src/editar_vehiculo.php#L10): `$_GET['id']`
- [editar_turno.php](src/editar_turno.php#L10): `$_GET['id']`

**Ejemplo Actual**:
```php
$id = $_GET['eliminar'];
$query = "DELETE FROM clientes WHERE id = :id";
```

**Recomendación**:
```php
if (!isset($_GET['eliminar']) || !is_numeric($_GET['eliminar'])) {
    header("Location: clientes.php?error=id_invalido");
    exit();
}
$id = (int)$_GET['eliminar'];
```

---

### 3. **Rutas de Redireccionamiento (IMPORTANTE)**
**Nivel de Gravedad**: 🔴 CRÍTICA

**Problema**: URLs en redireccionamientos vienen desde GET sin validación.

**Ejemplo en generar_informe.php**:
```php
header("Location: generar_informe.php?tipo=$tipo_informe&fecha_inicio=$fecha_inicio&fecha_fin=$fecha_fin");
```

**Recomendación**:
```php
// Validar que los parámetros sean esperados
$tipos_validos = ['turnos', 'inventario', 'financiero'];
if (!in_array($tipo_informe, $tipos_validos)) {
    $tipo_informe = 'turnos';
}

// O usar redirects simples sin URL user-input:
header("Location: generar_informe.php");
```

---

### 4. **Exposición de Contraseñas (CRÍTICA)**
**Nivel de Gravedad**: 🔴 CRÍTICA

**Problema**: Las credenciales de base de datos están hardcodeadas en [config.php](src/includes/config.php).

```php
define('DB_HOST', '10.50.0.30');
define('DB_PORT', '3406');
define('DB_USER', 'root');
define('DB_PASS', 'w1f14m3d1a');  // ⚠️ EXPOSICIÓN DE CREDENCIALES
```

**Recomendación Urgente**:
1. Mover credenciales a variables de entorno (`.env` file)
2. Nunca commitear `config.php` a Git
3. Usar `env()` helper o similar:
```php
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
```

---

### 5. **Falta de CSRF Protection (IMPORTANTE)**
**Nivel de Gravedad**: 🟡 MEDIO

**Problema**: No hay tokens CSRF en formularios POST.

**Archivos Afectados**: Todos los formularios en:
- clientes.php
- clientes_m.php
- vehiculos.php
- repuestos.php
- turnos.php

**Solución Recomendada**:
```php
// En auth.php o incluir en header
function generateCsrfToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && 
           hash_equals($_SESSION['csrf_token'], $token);
}

// En formularios
<form method="POST">
    <input type="hidden" name="csrf_token" 
           value="<?php echo generateCsrfToken(); ?>">
</form>

// Al procesar
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        die('CSRF token inválido');
    }
}
```

---

### 6. **Sanitización en URL Parameters (IMPORTANTE)**
**Nivel de Gravedad**: 🟡 MEDIO

**Problema**: En algunos casos, parámetros GET no tienen cast a int.

**Ejemplo en [generar_informe_inventario.php](src/generar_informe_inventario.php#L11)**:
```php
$tipo = $_GET['tipo'] ?? 'completo';
// Sin validación de que sea un tipo válido
```

**Recomendación**:
```php
$tipos_validos = ['completo', 'bajo_stock', 'valor_total'];
$tipo = $_GET['tipo'] ?? 'completo';
if (!in_array($tipo, $tipos_validos)) {
    $tipo = 'completo';
}
```

---

### 7. **Error Handling en PDF (BUENO PERO MEJORABLE)**
**Nivel de Gravedad**: 🟡 BAJO

**Archivos**: [generar_pdf.php](src/generar_pdf.php), [generar2_pdf.php](src/generar2_pdf.php)

**Actual**: Buen manejo con ob_start() y ob_end_clean().

**Mejora Sugerida**: Agregar logging de errores:
```php
if (!file_exists('tcpdf/tcpdf.php')) {
    error_log('TCPDF library not found at ' . __FILE__);
    ob_end_clean();
    header('Location: error.php?msg=library_error');
    exit();
}
```

---

### 8. **Falta de Rate Limiting en Login**
**Nivel de Gravedad**: 🟡 BAJO

**Archivo**: [login.php](src/login.php)

**Problema**: Sin protección contra ataques de fuerza bruta.

**Recomendación**:
```php
// En auth.php
function incrementFailedLoginAttempts($username) {
    $key = "login_attempts_" . $username;
    $_SESSION[$key] = ($_SESSION[$key] ?? 0) + 1;
    $_SESSION[$key . '_time'] = time();
}

function isLoginBlocked($username) {
    $key = "login_attempts_" . $username;
    if (!isset($_SESSION[$key])) return false;
    
    if (time() - ($_SESSION[$key . '_time'] ?? 0) > 900) { // 15 min
        unset($_SESSION[$key]);
        return false;
    }
    
    return $_SESSION[$key] >= 5; // Máximo 5 intentos
}
```

---

## 📊 Matriz de Severidad

| Severidad | Cantidad | Ejemplos |
|-----------|----------|----------|
| 🔴 CRÍTICA | 1 | Credenciales en config.php |
| 🟡 MEDIA | 5 | Validación entrada, CSRF, ID validation |
| 🟢 BAJA | 3 | Rate limiting, logging, sanitización URLs |
| ✅ OK | 7+ | Auth, SQL injection, XSS, Excepciones |

---

## 🔍 Resumen por Archivo

### Core Files
| Archivo | Estado | Notas |
|---------|--------|-------|
| [includes/auth.php](src/includes/auth.php) | ✅ | Bien implementado |
| [includes/database.php](src/includes/database.php) | ✅ | PDO correcto |
| [includes/config.php](src/includes/config.php) | 🔴 | **CRÍTICA: Credenciales hardcodeadas** |
| [includes/header.php](src/includes/header.php) | ✅ | Buena estructura |
| [includes/footer.php](src/includes/footer.php) | ✅ | Limpio |

### List/CRUD Pages
| Archivo | Estado | Notas |
|---------|--------|-------|
| [clientes.php](src/clientes.php) | ✅ | SQL seguro, pero falta validación entrada |
| [clientes_m.php](src/clientes_m.php) | ✅ | Upload con validación, bueno |
| [vehiculos.php](src/vehiculos.php) | ✅ | Estructura correcta, excepciones bien usadas |
| [repuestos.php](src/repuestos.php) | ✅ | Stock update seguro |
| [turnos.php](src/turnos.php) | ✅ | Try-catch implementado |

### Edit Pages
| Archivo | Estado | Notas |
|---------|--------|-------|
| [editar_repuesto.php](src/editar_repuesto.php) | ✅ | Validación de existencia de ID |
| [editar_vehiculo.php](src/editar_vehiculo.php) | ✅ | Buena estructura |
| [editar_turno.php](src/editar_turno.php) | ✅ | Queries parametrizadas |

### Reporting
| Archivo | Estado | Notas |
|---------|--------|-------|
| [dashboard.php](src/dashboard.php) | ✅ | XSS protection, bien |
| [informes.php](src/informes.php) | 🟡 | Sin validación de fechas |
| [generar_informe.php](src/generar_informe.php) | 🟡 | URL params sin validar |
| [generar_informe_inventario.php](src/generar_informe_inventario.php) | 🟡 | Tipo sin validación |

### PDF Generation
| Archivo | Estado | Notas |
|---------|--------|-------|
| [generar_pdf.php](src/generar_pdf.php) | ✅ | ob_start/clean bien usado |
| [generar2_pdf.php](src/generar2_pdf.php) | ✅ | Mismo patrón, ok |

### Auth
| Archivo | Estado | Notas |
|---------|--------|-------|
| [login.php](src/login.php) | ✅ | Hash bcrypt correcto, sin rate limit |
| [logout.php](src/logout.php) | ✅ | Limpia sesiones |

### Other
| Archivo | Estado | Notas |
|---------|--------|-------|
| [ordenes.php](src/ordenes.php) | ✅ | Estructura OK |
| [detalle_orden.php](src/detalle_orden.php) | ✅ | Lectura segura |
| [ping.php](src/ping.php) | ✅ | Health check simple |
| [index.php](src/index.php) | ✅ | Redirección ok |

---

## 🚀 Plan de Acción Recomendado

### URGENTE (Hacer hoy):
1. ⚠️ **Mover credenciales de DB a variables de entorno**
   - Crear archivo `.env` en raíz
   - Actualizar [config.php](src/includes/config.php)
   - Añadir `.env` a `.gitignore`

2. ⚠️ **Agregar validación de entrada en formularios POST**
   - Crear función de validación genérica
   - Aplicar a todos los formularios

3. ⚠️ **Validar IDs desde URLs**
   - Agregar validación int() en todos los `$_GET['id']`

### IMPORTANTE (Esta semana):
4. 🟡 **Implementar CSRF tokens**
   - Agregar generador/verificador en auth.php
   - Añadir a todos los formularios

5. 🟡 **Sanitizar parámetros de reporte**
   - Validar tipos de informe permitidos
   - Validar rangos de fechas

6. 🟡 **Rate limiting en login**
   - Implementar bloqueo tras 5 intentos

### PREFERIBLE (Próximas 2 semanas):
7. 🟢 **Agregar logging de errores**
   - Crear archivo de log para errores críticos
   - Log de cambios en CRUD

8. 🟢 **Tests unitarios**
   - Crear tests para lógica de validación
   - Tests de seguridad

---

## ✅ Checklist de Verificación

- [x] Sin errores de sintaxis PHP
- [x] SQL injection protection (queries parametrizadas)
- [x] XSS protection (htmlspecialchars)
- [x] Session management implementado
- [x] Paginación correcta
- [x] Try-catch para excepciones
- [x] Validación de tipos MIME en uploads
- [x] Eliminación de archivos obsoletos
- [ ] ⚠️ Credenciales no hardcodeadas (FALLA)
- [ ] Validación de entrada completa
- [ ] ID validation en URLs
- [ ] CSRF protection en formularios
- [ ] Rate limiting en login

---

## 📚 Referencias y Estándares

**Estándares Seguidos**:
- ✅ PDO + Prepared Statements (OWASP)
- ✅ htmlspecialchars() en outputs
- ✅ Password hashing con bcrypt
- ✅ Session-based authentication

**Estándares Pendientes**:
- ⚠️ OWASP Top 10 #4: Insecure Deserialization (no afecta)
- ⚠️ OWASP Top 10 #5: Broken Access Control (mejorable con validación)
- ⚠️ OWASP Top 10 #8: CSRF (no implementado)

---

## 🎯 Conclusión

El código de **STM Taller de Motos** está en **buen estado general**. Las prácticas de seguridad principales están implementadas correctamente (SQL injection, XSS, autenticación).

**Prioridad Máxima**: Mover credenciales de base de datos a variables de entorno.

**Próximas Mejoras**: Validación de entrada más robusta y protección CSRF.

**Score de Seguridad**: 7/10 ⭐ (muy bueno, con mejoras claras disponibles)

---

*Análisis completado: 28 de enero de 2026*  
*Herramienta: GitHub Copilot Code Review v1*
