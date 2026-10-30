# APPU ERP

APPU ERP es un sistema web empresarial para gestionar socios de negocio, artículos, ventas, compras, inventarios, logística, tesorería y reportes. Está construido con PHP, JavaScript y MySQL/MariaDB, con una organización de documentos inspirada en SAP Business One.

## Alcance actual

Los almacenes se administran como maestros de configuración desde Gestión; Inventario queda reservado para stock, movimientos, lotes y transferencias del día a día.

- Maestros: clientes, proveedores, artículos, empleados, usuarios, perfiles y permisos.
- Ventas y CxC: cotizaciones, órdenes de venta, facturas, boletas, notas de crédito y cobros recibidos.
- Compras y CxP: solicitudes, órdenes de compra, facturas de proveedor, recepciones parciales y pagos efectuados.
- Inventario y logística: almacenes, stock por almacén, kardex, transferencias, lotes, vencimientos, reservas y guías de remisión internas.
- Gestión: datos de empresa, listas de valores, series, cuentas financieras, integraciones externas y campos personalizados.
- Trazabilidad: mapa documental, historial de estados, aprobaciones, anulaciones con motivo y reversión de movimientos.
- Reportes y dashboard: ventas, compras, finanzas, vencimientos, stock bajo, almacenes y transferencias.

Los maestros y documentos se conservan para auditoría. Las bajas operativas se manejan como estados inactivos o anulados; no se eliminan físicamente documentos históricos.

La guía de remisión y los despachos son documentos operativos internos. No se envían a SUNAT ni constituyen una GRE electrónica. La integración con SUNAT queda fuera del alcance actual.

## Requisitos

- Apache con `mod_rewrite`.
- PHP 8.1 o superior con PDO MySQL.
- MySQL o MariaDB.

## Instalación

1. Configura Apache para servir la carpeta del proyecto, mediante un Alias o una unión de directorio. En el entorno de Erick, `C:\xampp\htdocs\APPU ERP` ya apunta a `C:\Users\Erick Cieza\Desktop\PROYECTOS DE SISTEMAS\APPU ERP`; edita siempre esta última carpeta.
2. Provisiona la base MySQL o MariaDB a partir de un respaldo/esquema obtenido por un canal privado. El volcado local `db/db_appu.sql` no se incluye en el repositorio porque contiene datos personales.
3. Configura opcionalmente `APPU_DB_HOST`, `APPU_DB_USER`, `APPU_DB_PASSWORD` y `APPU_DB_NAME`.
4. Habilita `mod_rewrite` y permite que Apache lea `.htaccess`.
5. Abre la URL del proyecto e inicia sesión con un usuario activo.

Si la base ya existía, aplica en orden los scripts de `db/migrations/`, incluyendo los más recientes de almacenes, lotes, series, pagos, compras, campos personalizados, integraciones y estados activos de clientes, proveedores, empleados y usuarios.

La migración `20261026_custom_field_metadata.sql` añade búsqueda, copia optativa, defaults y restricciones a las definiciones de campos. Se aplica una sola vez después de las migraciones anteriores. El 29-10-2026 se completaron los metadatos y protecciones de operaciones pendientes de la base operativa, con respaldo previo y comparación de conteos/saldos. El comprobador CLI `tests/schema-readiness.php` informa si el esquema está listo.

Después se aplican, una sola vez, `20261026_document_line_flow.sql` y `20261027_document_operation_guards.sql`. Añaden relaciones por línea, origen físico de la factura, trazabilidad de movimientos, índices, reintentos y redondeo explícito. El nuevo flujo Orden → Entrega → Factura requiere estas migraciones. Las guías históricas solo se marcan como salidas físicas si existe evidencia en el ledger; no se descuenta stock retrospectivamente.

## Pruebas de la migración funcional

Desde la raíz, `tests\run-smoke-qa.cmd` ejecuta renderizado y ciclos transaccionales con `db_appu_qa`. Los cambios de arquitectura y las brechas se detallan en `docs/architecture-v2.md`, `docs/migration-plan.md` y `docs/initial-audit-v2.md`.

La ejecución integrada está en `powershell -ExecutionPolicy Bypass -File tests/run-all-qa.ps1`. Incluye los flujos HTTP y una sesión real para verificar permisos/CSRF. Los servidores QA se limitan a loopback y bases con sufijo `_qa`, y el ejecutor solo detiene procesos que él mismo inició. Los respaldos quedan bajo `db/backups/`, fuera de Git y bloqueados por Apache.

Las pruebas de controladores usan Node y dependencias aisladas de la aplicación PHP:

```powershell
pnpm --dir tests install --frozen-lockfile
pnpm --dir tests test
python tests/audit-ui-actions.py
```

Para probar HTTP con datos sintéticos, inicia en una consola un servidor de loopback que solo admite una base `_qa`:

```powershell
$env:APPU_DB_NAME='db_appu_qa'
$env:APPU_DOCUMENT_QA='1'
& C:\xampp\php\php.exe -S 127.0.0.1:8765 tests/document-workbench.php
```

En otra consola, con `APPU_DB_NAME=db_appu_qa`, ejecuta `C:\xampp\php\php.exe tests/sales-order-http.php` y `C:\xampp\php\php.exe tests/sales-fulfillment-http.php`. Verifican la identidad QA del servidor antes de escribir, conservan CSRF y limpian sus fixtures. La segunda prueba recorre confirmación, parciales, facturas desde entregas, mapa, reintentos, anulaciones y rollback tardío. Detén el servidor al terminar. El router devuelve 404 fuera de ese entorno y no es una entrada para Apache.

La auditoría `docs/ui-action-audit.md` distingue pruebas de servicio, HTTP, DOM y revisión visual. Los controles inventariados sin ejecución individual permanecen pendientes.

El maestro de artículos comparte los modos de consulta/edición y el render de campos adicionales. Conserva `editar-producto?id=…` como ruta compatible y utiliza el mismo guardado existente, con JSON para la UI nueva y respuestas anteriores para consumidores legados. Requiere las migraciones de metadatos y de protección de operaciones indicadas arriba. `C:\xampp\php\php.exe tests/product-master-http.php` prueba alta, reintento, edición concurrente, UDF, búsqueda/paginación y estado con el mismo servidor QA; las ocho pruebas DOM de artículos se incluyen en `pnpm --dir tests test`.

## Seguridad y operación

La aplicación centraliza autenticación, permisos por endpoint, CSRF, sesiones y validaciones de maestros. Antes de publicar el sistema deben reemplazarse las credenciales de desarrollo, anonimizarse los datos de ejemplo, usar HTTPS y configurar copias de seguridad de la base de datos.

La versión visible de recursos se controla en `global/config.php` mediante `SCRIPT_VER`, para que Apache y el navegador carguen siempre los cambios del proyecto.

Las fechas de la aplicación usan `America/Lima`; PDO fija la zona horaria de la sesión MySQL en `-05:00` para que los valores de PHP y SQL coincidan.
