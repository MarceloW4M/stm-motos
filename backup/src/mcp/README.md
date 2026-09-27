# STM MCP HTTP para n8n

Este módulo expone un servidor MCP real sobre HTTP usando JSON-RPC 2.0, listo para ser consumido por un cliente MCP de n8n con autenticación por headers. Además mantiene la API HTTP previa como compatibilidad para flujos existentes.

Ubicación principal: [src/mcp/index.php](/root/stm-taller-motos/src/mcp/index.php)

## Qué resuelve

- Cliente MCP remoto desde n8n contra `/mcp/`
- Autenticación por header configurable
- Inspección completa de tablas MySQL
- Recursos MCP para leer esquemas y muestras de datos
- Compatibilidad con los endpoints REST heredados

## Transporte MCP

- Tipo: Streamable HTTP
- Endpoint: `/mcp/`
- Método MCP: `POST`
- Formato: JSON-RPC 2.0

Una petición GET a `/mcp/` devuelve metadatos del servidor, herramientas disponibles y configuración de auth.

## Autenticación por Header

Variables relevantes en [src/includes/config.php](/root/stm-taller-motos/src/includes/config.php):

- `API_TOKEN`: secreto compartido
- `MCP_AUTH_HEADER`: header esperado, por defecto `Authorization`
- `MCP_AUTH_SCHEME`: esquema, por defecto `Bearer`

Ejemplo recomendado para n8n:

```text
Header: Authorization
Value: Bearer TU_TOKEN_MCP
```

También se aceptan `X-MCP-Auth` y `X-API-KEY` como fallback.

## Herramientas MCP disponibles

- `stm_database_overview`: resumen de base, auth y tablas faltantes/inesperadas
- `stm_list_tables`: listado de tablas y metadatos básicos
- `stm_describe_table`: columnas, índices, FKs, row count y CREATE TABLE
- `stm_get_table_rows`: muestra paginada de filas
- `stm_verify_database`: auditoría completa de todas las tablas
- `stm_execute_readonly_query`: SQL de solo lectura controlado

## Recursos MCP disponibles

- `stm://schema/overview`
- `stm://table/{table}/schema`
- `stm://table/{table}/sample?limit={limit}`

## Configuración en n8n

En el nodo MCP Client:

1. Elegí transporte HTTP o Streamable HTTP.
2. Configurá la URL base hacia `/mcp/`.
3. Agregá Header Auth con el header configurado.
4. Ejecutá la inicialización del cliente.

Valores típicos:

```text
URL: http://localhost:8080/mcp/
Header: Authorization
Value: Bearer TU_TOKEN_MCP
```

## Ejemplos JSON-RPC

Inicializar:

```json
{
  "jsonrpc": "2.0",
  "id": 1,
  "method": "initialize",
  "params": {
    "protocolVersion": "2025-03-26",
    "capabilities": {},
    "clientInfo": {
      "name": "n8n",
      "version": "1.x"
    }
  }
}
```

Listar herramientas:

```json
{
  "jsonrpc": "2.0",
  "id": 2,
  "method": "tools/list"
}
```

Verificar todas las tablas:

```json
{
  "jsonrpc": "2.0",
  "id": 3,
  "method": "tools/call",
  "params": {
    "name": "stm_verify_database",
    "arguments": {
      "include_sample_rows": false
    }
  }
}
```

Describir una tabla:

```json
{
  "jsonrpc": "2.0",
  "id": 4,
  "method": "tools/call",
  "params": {
    "name": "stm_describe_table",
    "arguments": {
      "table_name": "clientes"
    }
  }
}
```

Ejecutar SQL de solo lectura:

```json
{
  "jsonrpc": "2.0",
  "id": 5,
  "method": "tools/call",
  "params": {
    "name": "stm_execute_readonly_query",
    "arguments": {
      "query": "SELECT COUNT(*) AS total_clientes FROM clientes"
    }
  }
}
```

## API heredada que sigue disponible

GET:

- `/mcp/clients`
- `/mcp/vehicles`
- `/mcp/orders`

POST protegidos por token:

- `/mcp/turnos`
- `/mcp/telegram`

También podés usar `?resource=clients`, `?resource=vehicles`, etc., si tu proxy no conserva bien el path.

## Recomendaciones

- Definí `API_TOKEN` en el entorno del contenedor o servidor
- No expongas este endpoint sin TLS en Internet
- Restringí CORS en producción
- Si usás n8n externo, publicá solo `/mcp/` detrás de un reverse proxy

## Verificación manual con curl

```bash
curl -X POST 'http://localhost:8080/mcp/' \
  -H 'Content-Type: application/json' \
  -H 'Authorization: Bearer TU_TOKEN_MCP' \
  -d '{
    "jsonrpc":"2.0",
    "id":1,
    "method":"tools/call",
    "params":{
      "name":"stm_verify_database",
      "arguments":{}
    }
  }'
```
