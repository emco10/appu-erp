# Arquitectura incremental APPUCORP ERP

Fecha: 26-10-2026. Estado: primera migración en curso; este documento distingue el diseño objetivo de la implementación comprobada.

Actualización 27-10-2026: el primer flujo Orden → Entrega → Factura ya utiliza aplicaciones por línea, movimientos físicos y facturación separada, reversión e identificación de reintentos. El alcance ERP completo sigue abierto.

## Diagnóstico basado en el repositorio

PHP 8.1, PDO MySQL/MariaDB, JavaScript con jQuery, Select2 y DataTables; plantillas PHP con Bootstrap/AdminLTE. Apache enruta las vistas mediante `.htaccess` e `index.php`. No existe framework MVC ni gestor de dependencias del proyecto. Los endpoints viven en `modules/`, los controladores de pantalla en `ajax/`, las vistas en `views/` y los servicios compartidos en `global/` y `modules/inventory/stock.php`.

Se conservan tablas físicas por documento. La base incluye cabeceras/detalles de cotización, orden, factura, boleta, nota de crédito, compra, recepción y guía; movimientos, existencias por almacén, reservas, lotes, flujo e historial documental y campos personalizados EAV. No hay una razón técnica para reemplazarlas mediante una reescritura completa.

La suite existente cubre ciclos de servicios y renderizado. Los contratos que buscan cadenas en los fuentes solo prueban la presencia de código; no prueban que un botón funcione. La auditoría exhaustiva de controles y los recorridos de navegador siguen abiertos.

## Límites funcionales

| Área | Responsabilidad | Implementación reutilizable | Brecha objetivo |
|---|---|---|---|
| Plataforma/configuración | Sesión, CSRF, permisos, series, empresa, reglas | `global/authorization.php`, `config.php`, `document-numbering.php` | Metadatos y reglas comunes; preferencias por usuario/rol |
| Datos maestros | Socios, contactos/direcciones, artículos, almacenes, monedas, UoM, precios | Servicios de maestros y `business-partner-details.php` | UoM y conversiones centralizadas; flags de artículos y precios normalizados |
| Ventas y clientes | Oferta, orden, factura interna, anticipo/reserva, crédito | Servicios de orden, `document-engine.php`, `sales-delivery-billing.php` | Completar dominio/formularios para todos los caminos y tipos; el flujo de entrega ya factura por saldo |
| Inventarios y logística | Existencia física, compromiso, solicitado, ledger, traslado | `modules/inventory/stock.php`, guías, recepciones | Separar entrega física de factura en todos los orígenes; conciliación y políticas configurables |
| Compras y proveedores | Solicitud, aprobación, pedido, recepción, factura | Validadores y servicios de compra/recepción | Pedido pendiente por línea, devolución proveedor y efectos Ordered consistentes |
| Bancos y pagos | Obligaciones comerciales y aplicaciones de cobro/pago | `finance-payments.php`, `sales-advance.php` | Conservar separación de movimientos físicos y aplicaciones financieras |
| Trazabilidad/auditoría | Relaciones de cabecera/línea, historial, reversión | `document-history.php`, `tbl_document_flow` | Relaciones por línea y saldo por etapa, sin inferirlas solo del artículo |
| Reportes/análisis | Consultas operativas sobre datos confirmados | Reportes y stock-query existentes | Filtros consistentes, paginación de servidor, columnas/preferencias y UDF consultables |
| Integraciones | Frontera externa desacoplada | Tablas/configuración de integraciones | Adaptadores futuros. SUNAT fuera de alcance |

## Separación de responsabilidades

- **Presentation:** vistas/JavaScript y endpoints; leen peticiones, autorizan y traducen los errores del caso de uso al contrato API.
- **Application:** casos de uso crear, editar, confirmar, copiar, cerrar y cancelar. Una transacción engloba documento, líneas, número, inventario, relaciones, UDF e historial.
- **Domain:** catálogo de tipos, estados/transiciones, reglas de cantidades abiertas, cálculo monetario, validaciones y efectos de inventario. No depende de HTML ni de nombres de campos visuales.
- **Infrastructure:** PDO, repositorios sobre tablas existentes, bloqueos y adaptadores externos. Las consultas no se reciben del navegador.

Estos límites pueden coexistir inicialmente como archivos PHP en `global/`. No es necesario crear cuatro proyectos ni mover todos los archivos para obtener la separación.

## Motor documental común

Cada adaptador de tipo define tabla/campos, tipos destino válidos, permisos, estados, reglas y efectos. Comparte numeración, validación, totales, UDF, relaciones, auditoría y errores. Se migrará primero la orden, después oferta, entrega y factura. Se preservan IDs y documentos históricos.

`global/validation.php` proporciona excepciones estructuradas (`path`, `code`, `message`). `js/document-ui.js` proporciona modos explícitos y presentación de todos los errores. La orden de venta ya usa ambos; los demás controladores siguen en migración.

Estados objetivo: `DRAFT`, `PENDING_APPROVAL`, `OPEN`, `PARTIALLY_PROCESSED`, `CLOSED`, `CANCELLED`. Se necesita un adaptador para los estados legados; no basta renombrar `Pendiente`, `Confirmada`, `Atendida` o los estados numéricos: representan dimensiones comerciales, financieras y logísticas diferentes.

La orden pendiente actual es editable y no reserva. Confirmarla reserva. No equivale a certificar que el concepto completo de borrador/aprobación ya exista para todos los documentos.

`app_order_domain_status` adapta los estados legados al estado de cumplimiento: DRAFT, OPEN, PARTIALLY_PROCESSED, CLOSED o CANCELLED. El estado comercial `Atendida` se recalcula por cantidades facturadas; una orden con toda la mercancía entregada puede estar cerrada para entrega y aún tener saldo facturable. No se confunden ambas dimensiones.

Toda línea derivada debe guardar tipo/ID/línea base, cantidad aplicada y unidad/factor histórico. El saldo se calcula por etapa: entregar consume saldo de entrega; facturar una entrega consume saldo facturable y no vuelve a consumir inventario. Cancelar revierte la aplicación correspondiente. Un índice único evita duplicar la misma aplicación y los bloqueos impiden consumir dos veces el saldo bajo concurrencia.

Los documentos con efectos físicos no admiten cambios libres; utilizan reversión o correctivo. El guardado de la orden pendiente usa huella del documento, detalle y UDF para detectar ediciones obsoletas incluso dentro del mismo segundo.

El primer motor persiste `tbl_document_line_application` por etapa (`CONVERSION`, `FULFILLMENT`, `BILLING`). Las facturas basadas en entrega conservan tipo/ID/línea base y `inventory_origin=DELIVERY`; no producen salida ni reversión física. Anularlas reabre el saldo facturable. Anular la entrega restaura la mercancía y su compromiso, y exige anular antes la factura dependiente.

Se bloquea la orden raíz antes de consultar saldos y se usan lecturas actuales de filas/aplicaciones con `FOR UPDATE`. Una prueba con dos procesos y un snapshot anterior demuestra el rechazo de 5 cuando otro proceso entregó 6 de 10. `tbl_document_request` serializa reintentos por usuario/operación/clave y rechaza reutilizar una clave con contenido distinto.

Los totales de facturas parciales conservan el impuesto calculado y muestran un ajuste de redondeo explícito en la última factura cuando es necesario para conciliar el total de la orden. Un descuadre superior al margen de redondeo permitido bloquea la operación; no se ocultan inconsistencias del origen.

## Matriz objetivo de inventario

| Documento confirmado | OnHand | Committed | Ordered | Condición |
|---|---:|---:|---:|---|
| Oferta | 0 | 0 | 0 | Regla predeterminada |
| Orden de venta | 0 | + | 0 | Confirmada; pendiente/borrador sin efectos |
| Entrega de orden | − | − | 0 | Solo cantidad entregada; liberar su reserva |
| Factura basada en entrega | 0 | 0 | 0 | Nunca segunda salida |
| Factura directa | − | 0 | 0 | Política explícita; sin entrega previa |
| Factura de reserva | 0 | + | 0 | Reutiliza reserva de orden sin duplicarla |
| Anticipo | 0 | 0 | 0 | Solo obligación/aplicación financiera |
| Devolución física | + | 0 | 0 | Independiente del crédito financiero |
| Solicitud de transferencia | 0 | 0 | 0 | Intención |
| Transferencia | −/+ | 0 | 0 | Dos almacenes, una transacción |
| Pedido de compra | 0 | 0 | + | Cantidad pendiente de recibir |
| Recepción de proveedor | + | 0 | − | Solo saldo recibido |

Esta es la regla de migración solicitada, no una descripción de todos los caminos legados. En particular, el flujo actual factura estándar → guía todavía necesita revisión para no mezclar facturación con entrega. Disponible actual = OnHand − Committed; proyectado = OnHand − Committed + Ordered.

El nuevo flujo Orden → Entrega registra la salida y libera solo la reserva entregada. Facturar una orden con estas entregas mediante el camino directo legado queda bloqueado: debe facturar sus entregas. La guía de una factura directa continúa siendo un documento de traslado sin otra salida, porque esa factura ya la produjo. Las guías de reserva sí producen salida física. La matriz se aplica de manera explícita según el origen.

La factura/boleta directa desde orden libera su reserva antes de consumir inventario, dentro de la misma transacción. Mantiene aplicaciones BILLING y FULFILLMENT diferenciadas; el mapa no suma dos veces una misma cantidad por participar en dos etapas. Las cabeceras nuevas no pueden nacer anuladas/canceladas y atender a la vez una orden.

Las guías anteriores a la migración no se reinterpretan como salidas nuevas. `inventory_posted` se reconstruye únicamente cuando existe evidencia en el ledger. Una guía histórica sin esa evidencia no puede convertirse automáticamente en factura de entrega; requiere revisión operativa de su origen/saldos. La migración conserva datos y movimientos históricos.

El ledger se conserva independiente (`tbl_warehouse_movement`); las existencias materializadas deben conciliarse con él. No recalcular stock únicamente desde documentos. Las reservas y los saldos pendientes también requieren reconciliación propia.

## Extensibilidad

Se conserva `tbl_custom_field_definition` / `tbl_custom_field_value`, sin `ALTER TABLE` por alta de campo. La orden consulta metadatos al abrir y renderiza los tipos existentes: texto, número, fecha, lista y booleano. Guarda, consulta y valida sus valores. El error agregado comparte el mismo contrato que los campos nativos.

La migración `20261026_custom_field_metadata.sql` añade default, longitud/rango/patrón, visibilidad, orden/sección, búsqueda configurable y copia optativa. Orden, entrega y factura desde entrega comparten `appuRenderCustomFields`, incluidas secciones y restricciones básicas. El servidor valida todas las reglas. La búsqueda exacta usa un índice por definición/entidad/prefijo de valor/registro. La copia comprueba autorización del origen y compatibilidad de tipo/lista; los valores del formulario se combinan antes de validar las reglas del destino.

`sales-order-http.php` demuestra administración → campo de lista obligatorio → rechazo vacío → guardar → consultar → buscar por valor, sin cambiar el formulario. `custom-field-metadata-lifecycle.php` prueba defaults, restricciones, copia autorizada/compatible, visibilidad y protección de tipos utilizados. Esto acredita el ciclo de cabecera de orden; todavía faltan ámbitos HEADER/LINE/MASTER generales, UDF en líneas, tipos decimal/referencia, valores tipados y filtros en los demás documentos/maestros.

## Decisiones de reutilización

| Clasificación | Pieza | Decisión |
|---|---|---|
| KEEP | PDO, tablas por documento, maestros, IDs, permisos, CSRF | Preservar y cubrir con regresión |
| KEEP | Numeración, ledger, lotes, pagos e historial | Reutilizar dentro de casos de uso transaccionales |
| REFACTOR | Endpoints de ventas/compras | Extraer reglas y acceso a datos; contratos uniformes |
| REFACTOR | Controladores de formulario y barras | Estados explícitos, cancelación de edición, errores completos y control de respuestas AJAX obsoletas |
| REFACTOR | UDF EAV | Metadatos compartidos, valores tipados y filtros permitidos |
| REPLACE | Validación genérica que devuelve solo ERROR/500 | Errores accionables por ruta; logs técnicos separados |
| REMOVE | Acciones sin permiso/estado válido y escrituras libres tras posteo | Ocultar acciones inválidas y rechazar en servidor |
| ADD | Aplicaciones por línea, adaptadores de estado y copia parcial | Nuevas migraciones aditivas, conciliación y pruebas de procesos |
| ADD | UoM/grupos, políticas, preferencias, adjuntos | Servicios comunes; entregas incrementales verificadas |

## Evidencia y límites

La suite `tests/run-smoke-qa.cmd` pasó antes de esta migración y volvió a pasar con los cambios iniciales. `sales-order-document-lifecycle.php` comprueba errores múltiples, alta/consulta/edición, totales, UDF, conflicto de versión y bloqueo de edición confirmada; usa rollback en una base `_qa`.

Cinco pruebas en `sales-order-ui.test.cjs` ejecutan los controladores con jQuery y DOM: limpieza y respuestas obsoletas, búsqueda, consulta/edición/cancelación, errores/foco y fallo de recarga tras guardado. No sustituyen una prueba visual: Computer Use se detuvo al no verificar la URL de Brave. La matriz `ui-action-audit.md` inventaría controles y distingue la evidencia real de las acciones pendientes.

Se agregaron seis casos DOM para entrega/factura/mapa/consulta, y procesos de servicio, HTTP y concurrencia. `verification-20261027.md` contiene la evidencia actual y sus límites. La siguiente prioridad es completar P0 en los maestros y formularios legados; las unidades, Ordered, UDF de línea, preferencias, adjuntos y demás requisitos aún no están certificados.

## Primer adaptador del ciclo común de maestros

`js/master-workspace.js` controla LIST/CREATE/VIEW/EDIT y una búsqueda SEARCH separada para conservar la edición hasta seleccionar un resultado. El adaptador de artículos aporta campos, permisos, columnas y endpoints; el render UDF sigue usando `document-ui.js`. Las lecturas de ficha y lista tienen generaciones independientes, y una navegación de página pendiente no puede reabrir un registro después de Nuevo.

`global/product-master.php` concentra snapshot, versión de atributos editables/UDF, validación y búsqueda parametrizada/paginada. La versión excluye stock: un movimiento de inventario no debe invalidar una edición de descripción o precio. La escritura bloquea el artículo y verifica la versión antes de modificarlo, conserva los UDF ocultos y admite borrar un UDF visible. El endpoint existente conserva sus respuestas antiguas y ofrece JSON al controlador nuevo, que también utiliza las claves transaccionales de petición. La URL de edición anterior sirve la misma vista/controlador; se retiró su implementación duplicada.

Ocho casos DOM y `product-master-http.php` verifican este adaptador. Clientes/proveedores/empleados/usuarios conservan por ahora sus controladores anteriores: la existencia del ciclo común no acredita su migración.
