# Verificación de la primera migración

Fecha de operación: 26-10-2026, America/Lima. Base de pruebas: `db_appu_qa`. No se aplicó la migración de metadatos a `db_appu`.

| Comprobación ejecutada | Resultado | Qué acredita |
|---|---|---|
| Lint de 14 archivos PHP modificados/nuevos | PASS | Sintaxis PHP 8.1 |
| `node --check` en document-ui, footer-functions, resumen-ordenes-venta e index | PASS | Sintaxis JavaScript |
| `tests/run-smoke-qa.cmd` | PASS | Render y regresiones de maestros, documentos, compras, pagos, inventario, permisos y contratos existentes; nueva orden/reglas/metadatos incluidos |
| `tests/sales-order-document-lifecycle.php` | PASS | Errores múltiples, alta, consulta, edición, total, persistencia UDF, conflicto y restricción de órdenes confirmadas |
| `tests/validation-engine.php` | PASS | Required, condición, rango, longitud, patrón, unicidad, valores permitidos, reglas de negocio y validador personalizado |
| `tests/custom-field-metadata-lifecycle.php` | PASS | Default, restricciones agregadas, copia optativa/compatible, campo oculto obligatorio y protección de tipos usados |
| `tests/sales-order-http.php` | PASS | CSRF; error agregado; lista/opciones/campo desde Administración; obligatorio vacío; persistencia/consulta/búsqueda exacta; edición y conflicto 409 |
| `node --test tests/sales-order-ui.test.cjs` | PASS, 5 casos | Nuevo limpio y peticiones obsoletas; SEARCH y filtros; VIEW/EDIT/cancelar; marcas/foco/conservación de errores; no duplicar alta si falla recarga |
| Consulta posterior de fixtures `qa_http_*` y `qa_metadata_*` | 0 campos, 0 órdenes, 0 listas remanentes | Limpieza y rollback de los fixtures nuevos |
| `python tests/audit-ui-actions.py` | 213 controles PHP, 62 patrones JavaScript | Inventario reproducible, sin certificar ejecución individual |

Las pruebas DOM usan Select2/DataTables/modal simulados para ejecutar el controlador y controlar el orden de respuestas. No prueban el render del plugin real, el diseño responsive, la descarga PDF/Excel ni todas las acciones de Bootstrap. La revisión visual del flujo nuevo sigue pendiente por la interrupción de Computer Use al comprobar la URL de Brave.

Las pruebas existentes de entregas verifican parcialmente saldos/estados; no demuestran que la guía basada en orden genere la salida física. La auditoría encontró que ese camino todavía no la registra. Tampoco está implementado el flujo genérico entrega → factura con relaciones por línea y prevención de doble salida. Son requisitos abiertos, junto con la migración global de navegación y la extensibilidad por línea.

La aprobación de esta etapa no se extiende al objetivo ERP completo. Ver `migration-plan.md` para las puertas de salida restantes.
