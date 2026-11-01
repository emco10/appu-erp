# Alineación ERP APPU con SAP Business One

APPU toma de SAP Business One patrones de organización y control, sin copiar módulos que estén fuera del alcance y sin implementar SUNAT en esta etapa.

## Principios aplicados

- **Socio de negocio:** clientes y proveedores siguen siendo maestros operativos separados por compatibilidad; `vw_business_partner` ofrece una vista unificada para reportes y futuras búsquedas.
- **Documentos comerciales:** cada documento debe conservar cabecera, detalle, estado, fechas, usuario y trazabilidad.
- **Abastecimiento:** las solicitudes de compra se separan de las órdenes y compras, dejando un flujo preparado para aprobación y recepción.
- Las órdenes de compra pueden conservar la solicitud de compra origen y el flujo documental registra la conversión.
- **Pagos:** cobros y pagos usan cabecera (`tbl_incoming_payment`, `tbl_outgoing_payment`) y detalle de aplicaciones (`*_detail`).
- Los pagos se pueden aplicar a uno o varios documentos, consultar por operación y anular con reversión de saldos e historial de estados.
- **Flujo documental:** `tbl_document_flow` registra relaciones entre cotización, orden, factura/boleta, compra, recepción, guía y nota de crédito.
- **Inventario:** cantidades por almacén, reservas, artículos configurables por serie/lote/sin seguimiento, vencimientos y movimientos auditables.
- **Despacho:** las guías internas permiten entregas parciales; el sistema calcula el entregado y el saldo pendiente por línea.
- **Búsqueda operativa:** clientes, proveedores y artículos se seleccionan desde maestros mediante listas buscables y autocompletado.
- **Configuración:** series, listas de valores, campos personalizados, perfiles y permisos se gestionan desde Gestión.

## Experiencia documental

La barra de trabajo separa dos acciones terminales: **Anular** y **Cancelar**. En APPU ambas requieren motivo, revierten el impacto de inventario y cierran la cuenta pendiente cuando corresponde; la diferencia se conserva en el historial para distinguir la causa operativa. Si existe una guia de remision activa o un cobro aplicado, primero debe revertirse ese documento relacionado.

- Los formularios incorporan una barra común de nuevo, buscar, navegación entre documentos, guardar, historial y anular/cancelar, siguiendo el patrón operativo de SAP Business One.
- Anular deja el documento inválido y revierte su impacto de inventario cuando corresponde; cancelar lo deja cerrado. Ambos cambios se auditan y no eliminan la cabecera ni el detalle.

## Reportes prioritarios

Nota operativa vigente: tanto Anular como Cancelar son estados terminales y revierten el impacto de inventario; el historial registra cuál acción se ejecutó y por qué.

1. Ventas por socio de negocio, vendedor, artículo, canal y período.
2. Compras por proveedor, artículo, estado y período.

## Flujo de compras y recepción

- Flujo físico primero: orden de compra → entrada de mercancías → factura de proveedor.
- Flujo financiero primero: orden de compra → factura de reserva → entrada de mercancías.
- La entrada no crea por sí misma una cuenta por pagar; incrementa existencias y consume el saldo abierto por línea del documento base.
- Cada entrada conserva su cabecera, detalle, documento base, estado e historial. Las recepciones parciales dejan el origen abierto y la anulación reabre el saldo.
- En el resumen de órdenes, una orden de compra aprobada o parcial ofrece **Copiar a entrada** a usuarios con permiso de inventario; la pantalla carga el origen y sus saldos pendientes.
- Una orden vigente sin factura activa ofrece **Copiar a factura** a usuarios con permiso de compras; el formulario preselecciona la orden y conserva sus validaciones de proveedor, moneda, líneas, importes y duplicidad.
- Estas acciones son atajos a flujos especializados, no conversiones universales: el servidor vuelve a validar el documento origen y los permisos. La prueba automatizada cubre la visibilidad/preselección; queda pendiente la validación interactiva completa de recepción y factura.
3. Cuentas por cobrar/pagar, antigüedad, vencidos y pagos aplicados.
4. Stock por almacén, lote, vencimiento, reservado y disponible.
5. Flujo documental y documentos pendientes por atender.

La integración con SUNAT, OSE, marketplaces y otros servicios queda como etapa posterior; esta capa prepara los datos sin realizar llamadas externas.
