# STM - Taller de Motos

Sistema de gestion para taller mecanico de motos, desarrollado en PHP + MySQL.
Permite administrar clientes, vehiculos, servicios, repuestos, turnos, ordenes y reportes.

## Resumen

- Backend: `PHP 8.2` con `PDO`
- Base de datos: `MySQL` (`stm_taller`)
- Web server: `Nginx`
- Contenedores: `Docker Compose`
- PDF: `TCPDF`
- Frontend: HTML + CSS + JS sin framework

## Funcionalidades Principales

- ABM de clientes
- ABM de vehiculos
- ABM de servicios (`servicios.php`)
- ABM de repuestos
- Gestion de turnos con cupo maximo por hora
- Asignacion de mecanico por turno
- Generacion de ordenes de reparacion
- Carga de tareas e insumos por orden
- Dashboard de agenda con vista por horario
- Historico de reparaciones por cliente
- Seccion temporal de repuestos/insumos historicos por cliente
- Generacion de PDF de orden/factura
- Chat flotante en dashboard preparado para integracion con agente `n8n`

## Estructura Relevante

```text
.
├── docker-compose.yml
├── init_database.sql
├── scripts/
│   ├── migrar_clientes_access.php
│   ├── migrar_historico_access.php
│   ├── migrar_historico_insumos_access.php
│   └── migrar_vehiculos_access.php
└── src/
		├── dashboard.php
		├── clientes.php
		├── clientes_m.php
		├── vehiculos.php
		├── repuestos.php
		├── servicios.php
		├── turnos.php
		├── ordenes.php
		├── historico_cliente.php
		├── generar_pdf.php
		├── includes/
		│   ├── config.php
		│   ├── database.php
		│   ├── auth.php
		│   ├── header.php
		│   └── footer.php
		└── css/
```

## Instalacion Rapida

1. Clonar repositorio

```bash
git clone https://github.com/MarceloW4M/BackUp.git
cd BackUp
```

2. Levantar servicios

```bash
docker-compose up --build
```

3. Abrir aplicacion

- `http://localhost:8080`

4. Credenciales por defecto

- Usuario: `admin`
- Password: `admin123`

## Configuracion

Archivo: `src/includes/config.php`

Constantes clave:

- `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`
- `SITE_NAME`
- `OPEN_TIME`, `CLOSE_TIME`
- `N8N_CHAT_WEBHOOK_URL` (si queres activar chatbot)
- `N8N_CHAT_TIMEOUT_MS`

Si `N8N_CHAT_WEBHOOK_URL` esta vacio, el chat de dashboard queda visible pero no envia consultas al VPS.

## Base de Datos (tablas principales)

- `usuarios`
- `clientes`
- `vehiculos`
- `servicios`
- `repuestos`
- `turnos`
- `ordenes_reparacion`
- `tareas_orden`
- `orden_repuestos`
- `historico`
- `historico_insumos`

## Migracion desde Access (.mdb)

Se incluyen scripts para migrar datos historicos.

Ejemplos:

```bash
php scripts/migrar_clientes_access.php --mdb="/ruta/archivo.mdb" --apply
php scripts/migrar_historico_access.php --mdb="/ruta/archivo.mdb" --apply
php scripts/migrar_vehiculos_access.php --mdb="/ruta/archivo.mdb" --apply
php scripts/migrar_historico_insumos_access.php --mdb="/ruta/archivo.mdb" --apply
```

Notas:

- Sin `--apply`, algunos scripts corren en modo simulacion.
- `historico_insumos` se vincula por `orden` contra `historico`.

## Flujo Operativo Recomendado

1. Crear/actualizar cliente.
2. Asociar vehiculo al cliente.
3. Definir servicios disponibles en `servicios.php`.
4. Cargar turno en `turnos.php` asignando mecanico.
5. Crear orden desde dashboard/turnos.
6. Cargar tareas y repuestos de la orden.
7. Generar PDF si corresponde.

## Chatbot n8n (Dashboard)

`dashboard.php` incluye un boton flotante de chat.

Payload enviado al webhook:

```json
{
	"message": "consulta del usuario",
	"source": "dashboard-stm",
	"user": "usuario_logueado",
	"page": "dashboard.php",
	"sent_at": "ISO-8601"
}
```

El workflow de n8n puede responder con campos como:

- `reply`
- `response`
- `answer`
- `message`

## Comandos Utiles

Reinstalacion completa:

```bash
bash reinstall.sh
```

Reset de password admin:

```bash
bash reset_password.sh
```

Validar sintaxis de un archivo PHP:

```bash
php -l src/archivo.php
```

## Publicacion en GitHub

Si ya existe remoto:

```bash
git add .
git commit -m "mensaje"
git push
```

Si no existe remoto:

```bash
git remote add origin https://github.com/MarceloW4M/BackUp.git
git branch -M main
git push -u origin main
```

## Estado del Proyecto

Proyecto activo, con foco en:

- estabilidad de agenda y ordenes
- migracion de legacy Access
- soporte operativo diario del taller

