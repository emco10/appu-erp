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

El **Mapa de relaciones** concentra el resumen de vínculos recientes y la exploración interactiva de documentos con sus aplicaciones por línea. La ruta anterior **Flujo documental** se conserva como acceso compatible al mismo mapa. **Seguimiento de despachos** queda reservado para consultar y actualizar entregas operativas, no para navegar relaciones generales.

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

Si la base ya existía, aplica en orden los scripts de `db/migrations/`, incluyendo los más recientes de almacenes, lotes, seguimiento de artículos por serie/lote/ninguno, series documentales, pagos, compras, campos personalizados, integraciones y estados activos de clientes, proveedores, empleados y usuarios. La migración `20261101_add_product_tracking_type.sql` conserva el valor predeterminado `NONE` y marca como `LOT` los artículos con historial de lotes; `tests/schema-readiness.php` comprueba que la columna esté instalada.

### Migraciones automáticas en Hostinger

Agrega cada cambio de esquema como un archivo SQL nuevo en `db/migrations/`, con nombre cronológico único `YYYYMMDD_nombre.sql`; nunca edites una migración aplicada. El workflow `.github/workflows/hostinger-migrations.yml` espera el despliegue de Hostinger verificando los checksums y luego ejecuta `php db/migrate.php --apply` por SSH. Las migraciones aplicadas se registran con checksum en `appu_schema_migrations`, se serializan con un lock MySQL y no se repiten.

Los secretos SSH requeridos son `HOSTINGER_SSH_HOST`, `HOSTINGER_SSH_PORT`, `HOSTINGER_SSH_USER`, `SSH_PRIVATE_KEY`, `HOSTINGER_SSH_KNOWN_HOSTS` y `HOSTINGER_APP_PATH`. PHP CLI del hosting lee la configuración privada de BD en `~/.appu-erp/database.php`; el archivo debe devolver `host`, `user`, `password` y `database`, tener permisos `0600` y estar fuera del directorio público. `global/config.php` también mantiene soporte para las variables `APPU_DB_HOST`, `APPU_DB_USER`, `APPU_DB_PASSWORD` y `APPU_DB_NAME`. No guardes contraseñas en GitHub como texto del código ni en el repositorio. Configura el entorno `production` en GitHub con revisores obligatorios antes de confiar cambios de esquema a producción.

No habilites migraciones productivas hasta crear y probar un respaldo restaurable y reconciliar la estructura existente. La base actual puede tener historial incompleto; `php db/migrate.php --status` informa el estado. La línea base no inspecciona el esquema: si detectas diferencias puntuales, crea primero una migración correctiva nueva y déjala pendiente; establece la línea base solo hasta la última migración cuyo esquema ya verificaste para que el workflow aplique luego la correctiva:

```sh
php db/migrate.php --baseline --through=20261101_normalize_product_tax_affectation.sql --confirm-db=u623025990_appucorp_erp --confirm-backup=YES
```

El comando registra como aplicadas las migraciones hasta ese archivo, sin ejecutarlas ni inspeccionar su contenido; reemplaza el nombre de la base si corresponde y no lo ejecutes hasta crear un respaldo y verificar la comparación de esquema. `--apply` se niega a operar si falta el historial. No importes un volcado completo para actualizar el esquema.

La migración `20261026_custom_field_metadata.sql` añade búsqueda, copia optativa, defaults y restricciones a las definiciones de campos. Se aplica una sola vez después de las migraciones anteriores. El 29-10-2026 se completaron los metadatos y protecciones de operaciones pendientes de la base operativa, con respaldo previo y comparación de conteos/saldos. El comprobador CLI `tests/schema-readiness.php` informa si el esquema está listo.

Después se aplican, una sola vez, `20261026_document_line_flow.sql` y `20261027_document_operation_guards.sql`. Añaden relaciones por línea, origen físico de la factura, trazabilidad de movimientos, índices, reintentos y redondeo explícito. El nuevo flujo Orden → Entrega → Factura requiere estas migraciones. Las guías históricas solo se marcan como salidas físicas si existe evidencia en el ledger; no se descuenta stock retrospectivamente.

Las altas desde los formularios de facturas y boletas usan una clave idempotente por operación dentro de la misma transacción del documento. Los reintentos de una solicitud con la misma clave y datos devuelven el resultado anterior sin reservar otro correlativo; la unicidad serie/número de la base sigue siendo la última barrera contra colisiones.

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

La prueba de disponibilidad de integraciones solo conecta por HTTP(S) a direcciones públicas y fija la IP validada para evitar cambios DNS entre validación y conexión. Para integraciones internas aprobadas, configura `APPU_INTEGRATION_PRIVATE_HOST_ALLOWLIST` con los hosts exactos separados por comas; no admite comodines y queda vacía por defecto.

La versión visible de recursos se controla en `global/config.php` mediante `SCRIPT_VER`, para que Apache y el navegador carguen siempre los cambios del proyecto.

Las fechas de la aplicación usan `America/Lima`; PDO fija la zona horaria de la sesión MySQL en `-05:00` para que los valores de PHP y SQL coincidan.
