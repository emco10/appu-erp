# Plan de migración incremental

Fecha: 26-10-2026. Actualización: 27-10-2026. Alcance: el prompt maestro adjunto completo; SUNAT excluida. No sustituir el alcance por los tests que ya pasan.

Actualización 29-10-2026: los cuatro maestros legados ya usan un ciclo común probado por DOM/HTTP; Compras comparte un solo motor de recepción; la sesión refresca permisos en cada petición; Gestión permite crear perfiles y editar valores; el mapa incluye compras/cobros/pagos. La base operativa ya recibió los metadatos y protecciones de operaciones que faltaban, con respaldo y comparación de registros/saldos. Las afirmaciones antiguas de “base operativa sin migrar” describen el corte anterior y se sustituyen por esta evidencia.

## Fases y puertas de salida

| Fase | Trabajo | Evidencia requerida | Situación actual |
|---|---|---|---|
| P0 auditoría | Inventariar vistas/botones/rutas, handlers, endpoints y servicios; reproducir errores | `ui-action-audit.md`, matriz de arquitectura y pruebas por acción | Inventario previo parcial; ampliación en curso |
| P0 navegación | LIST/SEARCH/CREATE/VIEW/EDIT explícitos; limpiar Nuevo; cancelar edición; descartar cambios; invalidar AJAX obsoleto | Pruebas de transición y recuperación, errores de red, permisos y persistencia | Orden, nuevos flujos de entrega/factura y artículos migrados; demás maestros/documentos legados pendientes |
| P0 validación | Reglas reutilizables y errores agregados nativos/UDF; marcar/enfocar sin perder datos | Petición inválida devuelve todas las rutas; UI presenta errores asociados | Contrato, orden y artículos implementados; migración global pendiente |
| P1 motor documental | Estados/adaptadores, totales, transacciones, copias y relaciones por línea | 10 → 6 → saldo 4; rechazo de 5; entrega de 4 cierra; reversión y concurrencia | Primer motor por línea implementado y probado; ampliar a todos los documentos/estados, unidades e impuestos |
| P1 ventas | Orden estable → oferta → entrega → factura interna | Proceso completo con obligación financiera y sin doble salida | Orden → entregas → facturas parciales → reversión PASS HTTP; conversión oferta → orden enlaza líneas; falta homogeneizar todo el formulario de oferta/factura/boleta |
| P1 inventario | Artículo/almacén/stock/ledger; reservas; Ordered; transferencia | Secuencia 10, orden 3, entrega 3, compra 5, recepción 5, transferencia 4; conciliación | Servicios existentes con pruebas parciales; integración completa pendiente |
| P1 UDF | Metadatos cabecera/línea/maestros, tipos prioritarios, defaults, restricciones, copia, filtros y administración | Canal obligatorio configurado desde administración aparece, valida, permanece y se busca sin cambiar código | Ciclo de cabecera de orden probado por HTTP; defaults/restricciones/copia compatibles probados; líneas, tipos restantes y demás filtros pendientes |
| P2 operaciones | Reservas/anticipos, devoluciones/créditos separados, compras soporte, conteos y aprobación configurable | Ciclos, reversión, permisos e impactos probados | Reservas/anticipos/crédito/compra/aprobación parciales; resto pendiente |
| P3 preparación | Lote/serie/ubicaciones, picking/packing, reportes avanzados | Entidades y fronteras documentadas; funciones según alcance de fase | Lotes existen; demás capacidades preparadas incrementalmente |

## Método de despliegue

1. Usar `db_appu_qa` con datos sintéticos. No ejecutar pruebas de mutación sobre la base operativa.
2. Mantener el esquema por documento y sus claves. Introducir cambios aditivos en `db/migrations/`, con precondiciones y orden de aplicación documentado.
3. Guardar respaldo antes de migrar una base operativa. Comparar conteos, relaciones y saldos antes/después. No aplicar una migración sobre datos reales solo porque funcione en QA.
4. Migrar un flujo completo cada vez; mantener adaptadores para consumidores antiguos mientras se cambia la UI.
5. Probar crear, consultar, editar, confirmar, parcial, saldo, cancelar/revertir y fallar a mitad de transacción. Verificar documento, detalle, stock, ledger, relaciones, UDF e historial.
6. Verificar las acciones por perfil y estado. Las pruebas de texto/render solo son auxiliares; no acreditan comportamiento.
7. Aumentar `SCRIPT_VER` cuando cambien recursos de navegador. Registrar evidencia y brechas; retirar el camino antiguo cuando se verifique la equivalencia del proceso.

## Reglas de compatibilidad

- Las cantidades actuales de varios detalles son enteros. Rechazar fracciones explícitamente hasta migrar unidades/conversiones y todos los consumidores; nunca truncar silenciosamente.
- Las órdenes pendientes no reservan; confirmar es el posteo actual. Los nuevos estados de dominio deben mapearse sin confundir estado comercial, logístico y de cobro.
- No modificar líneas con aplicaciones derivadas. Migrar primero relaciones por línea antes de permitir copias parciales generales.
- Una factura de entrega nunca genera otra salida. Una reserva no debe sumar dos veces el compromiso de su orden.
- Reversión, cierre y anulación son casos de uso distintos. No sustituirlos por edición directa del saldo.
- Los UDF de cabecera se copian con autorización de metadatos y compatibilidad de tipo/lista; hay pruebas de esta regla. Extender el mismo contrato a ámbitos y tipos aún pendientes.

## Próximas verificaciones concretas

1. Continuar P0 en maestros, oferta, factura/boleta legadas y demás formularios; mantener las regresiones del proceso comercial ya probado por HTTP.
2. Revisar visualmente navegación de orden/entrega/factura de entrega y mapa; hay once pruebas DOM pero no certificación visual.
3. Ejecutar los controles inventariados de la matriz regenerada, con evidencia por control.
4. Consolidar reglas de validación y migrar maestros/oferta con la misma presentación de errores.
5. Ampliar las relaciones por línea a los caminos restantes y UDF por ámbito; después consolidar Ordered, unidades y preferencias de formulario.

El objetivo permanece abierto hasta verificar los requisitos completos del prompt. Ninguna fase se considera terminada solo por crear documentos o archivos.

## P0 de maestros: avance en artículos

Artículos utiliza el primer adaptador del ciclo común: consulta de solo lectura, edición/cancelación explícitas, dos destinos de guardado, búsqueda por atributos/UDF con paginación, control de respuestas tardías y protección frente a ediciones concurrentes. El listado enlaza a la ficha común y la URL de edición anterior permanece compatible. Se retiró el controlador duplicado.

No requiere DDL adicional al esquema de metadatos y `20261027_document_operation_guards.sql` ya documentados; las claves de petición también cubren el guardado del maestro. Solo se probó en QA. La base operativa sigue sin migrarse. La evidencia está en ocho pruebas DOM y el ciclo HTTP de artículos. Los siguientes adaptadores son clientes y proveedores, incluyendo la coordinación de ubigeo, catálogos y contactos/direcciones; luego empleados y usuarios, conservando sus salvaguardas de identidad/administrador.
