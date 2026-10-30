# Auditoría inicial de APPU

Fecha: 2026-10-23

## Alcance revisado

- 122 archivos PHP propios y sus rutas de inclusión.
- JavaScript, vistas, estilos, configuración, volcado SQL y recursos de marca.
- Flujo de sesión, acceso a módulos, consultas SQL y operaciones de stock/facturación.
- Arranque HTTP local y carga de los recursos principales.

## Cambios completados

- Identidad visual y textual unificada como APPU en interfaz, títulos, reportes, documentación, SQL y nombres de archivo.
- Sustitución de recursos gráficos anteriores por el logo oficial de APPU proporcionado por la empresa.
- Enrutamiento limitado a vistas válidas, eliminando la inclusión arbitraria de archivos.
- Autenticación central para módulos y protección CSRF para solicitudes que modifican datos.
- Regeneración del identificador de sesión al iniciar sesión y cookies `HttpOnly`/`SameSite=Lax`.
- Consultas de búsqueda y filtros convertidas a parámetros preparados.
- Filtro combinado de cotizaciones corregido; antes podía generar más de una cláusula `WHERE`.
- Facturas, boletas, notas de crédito y movimientos de almacén ahora usan transacciones.
- Uso de `lastInsertId()` para asociar detalles al documento creado por la conexión actual.
- Actualización de stock atómica, con rechazo de stock negativo al vender.
- Protección contra división por cero en el cálculo porcentual del impuesto.
- Errores internos de conexión dejan de exponer detalles sensibles al navegador.
- Acceso HTTP a `db/`, `global/` y archivos SQL/configuración bloqueado en Apache.

## Verificación realizada

- Sintaxis PHP: 121/121 archivos actuales válidos con PHP 8.1.4; además se retiró una copia obsoleta de reporte.
- Búsqueda global: sin referencias textuales ni nombres de archivo de la identidad anterior o autores anteriores.
- HTTP local: login `200`, logo `200`, estilo `200` y módulo sin sesión `401`.
- Logo: PNG de 1254 x 1254, RGBA y fondo transparente.

## Riesgos pendientes para la siguiente etapa

1. El volcado SQL incluye datos personales y hashes de contraseña con apariencia de datos reales. Debe anonimizarse antes de compartir o publicar el repositorio.
2. No existe autorización por roles en el servidor. Un usuario autenticado puede intentar invocar módulos administrativos directamente.
3. El esquema no declara claves foráneas. Es posible crear registros huérfanos o eliminar entidades todavía referenciadas.
4. Cotizaciones, órdenes y compras aún requieren transacciones completas al actualizar cabecera y detalles.
5. La numeración de cotizaciones y órdenes usa `MAX(id) + 1`, susceptible a duplicados bajo concurrencia.
6. Faltan pruebas automatizadas y pruebas de integración con una base APPU real.
7. Varias dependencias se cargan desde CDN sin integridad SRI ni una política CSP.

## Recomendación para el segundo paso

Priorizar anonimización del SQL, autorización por roles y restricciones de integridad de base de datos; después incorporar pruebas de integración para ventas, notas de crédito, stock y eliminaciones.
