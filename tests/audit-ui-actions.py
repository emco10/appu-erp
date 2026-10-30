"""Inventory source controls; deliberately does not certify runtime behavior."""
from html.parser import HTMLParser
from pathlib import Path
import json
import re

ROOT = Path(__file__).resolve().parents[1]


class Controls(HTMLParser):
    def __init__(self):
        super().__init__()
        self.rows = []
        self.current = None

    def handle_starttag(self, tag, attrs):
        attrs = dict(attrs)
        actionable = tag == "button" or (tag == "input" and attrs.get("type") in ("button", "submit", "reset")) or (tag == "a" and (attrs.get("role") == "button" or "data-toggle" in attrs or "data-bs-toggle" in attrs))
        if actionable:
            row = {"tag": tag, "id": attrs.get("id", ""), "class": attrs.get("class", ""), "label": attrs.get("aria-label") or attrs.get("title") or attrs.get("value", ""), "line": self.getpos()[0]}
            self.rows.append(row)
            self.current = row if tag != "input" else None

    def handle_data(self, text):
        if self.current is not None:
            self.current["label"] += " " + text

    def handle_endtag(self, tag):
        if tag in ("button", "a"):
            self.current = None


rows = []
for view in sorted((ROOT / "views").rglob("*.php")):
    contents = re.sub(r"<\?php[\s\S]*?\?>", lambda match: "\n" * match.group(0).count("\n"), view.read_text(encoding="utf-8"))
    parser = Controls()
    parser.feed(contents)
    script = ROOT / "ajax" / (view.stem + ".js")
    js = script.read_text(encoding="utf-8") if script.exists() else ""
    endpoints = sorted(set(re.findall(r"(?:\.\./)+modules/[a-zA-Z0-9_/-]+\.php", js)))
    for control in parser.rows:
        control["label"] = re.sub(r"\s+", " ", control["label"]).strip() or "Control de icono / cierre"
        control.update(module=view.parent.name, screen=str(view.relative_to(ROOT)).replace("\\", "/"), script=str(script.relative_to(ROOT)).replace("\\", "/") if script.exists() else "js/footer-functions.js o control nativo", endpoint_candidates=endpoints, evidence="Pendiente de prueba individual")
        rows.append(control)

dynamic = []
for script in sorted([*(ROOT / "ajax").glob("*.js"), *(ROOT / "js").glob("*.js")]):
    source = script.read_text(encoding="utf-8")
    for match in re.finditer(r"<button\b[^>]*>[\s\S]*?</button>", source):
        fragment = match.group(0)
        # Store source pattern, not a guessed runtime identity.
        dynamic.append({"script": str(script.relative_to(ROOT)).replace("\\", "/"), "line": source[:match.start()].count("\n") + 1, "pattern": fragment[:600], "evidence": "Patrón dinámico; inventario de instancias y prueba runtime pendientes"})

artifact = {"static_controls": rows, "dynamic_patterns": dynamic}
(ROOT / "docs/ui-action-inventory.json").write_text(json.dumps(artifact, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
lines = [
    "# Auditoría de acciones de interfaz",
    "", "Fecha: 29-10-2026. Estado: certificación de flujos automatizados; la revisión visual de todos los controles permanece separada.", "",
    "El inventario se reproduce con `python tests/audit-ui-actions.py`. Las rutas de endpoint son candidatos del controlador de pantalla: no prueban que cada control los invoque. Los controles de tablas, menús, permisos y cadenas JavaScript pueden producir múltiples instancias; requieren revisión runtime complementaria.", "",
    f"Inventario: {len(rows)} controles declarados en vistas PHP y {len(dynamic)} patrones de botones generados por JavaScript. Detalle estructurado: `ui-action-inventory.json`.", "",
    "## Procesos con evidencia reciente", "",
    "| Módulo | Pantalla | Acción | Resultado esperado | Resultado actual | Problema | Corrección | Test |",
    "|---|---|---|---|---|---|---|---|",
    "| Maestros | Artículos | Nuevo / limpiar, consultar, editar, cancelar y guardar | Ficha independiente; VIEW de solo lectura; restaurar al cancelar y conservar errores | PASS DOM + HTTP | Edición inmediata y UDF residuales; guardado genérico sin fallo de red | Ciclo común de maestros, snapshot y errores por campo | `product-master-ui.test.cjs`, `product-master-http.php` |",
    "| Maestros | Artículos | Buscar por atributos/UDF, paginar y navegar | Resultados seguros; ignorar respuestas anteriores; 27 registros en páginas de 25 y 2 | PASS DOM + HTTP | Carga completa y sin protección de carreras | Búsqueda parametrizada y paginada, generaciones de lectura/lista | `product-master-ui.test.cjs`, `product-master-http.php` |",
    "| Maestros | Artículos | Reintento y edición concurrente | Una sola alta; conflicto sin sobrescribir; cambios físicos no alteran versión del maestro | PASS HTTP | Riesgo de doble alta y última escritura ciega | Petición transaccional reutilizada, versión de atributos/UDF bajo bloqueo | `product-master-http.php` |",
    "| Ventas | Orden de venta | Nuevo después de buscar/abrir | Formulario limpio, sin respuestas tardías | PASS DOM | Estado/cargas residuales | Modos explícitos y generación de petición | `sales-order-ui.test.cjs` |",
    "| Ventas | Orden de venta | Consultar → Editar → Cancelar edición | Restaurar cabecera, líneas y UDF | PASS DOM + servicio | No había consulta/edición propias | Snapshot, VIEW/EDIT, restauración | `sales-order-ui.test.cjs`, `sales-order-document-lifecycle.php` |",
    "| Ventas | Orden de venta | Guardar inválido | Todos los errores; datos conservados; foco y marcas | PASS HTTP + DOM | Error genérico, primer error solamente | Contrato agregado y presentación por campo | `sales-order-http.php`, `sales-order-ui.test.cjs` |",
    "| Ventas | Orden de venta | Guardar y visualizar; falla recarga | Alta única; consulta recuperable | PASS DOM | Riesgo de repetir alta tras recarga fallida | Pasar a VIEW tras persistir, antes de recargar | `sales-order-ui.test.cjs` |",
    "| Ventas | Orden de venta | Guardar con versión antigua | Conflicto sin sobrescribir | PASS HTTP + servicio | Actualización ciega | Bloqueo y huella documento/detalles/UDF | `sales-order-http.php` |",
    "| Ventas | Orden de venta | Buscar/paginar y limpiar | Filtros aplicados; solo última respuesta | PASS DOM + HTTP para UDF | Sin búsqueda avanzada ni filtro UDF | Consulta parametrizada, paginación y generación de petición | `sales-order-ui.test.cjs`, `sales-order-http.php` |",
    "| Administración | Campos personalizados | Crear lista/opciones/campo obligatorio | Aparece en orden sin editar formulario | PASS HTTP | Metadatos limitados; sin búsqueda configurable | Metadatos y render dinámico | `sales-order-http.php` |",
    "| Ventas | Orden de venta | Guardar/consultar/buscar Canal | Valor permanece y filtro exacto lo recupera | PASS HTTP | No existía búsqueda por valor UDF | Índice y filtro validado por definición | `sales-order-http.php` |",
    "| Plataforma | API de orden | Escritura sin CSRF | Rechazo 419 sin cambios | PASS HTTP | Verificación de regresión | Middleware existente conservado | `sales-order-http.php` |",
    "| Logística | Entrega desde orden | Copiar / registrar parcial | Salida física y liberación parcial de compromiso; saldo 4 tras entregar 6 de 10 | PASS servicio + HTTP | La guía de orden no descontaba stock | Servicio atómico, ledger con línea y aplicaciones FULFILLMENT | `document-engine-lifecycle.php`, `sales-fulfillment-http.php` |",
    "| Ventas | Copiar entrega a factura | Facturar parcial o varias entregas | CxC y relación por línea; ninguna segunda salida | PASS servicio + HTTP + DOM | No existía facturación desde entrega | Aplicaciones BILLING, origen DELIVERY y controles de saldo | `document-engine-lifecycle.php`, `sales-fulfillment-http.php`, `delivery-ui.test.cjs` |",
    "| Ventas/Logística | Anular factura / entrega | Reabrir saldo de facturación; revertir físicamente solo al anular entrega | PASS HTTP | Riesgo de duplicar reversión | Separación de origen de inventario y dependencia de factura activa | `sales-fulfillment-http.php` |",
    "| Ventas/Logística | Reintentar guardado | Devolver el mismo documento para la misma operación | PASS HTTP | Duplicación tras respuesta perdida | Identificación de petición transaccional; conflicto si cambia el contenido | `sales-fulfillment-http.php` |",
    "| Logística | Nuevo / cambiar origen / consultar | Ignorar respuestas viejas, limpiar y abrir el registro exacto | PASS DOM | Cabecera y líneas residuales | Generaciones de petición y consulta VIEW por record_id | `delivery-ui.test.cjs`, `document-flow-ui.test.cjs` |",
    "| Trazabilidad | Mapa comercial y nodos | Abrir relaciones y documentos; mostrar aplicaciones por línea | PASS HTTP + DOM | El mapa legado no incluía la entrega como nodo | Grafo desde relaciones persistidas y aplicaciones, con número/fecha/importe/estado | `sales-fulfillment-http.php`, `document-flow-ui.test.cjs` |",
    "| Navegación | 42 rutas del menú | Abrir módulos sin volver a Inicio | HTTP 200 y render completo | PASS HTTP | Rutas sin certificación integral | Recorrido de todos los enlaces reales del menú | `navigation-http.php` |",
    "| Navegación | Barra de trabajo | Rutas Apache, Nuevo, Navegar y desplegables | Contexto correcto; Nuevo sin ID anterior; abrir por clic | PASS DOM | Solo se leía ?url; Nuevo reabría el registro; tipos factura/boleta/orden confundidos | Resolver ruta y tipo por página, limpiar contexto, toggles por clic | `navigation-ui.test.cjs` |",
    "| Maestros | Clientes/proveedores/empleados/usuarios | Buscar, seleccionar, flechas, editar, cancelar y guardar | Recuperar identidad correcta incluso entre páginas; rechazar respuestas tardías | PASS DOM + HTTP | Navegación dependía de filas no renderizadas y datos/catálogos antiguos | Ciclo común con DataTables, generación AJAX y snapshot | `masters-navigation-ui.test.cjs`, `masters-navigation-http.php` |",
    "| Compras | Orden, recepción y factura | Ambos circuitos estándar/reserva | Conservar recibido al facturar; no doble stock; CxP y reversión | PASS HTTP | Dos controladores divergentes y pérdida de recibido de OC | Motor canónico y saldo físico compartido por origen | `purchase-process-http.php`, `purchase-goods-receipt-http.php` |",
    "| Inventario | Entrada/salida/transferencia | Crear, recuperar, rechazar exceso y anular | Cantidad, lote y valor conciliados | PASS HTTP | Valor de transferencias incompleto y cantidades submínimas | Validación y ledger valorizado atómico | `inventory-document-http.php`, `inventory-transfer-http.php` |",
    "| Finanzas | Cobro/pago | Aplicar y revertir con moneda y socio | Actualizar cartera; rechazar excesos, subcéntimos y fechas inválidas | PASS HTTP | Validación monetaria/fechas y refresco incompletos | Servicio común, filtros y refresco de saldos | `finance-payment-http.php` |",
    "| Gestión | Empresa/perfiles/listas/UDF/series/almacenes | Guardar y volver a consultar configuración | Persistencia real y protección de referencias usadas | PASS HTTP | No crear perfil, etiquetas no editables, refresco pisaba formularios | Alta de perfil, edición segura y refresco solo presencia | `management-http.php` |",
    "| Seguridad | Sesión y perfiles | Otorgar/revocar permisos y desactivar usuario | Aplicar cambios a sesión abierta en siguiente petición | PASS HTTP real + servicio | Permisos de login quedaban congelados | Releer identidad y permisos antes de autorizar | `session-auth-http.php`, `session-permissions-lifecycle.php` |",
    "| Trazabilidad | Mapa común/historial | Ventas, compras, recepción y pago | Alias legados, cantidades, importes, rutas y permisos correctos | PASS servicio + DOM + HTTP | Compras/pagos fuera del grafo y tipos de historial ausentes | Catálogo común y filtrado por permiso | `document-flow-purchases.php`, `document-flow-ui.test.cjs`, `navigation-http.php` |",
    "", "Estas pruebas ejecutan DOM/controlador o HTTP/BD; no equivalen a aprobación visual. La automatización nativa del navegador no completó la revisión visual en esta sesión. La auditoría anterior se conserva en `ui-actions-audit.md` como antecedente, con sus límites.", "",
    "## Inventario por control de vista", "",
    "Todos los elementos siguientes siguen pendientes de ejecutar individualmente salvo las acciones acreditadas arriba. El código fuente y los candidatos de endpoint permiten localizar el siguiente paso de auditoría; no sustituyen persistencia, mensajes ni errores.", "",
    "| Módulo | Pantalla | Acción | Resultado esperado | Resultado actual | Problema | Corrección | Test |",
    "|---|---|---|---|---|---|---|---|",
]
for row in rows:
    label = row["label"].replace("|", "\\|")
    selector = "#" + row["id"] if row["id"] else row["tag"] + " (línea " + str(row["line"]) + ")"
    lines.append(f"| {row['module']} | `{row['screen']}` | {label} · `{selector}` | Ejecutar la acción rotulada según estado y permiso | Localizado en fuente; ejecución individual pendiente | Evidencia runtime incompleta | Revisar `{row['script']}` y candidatos en JSON | PENDIENTE |")
(ROOT / "docs/ui-action-audit.md").write_text("\n".join(lines) + "\n", encoding="utf-8")
print(f"Inventoried {len(rows)} PHP controls and {len(dynamic)} JavaScript button patterns; runtime certification remains pending")
