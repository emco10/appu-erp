# Estado de implementación del ERP APPU

Fecha de actualización: 2026-10-29

## Cierre operativo en verificación

Se consolidaron los ciclos de la arquitectura en la misma carpeta utilizada por Apache: `C:\Users\Erick Cieza\Desktop\PROYECTOS DE SISTEMAS\APPU ERP`. `C:\xampp\htdocs\APPU ERP` es una unión de directorio hacia esa carpeta, no una segunda copia.

La base operativa recibió las migraciones aditivas pendientes de metadatos y protección de operaciones, con respaldo SQL previo. La comparación posterior conservó conteos de documentos/maestros, stock, reservas, valor de inventario y saldos de CxC/CxP. `tests/schema-readiness.php` verifica los prerrequisitos sin modificar datos.

Evidencia incorporada el 29-10-2026:

- Navegación: 42 enlaces reales del menú renderizan completos por HTTP; 15 tipos de historial responden. La barra reconoce rutas limpias de Apache y diferencia factura, boleta, NC y OC; Nuevo elimina el contexto del registro anterior. Los módulos se despliegan por clic.
- Maestros: Clientes, Proveedores, Empleados y Usuarios comparten búsqueda modal, consulta, edición, cancelación y navegación por los datos de DataTables, incluidas filas de otras páginas. Se rechazan respuestas tardías y se limpian UDF/direcciones/contactos al cambiar ficha.
- Compras: ambos circuitos estándar/reserva están cubiertos por HTTP. La factura conserva las recepciones físicas anteriores de la OC y no habilita otra entrada de la misma mercancía. Se validan cantidades, descuentos, totales, fechas y dependencias de anulación.
- Inventario/Finanzas: entradas, salidas y transferencias conservan cantidad/lotes/valor; pagos controlan precisión monetaria, fechas, socio y moneda. Cobros/pagos abren su consulta desde el mapa.
- Gestión: crear y editar perfiles, permisos, listas/opciones, UDF, series, cajas/bancos y almacenes tiene evidencia HTTP. El refresco de presencia ya no reemplaza formularios en edición.
- Seguridad: los permisos se refrescan antes de autorizar cada petición. Pruebas con inicio de sesión real verifican CSRF, alta/retiro de permisos y corte de acceso del usuario desactivado.
- Trazabilidad: el mapa común incorpora ventas, compras, recepción, costos y pagos; normaliza tipos legados y filtra nodos según permiso.
- Costos de entrega: asignación en centavos con suma exacta y valores no negativos, incluso cuando el importe es menor al número de líneas; reversión y fechas válidas comprobadas.

El ejecutor `tests/run-all-qa.ps1` integra dominio, renderizado, controladores DOM, flujos HTTP y sesiones reales. La certificación automatizada y la aprobación visual se registran por separado; esta última no está acreditada para cada control/dispositivo. SUNAT permanece fuera del alcance.

La matriz siguiente describe componentes y ciclos del alcance anterior. “Implementado” no acredita el cumplimiento completo del nuevo prompt de reestructuración. Para el estado actual de la migración, sus brechas y evidencia, consultar `initial-audit-v2.md`, `architecture-v2.md`, `migration-plan.md` y `ui-action-audit.md`. El 27-10-2026 se verificó el primer flujo físico Orden → Entregas → Facturas con relaciones por línea, reversión y concurrencia; la navegación global y los restantes procesos siguen en migración.

La arquitectura APPUCORP es la referencia funcional. SAP Business One guía la organización de maestros, documentos, estados y trazabilidad. La integración real con SUNAT permanece en una fase futura.

## Matriz de cumplimiento

| Módulo | Requisito | Estado | Evidencia | Brecha | Prioridad | Acción y verificación |
|---|---|---|---|---|---|---|
| Plataforma | Web adaptable | Parcial | Viewport, Bootstrap/AdminLTE, reglas APPU móviles, fixture aislado y `responsive-layout-contract.php` | Celular corregido y revisado; falta cerrar la revisión visual reproducible en tableta y escritorio | Media | Completar matriz visual de vistas críticas en 768 px y 1440 px |
| Administración | Usuarios, perfiles y permisos | Implementado | Roles/permisos, autorización por endpoint, estados y protección del último administrador | Falta validación visual por cada perfil real | Alta | Validar UX final con perfiles del usuario |
| Administración | Empleados y usuarios conectados | Implementado | Alta, consulta, edición, navegación, actividad, desactivación segura e identidad única empleado/usuario | Falta validación visual con operación real | Alta | Validar UX final con datos del usuario |
| Gestión | Empresa, series, listas y campos de usuario | Implementado | Configuración unificada, perfiles/permisos, listas, campos por entidad, series y cuentas financieras | Falta validación visual con operación real | Alta | Validar UX final con datos del usuario |
| Gestión | Almacenes | Implementado | Alta, edición, métricas de uso, activación e inactivación protegida | Falta validación visual con operación real | Media | Validar UX final con datos del usuario |
| Socios de negocio | Clientes | Implementado | Alta, búsqueda, navegación, estado, direcciones/contactos, condiciones comerciales y selección fiscal/logística en documentos | Falta validación visual final con datos reales | Media | Validar UX con operación del usuario |
| Socios de negocio | Proveedores | Implementado | Alta, búsqueda, navegación, bancos, direcciones/contactos, condiciones comerciales y selección de dirección en compras | Falta validación visual final con datos reales | Media | Validar UX con operación del usuario |
| Ventas | Cotización | Implementado | Crear, cálculo autoritativo, recuperar, editar, anular y convertir; bloqueo por documento derivado activo | Falta validación visual final en navegador | Media | Validar UX con operación del usuario |
| Ventas | Orden de venta | Implementado | Conversión, alta directa, confirmación, anulación, estados comercial/logístico y acciones visibles | Falta prueba visual con operación real | Alta | Validar UX final con datos del usuario |
| Ventas | Guía y entregas parciales | Implementado | Saldo por línea, guías múltiples y estado logístico independiente | Falta prueba visual en navegador | Alta | Verificar interfaz con datos reales |
| Ventas | Factura, boleta y NC internas | Implementado | Validación de origen; anulación revierte inventario/CxC; NC parciales controlan importe, cantidades, cobros y reversión | Falta validación visual final en navegador | Media | Validar UX con operación del usuario |
| Ventas | Factura de anticipo | Implementado | Subtipo de anticipo por porcentaje, CxC, cobro, aplicación automática a factura final, reversión y trazabilidad | Falta validación visual final con operación real | Media | Validar UX con datos del usuario |
| Ventas | Factura de reserva | Implementado | Subtipo documental, facturación sin salida inmediata, CxC, guías parciales con descarga FEFO y reversión transaccional | Falta validación visual final con operación real | Media | Validar UX con datos del usuario |
| Compras | Solicitud y aprobación | Implementado | Crear, abrir, editar pendientes, consultar cerradas, aprobar/rechazar, auditar y convertir con detalle validado | Falta validación visual final | Media | Validar UX con datos reales |
| Compras | Orden de compra | Implementado | Cabecera/detalle vinculados al artículo, aprobación, copia desde solicitud y trazabilidad; anular la última orden activa devuelve la solicitud a Aprobada con historial | Faltan recorridos visuales de edición y anulación por control | Alta | Continuar validando UX por documento en QA |
| Compras | Factura de proveedor | Implementado | Validación integral contra orden y generación de CxP | Falta prueba visual completa | Alta | Validar UX con datos reales |
| Compras | Factura de reserva de proveedor | Implementado | Subtipos estándar/reserva, reconocimiento diferenciado de CxP, recepción logística y reversión protegida | Falta validación visual final con operación real | Media | Validar UX con datos del usuario |
| Compras | Entrada de mercancías | Implementado | Módulo independiente ubicado entre la orden y la factura; admite como base una orden de compra o una factura de reserva/proveedor, copia cabecera y líneas, conserva instantáneas históricas, controla cantidades pendientes, almacén, lotes, valorización, anulación y recálculo | Falta prueba visual completa con datos reales | Alta | Validar ambos flujos desde el navegador |
| Compras | Costos de entrega | Implementado | Documento cabecera/detalle vinculado a recepción, distribución por valor o cantidad, valorización y reversión protegida | Falta validación visual final con operación real | Media | Validar UX con datos del usuario |
| Inventario | Artículos | Implementado | Maestro, búsqueda, navegación, selección documental y prueba de ciclo de vida | Falta prueba visual final en navegador | Media | Validar UX con operación del usuario |
| Inventario | Entradas y salidas de mercancías | Implementado | Módulos separados, documentos cabecera/detalle, múltiples líneas, numeración, costo promedio, FEFO, consulta y reversión protegida | Falta validación visual con operación real | Alta | Validar UX y continuar normalización de transferencias |
| Inventario | Stock y movimientos por almacén | Implementado | Servicio transaccional común, documentos operativos y alertas de conciliación | Falta validación visual con datos reales | Alta | Revisar alertas operativas periódicamente |
| Inventario | Lotes y vencimientos | Implementado | Lotes, FEFO, reservas, reversión, transferencias y reporte de vencimientos reales | Falta validación visual con datos reales | Media | Probar operación diaria con lotes reales |
| Finanzas | CxC, CxP, cobros y pagos | Implementado | Cuentas y pagos cabecera/detalle; aplicaciones parciales, totales y múltiples; reversión transaccional | Falta validación visual con operación real | Alta | Validar UX final con datos del usuario |
| Trazabilidad | Flujo e historial | Implementado | Conversiones, entregas, recepciones y pagos enlazados; mapa navegable y apertura directa del registro | Falta únicamente validación visual con operación real | Alta | Validar UX final con datos del usuario |
| Reportes | Ventas, compras, inventario y finanzas | Implementado | Vistas/endpoints y regresión JSON de finanzas, inventario y flujo documental | Falta validación visual final de filtros/exportación | Media | Validar presentación con datos reales |
| Integraciones | Externas no SUNAT | Parcial | Tablas y Gestión | Conectores reales pendientes | Baja | Implementar solo por necesidad aprobada |
| SUNAT | Facturación electrónica | Fase futura | Backlog | Fuera del alcance actual | Postergada | No implementar todavía |

## Plan por fases

1. Estabilizar navegación, render, modos Buscar/Crear/Editar, recuperación de maestros y errores PHP/SQL.
2. Completar ciclos de socios, artículos, ventas, compras, inventario, CxC/CxP y pagos.
3. Unificar formularios, estados, navegación documental, cambios sin guardar y trazabilidad.
4. Consolidar pruebas, integridad, rendimiento, seguridad y documentación.
5. Mantener SUNAT en el backlog hasta autorización expresa.

La auditoría de acciones de interfaz está en curso; su inventario y límites de cobertura se documentan en [ui-actions-audit.md](./ui-actions-audit.md). No se considera cerrada la prioridad 0 hasta probar los controles pendientes por vista.

En proveedores se corrigieron el HTTP 500 de la búsqueda y la posición de navegación tras seleccionar un resultado; los contratos automatizados y la selección en navegador quedaron verificados. La prueba de desactivación también restablece correctamente la etiqueta “Guardar proveedor”.

La revisión ampliada de documentos está en curso y se registra en [ui-actions-audit.md](./ui-actions-audit.md). El inventario confirma ciclos de backend existentes para varias rutas de ventas, compras, inventario y finanzas, pero faltan pruebas UI por documento, una cobertura completa de botones y varios ciclos dedicados (entre ellos boleta, orden de servicio y transferencia de inventario). También se identificó que el sistema aún no tiene una capacidad común de Choose From List/F4 ni búsquedas formateadas; las búsquedas actuales son listas Select2/autocompletes específicas. No se considera equivalente a lo que se solicita hasta acordar e implementar las salvaguardas de búsqueda parametrizada y autorización.

En órdenes de compra se corrigió la pérdida del ID real de artículo causada por enviar el número de fila como identificador, y ahora se copian las líneas de una solicitud aprobada seleccionada. La normalización y las reglas de conversión pasan `purchase-lifecycle.php`; el contrato de UI/API pasa `purchase-copy-contract.php`. En QA se verificó desde el navegador la selección y aprobación de la solicitud, la copia de sus líneas, el guardado y la recuperación posterior de la orden.

La verificación del ciclo detectó que anular una orden dejaba su solicitud origen en estado `Convertida`, sin posibilidad de volver a convertirla. Ahora, al anular la última orden activa vinculada, la solicitud vuelve a `Aprobada` y registra el cambio en el historial; si queda otra orden activa, no se reabre. `purchase-order-cancellation-lifecycle.php` cubre ambos casos. La automatización de navegador no pudo completar el botón Anular porque el navegador integrado no soporta el `window.prompt()` del motivo; la ruta backend sí se probó.

En cotización se probó el Choose From List de clientes y se corrigió que completar el socio/direcciones desde esa lista no marcaba la cotización como modificada. La suite completa volvió a pasar con los contratos de compra, selección de cliente y cambios sin guardar.

## Avance de la fase 1

- Conexión de base de datos disponible durante el render autenticado; evita páginas truncadas antes de cargar sus scripts.
- Selectores del menú superior ajustados para no eliminar contenedores completos por enlaces internos.
- Búsqueda de clientes corregida para parámetros preparados compatibles con MySQL.
- Consulta de antigüedad financiera corregida mediante una tabla derivada.
- Prueba de humo añadida para asegurar cierre HTML y carga del script específico de cada vista.
- Los submenús mantienen abierto el módulo de la vista activa y cierran solo grupos ajenos; antes, un temporizador cerraba todos los grupos después de 750 ms.
- Se eliminó un selector obsoleto de órdenes de venta que apuntaba a un identificador inexistente del menú.
- Los accesos de creación de la barra superior exigen permiso de gestión; las consultas continúan disponibles con permiso de lectura.
- Se retiró un acceso de nota de crédito duplicado y se condicionó el acceso a Inicio al permiso de consulta del panel.
- El botón Nuevo del resumen de órdenes de venta ahora abre el formulario/modal de creación en lugar de recargar el propio resumen.
- El contrato de navegación ahora detecta selectores de menú sin correspondencia en el lateral, verifica permisos de creación, conserva el módulo activo y valida el destino de nueva orden.
- Crear, Buscar y Navegar son botones desplegables sin destinos `javascript:void(0)`; se evita el doble toggle entre Bootstrap y el controlador APPU, y Navegar se muestra desde 576 px.
- El saludo de Inicio ahora mantiene contraste legible en tema claro; se añadió un estilo específico con mayor prioridad que la regla global de encabezados y una comprobación al renderizar la portada.
- La suite `tests/run-smoke.cmd` volvió a ejecutarse después de estos cambios: renderizados, permisos, ciclos de ventas/compras, inventario, finanzas, trazabilidad y diseño adaptable — aprobados.

## Evidencia

- Render completo de Clientes, Empleados, Usuarios, Proveedores y Factura: aprobado.
- Recuperación del cliente guardado por detalle y búsqueda: aprobada.
- Reporte financiero contra MySQL y estructura JSON: aprobado.
- Navegación: todos los destinos declarados por el menú lateral y la barra superior renderizan su vista y script correspondiente.
- Clientes: crear, buscar y editar contra MySQL con limpieza del registro temporal — aprobado.
- Separación de permisos de consulta/gestión y acceso administrativo — aprobado.
- Proveedores: crear, buscar y editar contra MySQL con limpieza del registro temporal — aprobado.
- Artículos: crear, buscar, editar e iniciar con stock cero — aprobado.
- Entregas de orden: Pendiente → Parcial → Completa y retorno a Parcial al anular — aprobado.
- Facturación desde orden: cliente, almacén, moneda, artículos, cantidades, precios y totales validados — aprobado.
- Cuenta por cobrar: generación automática, saldo y fecha de vencimiento — aprobado.
- Anulación del documento de venta: reapertura de orden y restauración de reserva — aprobado.
- Compras: orden aprobada → entrada parcial/completa → factura de proveedor, y orden → factura de reserva → entrada; saldos, estados y reversión — aprobado por pruebas de dominio.
- Control de duplicidad de referencia de factura por proveedor — implementado.
- Inventario: ingreso → reserva → bloqueo de sobreconsumo → salida FEFO → reversión y conciliación — aprobado.
- QA local: `db_appu_qa` se creó desde el esquema de `db_appu` sin copiar datos operativos; `tests/run-smoke-qa.cmd` ejecuta la suite únicamente en esa base.
- Solicitud de compra en navegador QA: artículo del maestro visible, cantidad decimal rechazada, cantidad entera guardada y solicitud aprobada — aprobado.
- Orden de compra en navegador QA: proveedor seleccionado, solicitud copiada, orden guardada y vuelta a abrir con línea/cantidad íntegra — aprobado.
- Suite completa en QA, incluida la regresión de anulación/reapertura del origen — aprobada.
- Reporte de inventario y alertas de integridad contra MySQL — aprobado.
- Finanzas: cobro múltiple parcial/total, pago parcial a proveedor y reversión idempotente de ambos — aprobado.
- Trazabilidad: cada aplicación de cobro/pago enlaza el documento origen con su pago y conserva el importe aplicado — aprobado.
- Mapa documental: navegación entre nodos y apertura directa de cotizaciones, órdenes, ventas y compras — aprobado por contrato de rutas.
- Gestión: integridad de empresa, perfiles, permisos, listas, cuentas y series — aprobada.
- Campos de usuario: persistencia y validación de lista, número y fecha contra MySQL — aprobada.
- Almacenes: creación, edición, desactivación vacía y bloqueos por stock/reserva o último almacén activo — aprobados.
- Administración: identidad única empleado/usuario, recuperación del estado y protecciones de desactivación — aprobadas.
- Clientes y proveedores: búsqueda de activos/inactivos, recuperación del estado y navegación posicional — aprobadas.
- Socios de negocio: direcciones y contactos múltiples, registro predeterminado y sincronización de domicilio fiscal — aprobados.
- Condiciones comerciales: moneda, días de pago, límite de crédito, responsable y listas de precios configurables desde Gestión — aprobadas.
- Cotización, orden de venta y orden de compra reciben automáticamente los valores predeterminados del socio de negocio — implementado.
- Sintaxis PHP: 257 archivos propios, 0 errores en la última validación global.
- Prueba adaptable automatizada: viewport, precedencia de estilos, barras móviles, cabeceras y KPI — aprobada.
- Revisión visual a 390 px de Inicio, Cotización y Gestión — ejecutada; los defectos de ancho detectados fueron corregidos. La matriz visual de tableta/escritorio permanece pendiente.

## Avance de la fase comercial

- La orden solo puede facturarse una vez mientras exista una factura o boleta activa.
- Un documento anulado deja de bloquear una nueva facturación de la orden.
- La facturación actual es total, coherente con el cierre de la orden; las entregas físicas sí admiten parcialidad mediante varias guías.
- Los datos maestros de la orden prevalecen sobre textos alterados en el navegador al crear factura o boleta.
- Las acciones principales de la orden están visibles en la tabla y ya no dependen exclusivamente del clic derecho.

## Avance de la fase financiera

- Cuentas por cobrar y cuentas por pagar conservan separación funcional y contable.
- Los cobros y pagos usan cabecera y detalle, siguiendo el patrón documental de SAP Business One.
- Una cabecera puede aplicar importes a varios documentos del mismo socio de negocio y moneda.
- Se validan cuentas cerradas o anuladas, importes superiores al saldo, duplicidad de cuenta y moneda de la cuenta financiera.
- La reversión anula el pago sin eliminarlo y recompone los saldos y estados de cada documento afectado.

## Avance de la fase de direcciones documentales

- Cotización y orden de venta distinguen domicilio fiscal y dirección de entrega del cliente.
- Factura y boleta heredan referencias y fotografías históricas de ambas direcciones desde la orden o cotización de origen.
- La guía de remisión utiliza la dirección de entrega conservada por el documento comercial, sin depender del estado actual del maestro.
- Orden y factura de proveedor conservan la referencia y fotografía histórica de la dirección; la compra vinculada hereda la registrada en su orden.
- Las referencias se validan contra el socio de negocio y solo aceptan direcciones activas.
- Migraciones aplicadas: `20261025_add_document_partner_addresses.sql` y `20261025_add_purchase_partner_addresses.sql`.
- Regresión: suite de humo completa aprobada y sintaxis de 208 archivos PHP validada sin errores.

## Avance de robustez documental

- Orden de compra y factura de proveedor ofrecen selección explícita de las direcciones activas del proveedor y muestran la fotografía que quedará en el documento.
- Al consultar una orden o compra se recuperan tanto la referencia de dirección como su texto histórico.
- La selección del origen de una nota de crédito usa una clave inequívoca para factura o boleta, evitando colisiones cuando ambos documentos tienen el mismo identificador interno.
- El servidor determina el tipo de origen por la clave seleccionada, no por la serie editable de la nota de crédito.
- La nota de crédito valida cliente, almacén, moneda, artículos, precios, cantidades previamente acreditadas e importe pendiente de acreditar.
- No se permite acreditar importes ya cobrados sin reversar primero los cobros relacionados.
- Al anular una nota de crédito se recalcula la cuenta por cobrar desde el documento original y todas las notas activas; no se eliminan movimientos históricos.
- Prueba `credit-note-lifecycle.php`: acumulación de notas parciales y restauración del saldo al anular — aprobada.

## Avance de consistencia ERP

- Los formularios transaccionales detectan cambios realizados por el usuario y muestran el modo `MODIFICADO`.
- Salir por el menú, crear otro registro, cargar otro registro o navegar con las flechas solicita confirmación antes de descartar cambios.
- El aviso nativo del navegador protege también recarga, Atrás, Adelante y cierre de pestaña.
- El estado modificado solo se limpia después de una respuesta de persistencia exitosa o de una confirmación expresa de descarte.
- Cotización recalcula subtotal, IGV, descuento y total en el servidor; no confía en importes ni identidad del cliente enviados por el navegador.
- Una cotización con orden de venta activa queda bloqueada para edición o anulación.
- Al anular la orden derivada puede crearse una nueva orden desde la misma cotización, manteniendo el historial; se sustituyó la unicidad física incorrecta por un índice de consulta.
- Pruebas `unsaved-changes-contract.php`, `quotation-lifecycle.php` y `quotation-conversion-lifecycle.php` — aprobadas.

## Avance de solicitudes y aprobaciones

- Las solicitudes registradas se pueden abrir desde el listado; las pendientes quedan en modo edición y las aprobadas, rechazadas, convertidas o canceladas en modo consulta.
- La recuperación incluye cabecera, líneas y campos de usuario, evitando borrar información al editar.
- Fechas, moneda, empleado activo, artículos, duplicidades, cantidades y precios estimados se validan en servidor; código, nombre y descripción se toman del maestro de artículos.
- Solo una solicitud aprobada puede convertirse en orden de compra.
- La orden debe conservar exactamente los artículos y cantidades aprobados; los precios de negociación permanecen propios de la orden.
- La referencia a la solicitud de origen no puede cambiarse ni retirarse al editar una orden existente.
- Se eliminó una comprobación incorrecta que podía asociar recepciones ajenas a una orden por coincidencia de identificadores.
- Autorización por permiso y protección CSRF verificadas para consulta, gestión y aprobación.
- Prueba real de aprobación/rechazo y ambos historiales de auditoría — aprobada.

## Avance de interfaz adaptable

- La identidad visual APPU ahora se carga después de AdminLTE para que sus colores, contraste y ajustes de componente tengan precedencia estable.
- La barra documental usa una cuadrícula de cuatro columnas en pantallas pequeñas; todas las acciones quedan accesibles sin extender el lienzo horizontal.
- Las cabeceras documentales con alturas históricas fijas pasan a altura automática en móvil y sus columnas pueden contraerse sin recortar controles.
- Los grupos KPI del panel se apilan a una columna por debajo de 576 px, evitando textos e importes truncados.
- Contenedores, tarjetas, grupos de entrada y cabeceras flexibles tienen límites explícitos para no provocar desbordamiento lateral.
- Se añadió un fixture visual permitido solo bajo `cli-server` y `APPU_RESPONSIVE_TEST=1`; Apache responde 404 y no puede usarse para eludir autenticación.
- `responsive-layout-contract.php` quedó integrado a la suite de humo para proteger estas invariantes.
- Regresión completa y sintaxis de 257 archivos PHP — aprobadas.

## Avance de factura de reserva de cliente

- La factura permite distinguir entre modalidad estándar y de reserva; la modalidad de reserva exige una orden de venta confirmada.
- Al contabilizar la reserva se genera la cuenta por cobrar y se atiende comercialmente la orden, pero no se descarga el inventario ni se libera prematuramente su reserva.
- Cada guía parcial originada en la factura libera solo la cantidad reservada que entrega, descarga stock y lotes mediante FEFO y actualiza el estado logístico de la orden.
- La anulación de una guía restituye exactamente stock, lote y reserva. Tras anular todas las guías, la anulación de la factura reabre la orden sin duplicar existencias.
- Migración aplicada: `20261025_add_sales_invoice_subtypes.sql`.
- Prueba `reserve-invoice-lifecycle.php`: creación sin descarga, entregas 4 + 6, cumplimiento parcial/completo y reversión integral — aprobada.

## Avance de factura de anticipo de cliente

- La factura permite seleccionar el subtipo Anticipo y definir un porcentaje de la orden de venta confirmada.
- Se admiten varios anticipos activos, con control transaccional para que su suma no exceda el 100 % de la orden.
- El anticipo genera su propia cuenta por cobrar, permanece vinculado a la orden y no descarga stock, lotes ni reservas.
- Al crear la factura final se aplican automáticamente solo los anticipos efectivamente cobrados del mismo cliente, orden y moneda; la CxC conserva el saldo neto pendiente.
- La relación anticipo → factura final queda registrada en cabecera/detalle de aplicación y en el mapa documental.
- No se permite reversar el cobro de un anticipo aplicado ni anularlo hasta liberar primero la factura final. La anulación de la factura final libera las aplicaciones sin duplicar importes.
- Las notas de crédito recalculan el saldo considerando también los anticipos aplicados.
- Migración aplicada: `20261025_add_sales_advance_invoices.sql`.
- Prueba `sales-advance-lifecycle.php`: múltiples anticipos, límite del 100 %, cobro, aplicación, saldo neto y reversión — aprobada.

## Avance de factura de reserva de proveedor

- El formulario de factura de proveedor distingue explícitamente factura estándar y factura de reserva.
- La factura de reserva reconoce la cuenta por pagar al aprobarse y conserva la entrada de mercancías como documento logístico independiente.
- La factura estándar reconoce la cuenta por pagar cuando la recepción se completa; una recepción parcial todavía no genera deuda exigible en este flujo.
- Al anular una recepción de factura estándar, la CxP se revierte. La operación se bloquea si existen pagos aplicados para proteger la conciliación financiera.
- Listados y consultas recuperan y muestran el subtipo documental.
- Migración aplicada: `20261025_add_purchase_invoice_subtypes.sql`.
- Prueba `purchase-reserve-invoice-lifecycle.php`: reconocimiento diferenciado de CxP estándar/reserva — aprobada.

## Avance de costos de entrega y valorización

- Se incorporó el documento Costo de entrega con numeración propia, cabecera y detalle vinculados a una recepción de mercancías.
- El gasto adicional puede distribuirse proporcionalmente por valor base o cantidad recibida, sin modificar las existencias físicas.
- El inventario conserva valor acumulado y costo promedio por artículo y almacén; cada ajuste deja un movimiento valorizado auditable.
- El mapa documental de compras muestra los costos de entrega relacionados y permite abrir el registro.
- La anulación restaura la valoración original y se bloquea cuando existen salidas posteriores, evitando recalcular historia consumida de forma insegura.
- La anulación de una recepción también se bloquea mientras conserve costos de entrega activos.
- Migración aplicada: `20261025_create_landed_costs.sql`.
- Prueba `landed-cost-lifecycle.php`: distribución, costo promedio, protección por salida posterior y reversión exacta — aprobada.

## Avance de estructura del módulo Almacén

- Se eliminó del menú el concepto operativo “Actualizar stock” y se reemplazó por módulos separados: `Entrada de mercancías - Inventario` y `Salida de mercancías - Inventario`.
- Cada módulo usa documentos numerados con cabecera, múltiples líneas, almacén, fecha, referencia, motivo, comentarios, cantidades y estado.
- La entrada registra costo unitario, valor de inventario, lote y vencimiento; actualiza el costo promedio por artículo y almacén.
- La salida protege existencias reservadas, valora al costo promedio y asigna lotes automáticamente mediante FEFO.
- Ambos documentos se pueden buscar, consultar y anular; la reversión restaura cantidades, lotes y valor, y la entrada se protege frente a consumos posteriores.
- `Entrada de mercancías` quedó como documento principal de Compras, después de Órdenes y antes de Facturas de proveedor. Puede copiar desde una orden de compra vigente o desde una factura de reserva/proveedor, manteniendo relación por línea y evitando duplicar la ruta cuando la orden ya tiene una factura activa.
- La recepción registra proveedor, dirección, moneda, artículo, unidad y costo como instantáneas; así los cambios posteriores en los maestros no alteran la historia documental.
- El menú Crear también distingue las tres operaciones para evitar confundir una recepción de proveedor con un movimiento interno.
- Migración aplicada: `20261027_create_inventory_documents.sql`.
- Prueba `inventory-document-lifecycle.php`: entrada, salida, valoración, sobreconsumo, dependencia y reversión — aprobada.
