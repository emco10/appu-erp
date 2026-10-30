# Auditoría de acciones de interfaz

Estado: en curso. Esta auditoría no declara aprobado un botón solo porque tenga un `id` o un handler.

## Criterios

- **PASS automatizada:** una prueba reproducible verifica la acción o su resultado relevante.
- **PASS navegador:** la interacción fue ejecutada en la interfaz y se verificó el resultado.
- **PENDIENTE:** el control aparece en el código o tiene handler, pero todavía no se ejecutó una prueba suficiente.
- Las acciones que crean, modifican, anulan o eliminan datos reales no se ejecutan en la sesión del usuario sin una base aislada.
- Los controles generados por JavaScript, los iconos sin texto y los botones que aparecen según permisos/estado requieren inventario complementario por vista.

## Acciones revisadas

| Pantalla | Botón/acción | Acción esperada | Evidencia disponible | Resultado |
|---|---|---|---|---|
| Barra superior | Crear | Abrir opciones de creación permitidas por perfil | Navegación probada en navegador en escritorio y ancho intermedio; regresión de permisos/render en smoke | PASS navegador + automatizada |
| Barra superior | Buscar | Abrir opciones de búsqueda permitidas por perfil | Regresión de navegación/permisos en `tests/document-navigation-contract.php` | PASS automatizada; interacción completa pendiente |
| Barra superior | Navegar | Abrir/cerrar menú sin cambiar la URL y permanecer visible desde 576 px | Probado en navegador a 730 px y 1440 px; regresión en `tests/document-navigation-contract.php` | PASS navegador + automatizada |
| Clientes | Nuevo, Buscar, primero/anterior/siguiente/último, Guardar, Desactivar | Limpiar/consultar/navegar/guardar/desactivar el cliente correspondiente | Handlers en `ajax/registro-cliente.js`; ciclo alta-búsqueda-edición-estado en `tests/customer-lifecycle.php` | PASS automatizada para ciclo; clics individuales pendientes |
| Proveedores | Nuevo, Buscar, seleccionar resultado, primero/anterior/siguiente/último, Guardar, Desactivar | Limpiar/consultar/navegar/guardar/desactivar el proveedor correspondiente | Handlers en `ajax/registro-proveedor.js`; ciclo de proveedor y búsqueda de inactivos en `tests/provider-lifecycle.php`; regresión en `tests/ui-actions-contract.php`; Nuevo, Buscar y selección probados en navegador; posición verificada como `1 de 1` | PASS para Nuevo/Buscar/seleccionar; Guardar y Desactivar pendientes en navegador |
| Productos | Nuevo, Buscar, navegar, Guardar, Limpiar, Listado de productos | Crear/recuperar producto, guardar, limpiar o abrir listado | Handlers en `ajax/registro-producto.js`; ciclo en `tests/product-lifecycle.php` | PASS automatizada para ciclo; clics individuales pendientes |
| Cotizaciones | Seleccionar/Buscar, navegación, Nuevo, agregar artículo, Guardar, Anular, usar cliente | Buscar o crear cotización, administrar líneas, guardar o anular | `quotation-lifecycle.php` y conversión cubren ciclos backend; Choose From List probado en navegador; selección carga maestro/direcciones y marca cambios pendientes | PASS navegador para elegir cliente y completar ficha; guardado/anulación/copia pendientes |
| Órdenes de venta | Nueva orden, actualizar, agregar/quitar línea, Crear, historial, confirmar, anular | Crear o consultar orden y ejecutar transiciones autorizadas | Handlers en `ajax/resumen-ordenes-venta.js`; destino de creación cubierto por contrato de navegación | PENDIENTE de interacción funcional en navegador |
| Factura, boleta y nota de crédito | Seleccionar origen, Nuevo, agregar artículo, Guardar, Anular, cambiar estado | Crear/consultar el documento correcto y conservar sus relaciones | Pruebas de ciclo de documentos en smoke; controles localizados en vistas de facturación | PENDIENTE de prueba individual en navegador |
| Solicitudes de compra | Agregar artículo, Guardar solicitud, Actualizar, abrir, aprobar, rechazar, cancelar/convertir | Gestionar líneas y transiciones según estado y permiso | En QA, el CFL cargó `QA-ART-001`, mostró descripción/precio, rechazó `1.5`, guardó la línea con cantidad 2 y permitió aprobar; ciclos en smoke | PASS navegador para artículo, entero, guardado y aprobación; abrir/rechazar/cancelar pendientes |
| Orden de compra | Seleccionar, agregar artículo, Registrar orden, Eliminar orden | Seleccionar origen/artículo y crear o anular la orden | En QA, se copió la solicitud aprobada, se eligió proveedor, se guardó y abrió de nuevo la OC; línea/cantidad e historial de conversión verificados. `purchase-order-cancellation-lifecycle.php` valida anulación y reapertura auditada del origen | PASS navegador para copiar/guardar/abrir; anulación UI pendiente (el navegador de prueba no soporta `prompt()`); ciclo backend PASS |
| Factura de proveedor y compra interna | Seleccionar orden, Nueva factura, agregar artículo, Registrar compra, Eliminar compra | Crear la compra relacionada y aplicar su ciclo logístico/contable | `tests/purchase-lifecycle.php` y `tests/purchase-reserve-invoice-lifecycle.php` | PASS automatizada para ciclos; clics individuales pendientes |
| Recepción y costos de entrega | Nueva/buscar compra, limpiar filtros, Registrar recepción, Actualizar, Registrar costo | Consultar y contabilizar recepción/costo relacionado | Pruebas de compra, inventario y costos de entrega en smoke | PASS automatizada para ciclos; filtros y controles auxiliares pendientes |
| Cuentas por cobrar/pagar | Actualizar, Registrar cobro/pago, cerrar modal | Refrescar saldos y registrar el pago/cobro seleccionado | `tests/finance-payment-lifecycle.php` | PASS automatizada para ciclo financiero; interfaz pendiente |
| Inventario | Actualizar stock, transferencia, almacenes, consulta por almacén y movimientos | Registrar movimiento válido y consultar stock | Pruebas `inventory-lifecycle.php`, `warehouse-lifecycle.php` y smoke de inventario | PASS automatizada para ciclos; controles de consulta pendientes |
| Guías y despachos | Nueva guía, X, Cancelar, elegir origen, Registrar guía/despacho | Registrar entrega y mantener stock/estado coherentes; permitir cerrar el formulario sin guardar y explicar fallos de carga | `tests/sales-delivery-status.php`, regresión de cierre en `tests/ui-actions-contract.php`; apertura y cierre con X/Cancelar comprobados en navegador | PASS para apertura/cierre; fallos de carga con mensajes accionables; el resto de controles UI sigue pendiente |
| Gestión, reportes y administración | Guardar/crear, filtros, consultas y exportaciones visibles | Persistir configuración o presentar el resultado solicitado | Pruebas de integridad, renderizado y permisos en smoke | PENDIENTE de inventario y ejecución por control |

## Inventario de documentos y ciclos

El repositorio expone estas familias funcionales. `Ciclo backend` significa que existe al menos una prueba de servicio/BD; **no** acredita por sí sola el flujo del formulario y sus botones.

| Módulo | Documento/objeto | Ciclo automatizado existente | Copiar desde / origen | Estado de auditoría UI |
|---|---|---|---|---|
| Ventas | Cotización | `quotation-lifecycle.php` | Conversión a orden de venta | PENDIENTE: selector, alta/edición/anulación y conversión en navegador |
| Ventas | Orden de venta | `quotation-conversion-lifecycle.php`, `sales-delivery-status.php` | Cotización; orden confirmada puede originar factura/boleta y guía | PENDIENTE: probar cada origen con datos fixture y cada transición desde la UI |
| Ventas | Factura estándar | `credit-note-lifecycle.php`; no se encontró un ciclo aislado específico completo de factura estándar | Cotización u orden de venta confirmada | PENDIENTE: ciclo estándar e interfaz |
| Ventas | Factura de reserva | `reserve-invoice-lifecycle.php` | Orden de venta confirmada; entrega posterior mediante guía | PENDIENTE: flujo de copia y reversión desde formulario |
| Ventas | Factura de anticipo | `sales-advance-lifecycle.php` | Orden confirmada; aplicación a factura final | PENDIENTE: selector y aplicación desde UI |
| Ventas | Boleta | La búsqueda del repositorio no identificó un ciclo de creación/anulación equivalente dedicado | Cotización u orden de venta confirmada | PENDIENTE: ciclo aislado e interfaz |
| Ventas | Nota de crédito | `credit-note-lifecycle.php` | Factura o boleta elegible; acredita cantidades/importes remanentes | PENDIENTE: elegir origen y probar líneas/documento en UI |
| Compras | Solicitud de compra | `purchase-request-approval-lifecycle.php` | Se convierte a orden de compra cuando está aprobada | PASS navegador para seleccionar artículo, impedir decimales, guardar/aprobar; revisar abrir/rechazar/cancelar |
| Compras | Orden de compra | `purchase-lifecycle.php` valida el ID real de artículo de la fila visual, la elegibilidad y cantidades del origen; `purchase-copy-contract.php` protege el enlace UI/API; `purchase-order-cancellation-lifecycle.php` valida reapertura del origen tras anular la última OC activa | Solicitud aprobada; la interfaz copia detalle y datos de cabecera | PASS navegador para copiar, elegir proveedor, guardar y recuperar la OC; PASS backend para anular/reabrir; anulación UI y edición pendientes |
| Compras | Factura de proveedor estándar/reserva | `purchase-lifecycle.php`, `purchase-reserve-invoice-lifecycle.php` | Orden de compra | PENDIENTE: copy-from y ciclo UI; revisar reglas de recepción |
| Compras | Recepción de mercancías | `purchase-lifecycle.php` | Orden/factura de compra; recepción parcial por líneas | PENDIENTE: prueba de formulario, selector y límites por saldo |
| Compras | Costos de entrega | `landed-cost-lifecycle.php` | Recepción de mercancías | PENDIENTE: selección de recepción y persistencia en UI |
| Logística | Guía de remisión | `sales-delivery-status.php`, `reserve-invoice-lifecycle.php` | Factura, boleta u orden confirmada, limitado por saldo de entrega | PASS navegador: abrir, cerrar con X y Cancelar; cargas comunican errores; PENDIENTE: selector de fuente, cantidades parciales y estado en UI |
| Logística | Despacho/documentos | No se encontró ciclo aislado por documento en la suite | Guía/documento logístico | PENDIENTE: determinar alcance operativo, ciclo y prueba |
| Finanzas | Cuenta por cobrar / pagar | `finance-payment-lifecycle.php` | Derivadas de documentos comerciales/compras | PASS backend para pagos parciales/totales/múltiples y reversión; UI pendiente |
| Finanzas | Cobro / pago | `finance-payment-lifecycle.php` | Aplica una o más cuentas elegibles del mismo socio/moneda | PENDIENTE: formularios, botones, errores y refresco UI |
| Inventario | Ajuste/movimiento | `inventory-lifecycle.php` | No es copia documental | PENDIENTE: controles de registrar/consultar en UI |
| Inventario | Transferencia entre almacenes | Existe endpoint `modules/inventory/transfers.php`; no se encontró ciclo dedicado que pruebe el formulario completo | Almacén origen → almacén destino | PENDIENTE: ciclo aislado, permisos, cantidades y reversión |
| Inventario | Almacén | `warehouse-lifecycle.php` | No es copia documental | PASS backend; interacción UI pendiente |
| Maestros | Cliente, proveedor, artículo, usuario, empleado | `customer-lifecycle.php`, `provider-lifecycle.php`, `product-lifecycle.php`, `administration-integrity.php` | Seleccionados por documentos de ventas/compras/logística | PASS backend; búsquedas maestras parcialmente probadas |
| Órdenes | Orden de servicio | No se encontró prueba de ciclo dedicada | No se encontró relación copy-from configurada | PENDIENTE: definir si es documento operativo y completar ciclo |

Las pruebas enumeradas son una cobertura parcial existente, no una certificación exhaustiva. En particular, operaciones de conversión, recepción, contabilización, anulación y reversión necesitan validación integrada con la ruta HTTP/UI, además de los ciclos transaccionales de backend.

## Entorno QA y verificación reciente

- Se creó la base local `db_appu_qa` copiando únicamente el esquema actual de `db_appu`; no se copiaron filas operativas. `tests/qa-seed.sql` carga datos sintéticos para empresa, usuario, socios, artículo, almacén y listas/series.
- `tests/run-smoke-qa.cmd` fija `APPU_DB_NAME=db_appu_qa` antes de invocar la suite. La suite completa pasó el 26-10-2026, incluido el nuevo ciclo de anulación de orden y reapertura del origen.
- En el navegador conectado a QA se recorrió Solicitud → aprobación → Orden de compra: el selector mostró el artículo maestro, el valor decimal fue rechazado, la solicitud se guardó/aprobó, la OC copió las líneas, se registró y se abrió otra vez con cantidad y artículo correctos.
- No equivale a la auditoría exhaustiva solicitada: los demás documentos y botones siguen pendientes de recorrido individual. La anulación desde el botón de la OC tampoco se pudo completar en esta automatización porque el navegador integrado no soporta el `window.prompt()` que solicita el motivo; la ruta backend sí está cubierta por la prueba de ciclo.

## Búsquedas, Choose From List y copia entre documentos

| Capacidad | Estado encontrado | Trabajo requerido |
|---|---|---|
| Búsqueda por código/nombre en maestros | Hay endpoints de búsqueda y selectores/autocompletes específicos (p. ej. artículos, socios de negocio y órdenes) | Normalizar criterios, etiquetas, resultados sin coincidencia, errores de red, paginación y teclado |
| Choose From List (CFL/F4) | Hay Select2, autocompletes y listas de selección, pero no un contrato o componente CFL común para todos los documentos | Inventariar cada campo de referencia; ofrecer búsqueda filtrable, selección por teclado, datos descriptivos, estado activo y criterios de elegibilidad; validar el registro de nuevo en servidor |
| Búsqueda formateada | No se encontró implementación de consultas guardadas/mapeos de resultados ni una convención compartida en PHP/JS/SQL | Diseñar una capacidad explícita antes de habilitarla. Usar consultas nombradas y revisadas, parámetros tipados, columnas/campos destino permitidos, permisos y auditoría; nunca aceptar SQL arbitrario del navegador |
| Copiar de documento a documento | Existen flujos especializados, entre otros cotización → orden de venta, solicitud aprobada → orden de compra, orden → factura de proveedor/recepción, orden de venta → factura/boleta/guía, y recepción → costo de entrega | Verificar cada enlace con fixtures y asegurar cabecera, líneas, cantidades pendientes, precios, impuestos, moneda, almacén, direcciones históricas y trazabilidad. No copiar campos calculados como autoridad del cliente |
| Selección de origen con saldo | Algunos servicios validan estados y entregas/recepciones parciales en servidor | Hacer coherentes las cantidades máximas en pantalla y mantener el servidor como autoridad ante concurrencia o cambios posteriores |

Al probar la vista de factura en navegador (25 de octubre de 2026), el formulario y los controles Select2 se renderizaron. Las listas de facturas, cotizaciones y órdenes confirmadas mostraron únicamente el estado vacío; no se alteró ningún documento. Esto impide probar la copia en esa sesión, pero no demuestra que el endpoint esté roto: se necesita un conjunto de datos QA explícito para distinguir “sin candidatos elegibles” de error de carga.

## Organización de módulos inspirada funcionalmente en SAP Business One

La guía es funcional, no una reproducción de pantallas ni de código propietario. La referencia pública es el [portal de ayuda de SAP Business One](https://help.sap.com/docs/SAP_BUSINESS_ONE). Para APPU conviene mantener los límites de módulo alrededor de ciclos de negocio y documentos relacionados:

1. **Datos maestros y configuración:** socios de negocio, artículos, empleados, usuarios/permisos, almacenes, monedas, listas, series y campos de usuario.
2. **Ventas:** cotización → orden de venta → factura/boleta; notas de crédito vinculadas; entrega y facturas de anticipo/reserva como variantes explícitas.
3. **Compras:** solicitud → aprobación → orden de compra → factura de proveedor y recepción; costos de entrega ligados a recepciones.
4. **Inventario y logística:** existencias por almacén, movimientos, transferencias, reservas/lotes y guías/entregas.
5. **Finanzas:** cuentas por cobrar/pagar y cobros/pagos como aplicaciones auditables a documentos origen.
6. **Informes y administración:** lectura consolidada, configuración, trazabilidad y permisos, sin poner reglas transaccionales críticas solo en la interfaz.

Prácticas transversales a aplicar: cabecera y líneas con validación autoritativa en servidor; estados/transiciones explícitos; relaciones de origen/destino persistidas; copia únicamente desde documentos elegibles y por cantidades pendientes; fotografía histórica de datos que no debe mutar con el maestro; transacciones para contabilización/reversión; listas de selección con permisos y filtros; errores por campo/línea; y pruebas de contrato más ciclos integrales aislados por documento.

## Hallazgo corregido

En [ajax/registro-proveedor.js](../ajax/registro-proveedor.js), la acción de desactivar proveedor reiniciaba la etiqueta del botón Guardar a “Guardar cliente”. Se corrigió a “Guardar proveedor”. También se sincronizó la posición de navegación cuando se selecciona un proveedor en la tabla (antes se mostraba `0 de N`). Ambos contratos están en `tests/ui-actions-contract.php`, integrado en `tests/run-smoke.cmd`.

La búsqueda de proveedores fallaba con HTTP 500 al pulsar **Buscar**. La consulta repetía un placeholder con nombre (`:term`) aunque PDO usa preparaciones nativas. Se asignaron placeholders distintos para razón social y código; `tests/provider-lifecycle.php` ahora ejecuta la búsqueda real incluyendo un proveedor inactivo. En navegador se comprobó que Buscar carga el listado, seleccionar abre el maestro y la barra pasa de `0 de 1` a `1 de 1`.

En la confirmación de descarte de cambios se detectó en navegador una advertencia de SweetAlert2: el proyecto carga la versión 8.11.8, que no reconoce la opción `icon` usada por versiones posteriores. Se cambió a `type`, compatible con la dependencia instalada, y `tests/unsaved-changes-contract.php` verifica esta compatibilidad.

En cotizaciones, **Elegir cliente** cargaba el maestro correctamente, pero no marcaba la selección como cambio pendiente y no mostraba error de red. Ahora la selección marca el formulario y el fallo de recuperación notifica al usuario; el contrato se protege con `tests/ui-actions-contract.php`.

En la UI de cotización se abrió el Choose From List de clientes, se eligió un resultado y se verificó que cargó el cliente y sus direcciones, quedó marcado `MODIFICADO` y el control Nuevo pidió confirmación de descarte. La prueba no guardó ni modificó datos del cliente.

## Cobertura pendiente

La suite de humo cubre renderizados y ciclos de backend, pero no equivale a hacer clic en cada botón visible. La extracción estática tampoco incluye todos los botones dinámicos, iconos sin texto ni controles condicionales. Por tanto, la revisión integral por vista —incluidos permisos, carga, errores, confirmaciones y estados inválidos— permanece abierta. Se deben completar pruebas interactivas con datos de prueba aislados y añadir contratos para cada defecto encontrado antes de cerrar la prioridad 0.

## Hallazgos de integridad y cambios de la fase actual

- La tabla de orden de compra enviaba el número de línea como posición `0`, aunque el servidor interpreta esa posición como ID del artículo. Ahora el identificador real viaja en una columna técnica oculta y el servidor lo normaliza y valida contra el maestro antes de persistirlo.
- La consulta de detalle de órdenes ahora devuelve el ID real de artículo, necesario para recuperar/editar sin cambiar el producto por la posición visual de la fila.
- Elegir una solicitud aprobada en la orden de compra ahora carga sus artículos, cantidades, precios estimados, unidades, fechas, moneda, solicitante y observación. Si ya hay líneas, solicita confirmación para reemplazarlas. El servidor sigue validando estado, conjunto de artículos y cantidades.
- La copia se marca explícitamente como cambio no guardado; navegar a otro documento debe pedir confirmación aunque los campos se hayan rellenado programáticamente.
- `purchase-lifecycle.php` verifica normalización de la fila de UI, referencia de producto y rechazo de un artículo distinto; `purchase-copy-contract.php` protege los enlaces entre selector, API, vista y controlador.
- En navegador se seleccionó una solicitud aprobada disponible y se comprobó que se copiaron sus dos líneas (artículo, cantidad, precio y unidad), datos de cabecera y totales. Después se eligió un proveedor en su Select2: cargaron su RUC y dirección, las líneas se conservaron y el formulario siguió `MODIFICADO`; al pulsar Nuevo, la confirmación de descarte apareció y limpió el formulario. No se guardó una orden durante esta prueba.
- La creación/guardado final desde el formulario, los cambios de proveedor y la edición/anulación continúan pendientes de prueba aislada.
