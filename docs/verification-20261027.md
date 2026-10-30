# Verificación del flujo de entregas y facturación

Fecha: 27-10-2026, America/Lima. Entorno: `db_appu_qa`, servidor HTTP de loopback y fixtures sintéticos. Las migraciones nuevas están aplicadas en QA; no se aplicaron a la base operativa.

## Proceso comprobado

1. Crear orden de 10 unidades con stock físico 10.
2. Confirmar: físico 10, comprometido 10.
3. Entregar 6: físico 4, comprometido 4, pendiente de entrega 4.
4. Reintentar la misma petición: devuelve la misma entrega, sin otra salida. Reutilizar la clave con otros datos devuelve conflicto.
5. Intentar entregar 5: rechazo con “Solo quedan 4 unidades pendientes”.
6. Facturar las 6 entregadas: CxC 70.80, sin otra salida de stock. Un reintento devuelve la misma factura.
7. Intentar anular la entrega facturada: rechazo hasta anular la factura.
8. Entregar las 4 restantes y facturarlas: físico y compromiso 0, facturación total 118.00. Orden cerrada por cumplimiento y atendida comercialmente.
9. Consultar el mapa: orden, dos entregas y dos facturas conectadas; aplicaciones por línea con números de documento y línea.
10. Anular las facturas: reabre el saldo facturable sin modificar stock. Anular las entregas: restaura físico 10, comprometido 10 y valorización 50.00. Anular la orden libera el compromiso y conserva físico 10.
11. Provocar un error de UDF después de registrar movimientos: rollback de cabecera, líneas, movimientos, lotes, reservas, aplicaciones y numeración.

También se probó facturar parcialmente una entrega y combinar saldos de dos entregas en otra factura, sin duplicar movimiento ni obligación. La factura directa legada rechaza una orden que ya tiene entregas físicas.

Con las entregas anuladas, se probó además facturar y emitir boleta directamente contra una orden cuyo stock exacto estaba comprometido por ella misma. Ambos documentos liberan primero ese compromiso en la misma transacción, registran su salida y las aplicaciones por línea, y restauran correctamente saldo/compromiso al anularse. La prueba de boleta verifica también su cuenta por cobrar.

La auditoría detectó que PHP CLI/API usaba `Europe/Berlin` mientras las vistas y MySQL trabajaban en Lima. La configuración central establece `America/Lima` y la conexión PDO usa la zona de sesión `-05:00`, evitando fechas de documento distintas entre UI, API y SQL.

## Evidencia reproducible

| Prueba | Resultado | Alcance |
|---|---|---|
| `tests/run-smoke-qa.cmd` | PASS | Suite legada, nuevo render del mapa, orden, reglas/UDF, motor por línea, reserva, concurrencia y totales |
| `tests/document-engine-lifecycle.php` | PASS | 10→6→rechazo de 5→4; copia parcial/múltiple a factura, CxC, dependencias y reversión |
| `tests/sales-fulfillment-http.php` | PASS | Proceso anterior por endpoints reales, CSRF, reintentos, conflicto, mapa/consulta y rollback tardío |
| `tests/document-concurrency.php` | PASS | Dos procesos PDO: una entrega 6 y otra intenta 5 con un snapshot anterior; solo se confirma la primera y queda saldo 4 |
| `tests/document-totals.php` | PASS | Dos parciales de artículos a 0.03 no cobran un centavo adicional; impuesto preservado y ajuste final explícito; rechaza descuadres excesivos |
| `tests/sales-order-http.php` | PASS | Regresión de administración/UDF obligatorio, búsqueda, consulta, edición y conflicto |
| Pruebas Node de orden/entrega/factura/mapa/artículos | PASS, 19 casos | Estados, limpieza, respuestas obsoletas, errores, foco, conservación de datos, reintento, permisos y navegación/paginación |
| `tests/product-master-http.php` | PASS | Alta/reintento, versión concurrente, atributos/UDF, valores ocultos y borrado, búsqueda/páginas de 25 y 2, estado y URL anterior; stock sin cambios por edición |

Los controladores DOM usan simulaciones de Select2, DataTables y modal; no acreditan el aspecto visual ni todos los comportamientos de los plugins reales. La revisión visual sigue pendiente desde la interrupción de Computer Use al verificar la URL del navegador.

La consulta posterior confirmó cero productos/órdenes/claves de petición de los nuevos fixtures, y cero aplicaciones huérfanas con destino orden o factura en QA. El inventario regenerado contiene 225 controles de vistas y 62 patrones de botones JavaScript; los no ejecutados individualmente siguen pendientes. También pasó la comprobación de sintaxis de los archivos PHP/JavaScript afectados.

## Diseño verificado y límites

- `inventory_posted` diferencia las entregas físicas de las guías históricas/traslados sin otra salida. El histórico se conserva; no se reconstruyen salidas sin evidencia.
- `inventory_origin=DELIVERY` impide descontar o devolver stock por la factura que solo reconoce la obligación comercial.
- `tbl_document_line_application` mantiene saldos por etapa y reversión auditable. `tbl_warehouse_movement.document_line_id` conserva la línea responsable del movimiento.
- El bloqueo de la orden raíz y las lecturas actuales de aplicaciones evitan utilizar un saldo anterior bajo concurrencia.
- Las claves de petición se guardan en la misma transacción del documento. Los caminos antiguos sin clave conservan compatibilidad; la UI nueva sí la envía.
- La factura de entrega es interna y devuelve estado fiscal `NO_APLICA`; no se implementó SUNAT.

Esta etapa no completa el prompt maestro. Siguen pendientes la auditoría/estabilización de todas las pantallas, homogeneización de oferta/factura/boleta legadas, campos nativos y almacén por línea, unidades/conversiones, Ordered, UDF de línea/tipos restantes, preferencias de formulario, adjuntos, documentos/correctivos pendientes y las demás puertas del plan de migración.

## P0 de artículos

La ficha de artículos ahora consulta en VIEW y requiere Editar para habilitar cambios. Cancelar restaura el snapshot y Nuevo limpia identidad, controles y UDF. Guardar y consultar usa directamente el snapshot confirmado; Guardar y nuevo abre una ficha independiente. El controlador conserva los datos tras errores de campo o red y rechaza respuestas antiguas, incluso si una navegación está esperando otra página cuando se pulsa Nuevo.

La búsqueda parametrizada cubre código, nombre, marca, barras, estado y UDF autorizados. El ciclo HTTP prueba 27 artículos sintéticos en páginas distintas de 25 y 2, y escapa comodines SQL literales. La escritura usa la tabla de peticiones existente para un reintento sin segunda alta y una versión bajo bloqueo para no sobrescribir otra edición. La versión excluye stock; la prueba cambia el saldo entre lectura y edición, verifica que la edición sigue siendo válida y no altera ese saldo. Se comprueba también el borrado de UDF visibles y la conservación de valores ocultos.

Se retiró la vista/controlador duplicada de edición; su URL sirve la ficha común. El listado abre esa consulta y escapa los valores al renderizarlos. Clientes, proveedores, empleados y usuarios siguen pendientes de migración al ciclo común. No se ha aplicado DDL adicional ni se ha modificado la base operativa.
# Verificación adicional: Entrada de mercancías de Compras

- Menú corregido: la Entrada de mercancías ya no aparece en Ventas y se ubica entre Órdenes y Facturas de proveedor.
- Documento base dual: orden de compra o factura de reserva/proveedor.
- Integridad cabecera/detalle: proveedor, dirección, moneda, artículos, unidades, costos y vínculo de línea quedan persistidos.
- Recepción parcial, recepción total y anulación verificadas con `purchase-goods-receipt-flow.php`.
- Costos de entrega adaptados para recepciones basadas directamente en orden de compra.

## Certificación HTTP adicional

- Maestros de artículos: alta, validación, búsqueda, edición, paginación y concurrencia.
- Ventas: orden, reserva, entrega parcial, facturación, anulaciones y rollback.
- Compras: entrada parcial basada en orden, valorización, bloqueo de exceso y anulación.
- Inventario: traslado entre almacenes con cantidad, lotes y valor contable balanceado.
- Finanzas: CxC/CxP, cobro parcial, pago total, rechazo de exceso y reversión.

Ejecución reproducible: `powershell -ExecutionPolicy Bypass -File tests/run-http-qa.ps1`.
