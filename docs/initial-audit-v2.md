# Auditoría inicial de la migración funcional

Fecha: 26-10-2026. El estado se basa en fuentes, esquema QA y pruebas ejecutadas. “Parcial” exige más evidencia; no significa que el requisito completo esté terminado.

Actualización: 27-10-2026, después de verificar el primer flujo de entrega/facturación por línea.

| Área | Función | Existe | Funciona | Arquitectura adecuada | Acción |
|---|---|---|---|---|---|
| Plataforma | PHP/PDO, rutas Apache y plantillas | Sí | Suite de render y permisos PASS | Reutilizable; responsabilidades mezcladas en endpoints | KEEP infraestructura; REFACTOR casos de uso |
| Navegación | Maestros y documentos existentes | Sí | Parcial; suite antigua no ejecuta cada botón | Estados mayormente implícitos y peticiones sin generación | Migrar controladores a modos y protección AJAX comunes |
| Navegación | Orden Crear/Consultar/Editar/Cancelar | Nuevo refactor | PASS servicio, HTTP y cinco casos DOM | Capa compartida de estado/errores; servicio extraído | Revisar visualmente; completar acciones/documentos posteriores |
| Validación | Campos nativos y UDF de orden | Sí | Errores agregados, foco/marcas y conservación PASS | Contrato común; reglas y callbacks de servidor | Migrar demás formularios y endpoints |
| Datos maestros | Socios/contactos/direcciones y artículos | Sí | Ciclos backend PASS; UI parcial | Maestros compartidos reutilizables | Eliminar estado residual; completar flags/UoM/configuración |
| Ventas | Oferta y conversión a orden | Sí | Ciclos backend PASS | Reutilizable; conversión completa especializada | Motor de copia por línea y saldos |
| Ventas | Confirmación y compromiso de orden | Sí | Pruebas de reserva parciales PASS | Servicio stock compartido | Secuencia HTTP completa y política de disponibilidad |
| Logística | Entrega/guía parcial y estado | Sí | 10→6→saldo 4, rechazo de 5, entrega 4, reversión y concurrencia PASS servicio/HTTP | Servicio físico y aplicaciones por línea; histórico preservado | Completar revisión visual y restantes variantes/correctivos |
| Ventas | Factura/boleta estándar | Sí | Factura desde entregas parciales sin segunda salida PASS HTTP; boleta/caminos legados parciales | Inventario separado por origen en la factura migrada | Homologar los formularios/caminos restantes y reglas monetarias generales |
| Ventas | Reserva, anticipo y crédito | Sí | Ciclos backend PASS | Reutilizable con reglas especializadas | Mantener invariantes; integrar motor común y separar devolución física |
| Inventario | Saldo por almacén/ledger/lotes | Sí | Ciclo y reporte con conciliación PASS | Servicio transaccional reutilizable | Integrar por línea, Ordered, políticas y UoM |
| Inventario | Transferencia | Sí | Servicio existente; falta ciclo HTTP aislado completo | Usa movimientos origen/destino | Probar atomicidad, conservación y fallo parcial |
| Compras | Solicitud/pedido/recepción/factura | Sí | Ciclos backend PASS, UI parcial previa | Reutilizable; falta dominio común completo | Conectar Ordered y cantidades por etapa; devolución proveedor |
| Bancos/pagos | CxC/CxP y aplicaciones/reversión | Sí | Ciclos backend PASS | Servicio compartido reutilizable | Conservar al migrar documentos; validar acciones UI |
| Trazabilidad | Relaciones e historial | Sí | Mapa comercial interactivo y relaciones por línea PASS HTTP/DOM | Motor para oferta/orden/entrega/factura; otros documentos parciales | Ampliar cobertura y revisión visual del mapa |
| Extensibilidad | UDF de cabecera de orden configurado en administración | Sí | Ciclo HTTP crear/validar/persistir/consultar/buscar PASS | Metadatos, EAV indexado; sin ALTER por campo | Completar ámbitos/líneas y tipos prioritarios restantes |
| Extensibilidad | Defaults/restricciones/copia UDF | Nuevo refactor | Ciclo transaccional PASS | Motor común; copia optativa y tipos/listas compatibles | Aplicar render común a todas las entidades |
| Configuración | Preferencias por usuario/rol y columnas | Parcial | No acreditado | Sin motor de preferencias transversal | ADD metadatos de formulario/listado |
| Reportes | Consultas operativas | Sí | Smoke JSON PASS | Reutilizable; filtros desiguales | Homologar listados y probar filtros/exportaciones |
| SUNAT | Integración fiscal | Fuera de alcance | No implementar | Frontera externa desacoplada | Mantener documentos internos independientes |

## Evidencia reproducible

- `tests/run-smoke-qa.cmd`: render, permisos y ciclos legados; incluye la nueva orden, reglas comunes y metadatos.
- `tests/sales-order-http.php`: servidor QA local; CSRF, errores múltiples, configuración administrativa de UDF, alta/consulta/edición, búsqueda y conflicto.
- `tests/sales-order-ui.test.cjs`: controladores ejecutados con DOM y jQuery; no es una aprobación visual de navegador.
- `tests/audit-ui-actions.py`: inventario de controles y patrones dinámicos, sin declarar aprobada su ejecución.

La revisión visual del flujo nuevo sigue pendiente por interrupción de Computer Use al verificar la URL. Hay trabajo independiente de código, servicios, HTTP y auditoría que puede continuar. El objetivo completo no está bloqueado ni terminado.
