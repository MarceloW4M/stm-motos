# STM - Taller de Motos

Sistema de gestión integral para talleres de motocicletas. Permite administrar clientes, vehículos, repuestos, órdenes de reparación, turnos y generar informes. Desarrollado con PHP 8.2, MySQL y Docker para un entorno de desarrollo y producción simplificado.

## 🚀 Características

- **Gestión de Clientes**: Registro y administración de información de clientes con datos de contacto y CUIT
- **Control de Vehículos**: Inventario de motocicletas con marca, modelo, matrícula, VIN y asociación a clientes
- **Inventario de Repuestos**: Control de stock, precios y gestión de partes para reparaciones
- **Órdenes de Reparación**: Creación y seguimiento de órdenes de trabajo con tareas y repuestos utilizados
- **Sistema de Turnos**: Programación y gestión de citas para servicios
- **Informes y Reportes**: Generación de informes de inventario y estadísticas
- **Generación de PDFs**: Facturas y documentos en formato PDF usando TCPDF
- **Autenticación Segura**: Sistema de login con contraseñas hasheadas (bcrypt)
- **Interfaz Responsiva**: Diseño adaptativo para diferentes dispositivos

## 🛠️ Tecnologías Utilizadas

- **Backend**: PHP 8.2 con PDO para acceso a base de datos
- **Base de Datos**: MySQL con esquema `stm_taller`
- **Servidor Web**: Nginx (imagen Alpine)
- **Contenedorización**: Docker & Docker Compose
- **Generación de PDFs**: Biblioteca TCPDF
- **Estilos**: CSS personalizado (sin frameworks externos)

## 📋 Requisitos Previos

- Docker y Docker Compose instalados
- Puerto 8080 disponible (para la aplicación)
- Puerto 3306 disponible (para MySQL, si no se usa el contenedor)

## 🚀 Instalación y Configuración

### 1. Clonar o Descargar el Proyecto

```bash
git clone <url-del-repositorio>
cd stm-taller-motos
```

### 2. Construir y Ejecutar los Contenedores

```bash
docker-compose up --build
```

Este comando:
- Construirá las imágenes de Nginx, PHP-FPM y MySQL
- Iniciará los servicios
- Ejecutará el script de inicialización de la base de datos (`init_database.sql`)

### 3. Acceder a la Aplicación

- **Aplicación Principal**: [http://localhost:8080](http://localhost:8080)
- **phpMyAdmin** (opcional): [http://localhost:8080/phpmyadmin](http://localhost:8080/phpmyadmin) (si configurado en docker-compose.yml)

### 4. Credenciales de Acceso

- **Usuario**: `admin`
- **Contraseña**: `admin123`

## 📁 Estructura del Proyecto

```
stm-taller-motos/
├── docker/                    # Configuraciones Docker
│   ├── nginx/
│   │   └── default.conf      # Configuración Nginx
│   ├── php/
│   │   └── Dockerfile        # Imagen PHP-FPM personalizada
│   └── prueba/               # Archivos de prueba (legacy)
├── src/                      # Código fuente de la aplicación
│   ├── includes/             # Utilidades compartidas
│   │   ├── auth.php         # Gestión de autenticación y sesiones
│   │   ├── config.php       # Configuración de BD y constantes
│   │   ├── database.php     # Clase de conexión PDO
│   │   ├── header.php       # Plantilla HTML header
│   │   └── footer.php       # Plantilla HTML footer
│   ├── css/                 # Hojas de estilo
│   │   ├── style.css        # Estilos principales
│   │   └── styleess.css     # Estilos adicionales
│   ├── tcpdf/               # Biblioteca TCPDF para PDFs
│   ├── *.php                # Páginas principales (clientes.php, vehiculos.php, etc.)
│   ├── login.php            # Página de autenticación
│   ├── dashboard.php        # Panel principal
│   └── generar_pdf.php      # Generación de facturas PDF
├── docker-compose.yml       # Configuración de servicios
├── init_database.sql        # Script de inicialización de BD
├── reinstall.sh            # Script para reinstalación completa
└── reset_password.sh       # Script para resetear contraseña
```

## 🗄️ Esquema de Base de Datos

La aplicación utiliza MySQL con las siguientes tablas principales:

- `usuarios` - Autenticación de usuarios
- `clientes` - Información de clientes
- `vehiculos` - Registro de motocicletas
- `repuestos` - Inventario de repuestos
- `turnos` - Citas y turnos de servicio
- `ordenes_reparacion` - Órdenes de trabajo
- `tareas_orden` - Tareas asociadas a órdenes
- `repuestos_orden` - Repuestos utilizados en órdenes

## 🔧 Configuración

### Variables de Entorno

La configuración principal se encuentra en `src/includes/config.php`:

```php
define('DB_HOST', '10.50.0.30');
define('DB_NAME', 'stm_taller');
define('DB_USER', 'root');
define('DB_PASS', 'tu_password');
define('SITE_NAME', 'STM - Aventura Motos');
```

### Docker Compose

Los servicios están definidos en `docker-compose.yml`:
- **nginx**: Servidor web en puerto 8080
- **php**: PHP-FPM para procesamiento
- **mysql**: Base de datos MySQL

## 📖 Uso

### Navegación Principal

1. **Dashboard**: Vista general con estadísticas y accesos rápidos
2. **Clientes**: Gestión completa de clientes
3. **Vehículos**: Administración de motocicletas
4. **Repuestos**: Control de inventario
5. **Órdenes**: Creación y seguimiento de reparaciones
6. **Turnos**: Programación de citas
7. **Informes**: Reportes y estadísticas

### Funcionalidades Clave

- **Búsqueda y Filtrado**: En todas las listas principales
- **Paginación**: Navegación eficiente en listas grandes
- **PDF Generation**: Facturas automáticas con TCPDF
- **Responsive Design**: Adaptable a móviles y tablets

## 🔒 Seguridad

- Autenticación basada en sesiones PHP
- Contraseñas hasheadas con bcrypt
- Consultas parametrizadas para prevenir SQL injection
- Validación de entrada en formularios

## 🐛 Solución de Problemas

### Problemas Comunes

1. **Error de conexión a BD**: Verificar configuración en `config.php` y que MySQL esté ejecutándose
2. **Headers already sent**: Asegurar `ob_start()` antes de includes en generación de PDFs
3. **Puerto ocupado**: Cambiar puertos en `docker-compose.yml` si es necesario

### Reinicio Completo

Para una reinstalación completa:

```bash
bash reinstall.sh
```

### Reset de Contraseña

```bash
bash reset_password.sh
```

## 🤝 Contribución

1. Fork el proyecto
2. Crea una rama para tu feature (`git checkout -b feature/nueva-funcionalidad`)
3. Commit tus cambios (`git commit -am 'Agrega nueva funcionalidad'`)
4. Push a la rama (`git push origin feature/nueva-funcionalidad`)
5. Abre un Pull Request

## 📄 Licencia

Este proyecto está bajo la Licencia MIT. Ver el archivo `LICENSE` para más detalles.

## 📞 Soporte

Para soporte técnico o reportes de bugs, por favor crear un issue en el repositorio.

---

**Desarrollado con ❤️ para la gestión eficiente de talleres de motocicletas.**
