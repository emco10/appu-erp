# Integraciones Perú en APPU ERP

Implementación preparada el 6–7 de noviembre de 2026. La consulta manual no impide registrar datos por cuenta propia cuando falta proveedor o configuración.

## Uso

- Clientes/proveedores: ingresa DNI o RUC, pulsa **Consultar DNI/RUC**, revisa la respuesta y selecciona qué incorporar con **Aplicar selección**. **Guardar** persiste el maestro. Escribir o cambiar el documento no consulta un servicio externo.
- El perfil conserva agente de retención, agente de percepción y buen contribuyente como sí/no/sin verificar, además de procedencia y fecha. La cuenta BN de detracciones se registra y verifica manualmente. Cambiar el documento invalida el perfil anterior. No se infiere un régimen contable sólo del RUC.
- Gestión → **Integraciones Perú**: opciones separadas para identidad, GRE, SIRE y asistencia de retenciones/detracciones; todas comienzan desactivadas. **Consultar configuración de la API** muestra pendientes sin revelar claves.
- Logística → **Gestionar GRE**: consulta de guías, preparación de datos SUNAT, previsualización y envío manual, consulta manual del ticket y descarga de XML/CDR. La guía de origen conserva su número; una serie interna no se convierte en T ni se renumera. Al crear una nueva guía se puede elegir la serie T activa administrada en Gestión.
- Gestión → **Consultar SIRE**: períodos RVIE/RCE, exportación de propuesta, tickets y descarga. Cada solicitud requiere un botón. No se presentan declaraciones ni se generan libros.

## Retenciones y detracciones

El módulo Gestión incorpora una evaluación por operación en soles, con referencia de compra/venta y revisión explícita. Guarda una evaluación independiente con clave de idempotencia; no cambia el importe del CPE, registra un pago, crea un asiento ni acredita un depósito BN. La emisión del comprobante electrónico de retención y su aplicación a la conciliación contable requieren una siguiente integración específica.

La asistencia cubre retención IGV y servicios del Anexo 3 del SPOT. Distingue importe total de operación y pago parcial, exclusiones del proveedor, designación del comprador, tasas por servicio y depósitos previos declarados. Solicita la fecha de nacimiento de la obligación IGV; fechas históricas fuera del alcance, futuras y datos desconocidos producen revisión pendiente. El redondeo SPOT a soles se elige expresamente y queda registrado con la evaluación. Bienes, IVAP, transporte terrestre de carga y otros casos especiales conservan revisión específica; no se les aplica una tasa genérica de servicios.

Fuentes: [retenciones SUNAT](https://orientacion.sunat.gob.pe/06-preguntas-frecuentes-regimen-de-retenciones), [cartilla SPOT SUNAT](https://orientacion.sunat.gob.pe/sites/default/files/inline-files/Cartilla_detracciones.pdf). La revisión de una operación y sus exclusiones corresponde a quien la registra.

## Instalación y aislamiento

`20261106_create_peru_integrations.sql` agrega configuración, perfil y evaluaciones, más peso/unidad/apellidos para GRE. La API requiere además su migración `003_create_gre_submissions.sql`. Ninguna modifica importes o estados históricos CPE. Las claves permanecen en el servidor privado de la API; el navegador usa sesión, permisos y CSRF del ERP.

El archivo histórico `20261102_repair_sunat_series_scope.sql` se restaura con los bytes CRLF originales registrados en Hostinger (SHA-256 `c1082b5c01f374308d01750003c6bd78ebe5a197a9a6897ab51aaca090a02934`). Su SQL normalizado no cambia. `.gitattributes` evita otra conversión automática; el historial de migraciones permanece intacto.

Ver la arquitectura y los pendientes de claves en [API SUNAT](https://github.com/emco10/appu-sunat-api/blob/master/docs/peru-integrations.md). La validación con respuestas simuladas no acredita una nueva aceptación real de SUNAT.
