@echo off
setlocal
set "APPU_PHP=C:\xampp\php\php.exe"

for %%R in (
  views/clientes/registro-cliente
  views/empleados/registro-empleado
  views/usuarios/registro-usuario
  views/gestion/index
  views/proveedores/registro-proveedor
  views/productos/registro-producto
  views/ventas/resumen-ordenes-venta
  views/ventas/flujo-comercial
  views/trazabilidad/flujo
  views/logistica/guias
  views/logistica/documentos
  views/compras/solicitudes
  views/ordenes/orden-de-compra
  views/compras/resumen-compras
  views/compras/recepciones
  views/compras/costos-entrega
  views/finanzas/cuentas-por-cobrar
  views/finanzas/cuentas-por-pagar
  views/productos/actualizar-stock
  views/productos/entrada-inventario
  views/productos/salida-inventario
  views/productos/transferencias
  views/productos/stock-por-almacen
  views/reportes/productos
  views/facturacion/registro-factura
  views/reportes/finanzas
  views/reportes/flujo-documental
) do (
  "%APPU_PHP%" "%~dp0smoke-render.php" %%R
  if errorlevel 1 exit /b 1
)

"%APPU_PHP%" "%~dp0smoke-finance.php"
if errorlevel 1 exit /b 1

"%APPU_PHP%" "%~dp0smoke-inventory.php"
if errorlevel 1 exit /b 1

"%APPU_PHP%" "%~dp0smoke-document-flow.php"
if errorlevel 1 exit /b 1

"%APPU_PHP%" "%~dp0smoke-management.php"
if errorlevel 1 exit /b 1

"%APPU_PHP%" "%~dp0customer-lifecycle.php"
if errorlevel 1 exit /b 1

"%APPU_PHP%" "%~dp0permission-smoke.php"
if errorlevel 1 exit /b 1

"%APPU_PHP%" "%~dp0provider-lifecycle.php"
if errorlevel 1 exit /b 1

"%APPU_PHP%" "%~dp0product-lifecycle.php"
if errorlevel 1 exit /b 1

"%APPU_PHP%" "%~dp0sales-delivery-status.php"
if errorlevel 1 exit /b 1

"%APPU_PHP%" "%~dp0reserve-invoice-lifecycle.php"
if errorlevel 1 exit /b 1

"%APPU_PHP%" "%~dp0sales-advance-lifecycle.php"
if errorlevel 1 exit /b 1

"%APPU_PHP%" "%~dp0purchase-lifecycle.php"
if errorlevel 1 exit /b 1

"%APPU_PHP%" "%~dp0purchase-goods-receipt-flow.php"
if errorlevel 1 exit /b 1

"%APPU_PHP%" "%~dp0purchase-order-cancellation-lifecycle.php"
if errorlevel 1 exit /b 1

"%APPU_PHP%" "%~dp0purchase-reserve-invoice-lifecycle.php"
if errorlevel 1 exit /b 1

"%APPU_PHP%" "%~dp0landed-cost-lifecycle.php"
if errorlevel 1 exit /b 1

"%APPU_PHP%" "%~dp0inventory-lifecycle.php"
if errorlevel 1 exit /b 1

"%APPU_PHP%" "%~dp0inventory-document-lifecycle.php"
if errorlevel 1 exit /b 1

"%APPU_PHP%" "%~dp0finance-payment-lifecycle.php"
if errorlevel 1 exit /b 1

"%APPU_PHP%" "%~dp0document-navigation-contract.php"
if errorlevel 1 exit /b 1

"%APPU_PHP%" "%~dp0ui-actions-contract.php"
if errorlevel 1 exit /b 1

"%APPU_PHP%" "%~dp0purchase-copy-contract.php"
if errorlevel 1 exit /b 1

"%APPU_PHP%" "%~dp0custom-field-lifecycle.php"
if errorlevel 1 exit /b 1

"%APPU_PHP%" "%~dp0warehouse-lifecycle.php"
if errorlevel 1 exit /b 1

"%APPU_PHP%" "%~dp0administration-integrity.php"
if errorlevel 1 exit /b 1

"%APPU_PHP%" "%~dp0business-partner-details-lifecycle.php"
if errorlevel 1 exit /b 1

"%APPU_PHP%" "%~dp0credit-note-lifecycle.php"
if errorlevel 1 exit /b 1

"%APPU_PHP%" "%~dp0unsaved-changes-contract.php"
if errorlevel 1 exit /b 1

"%APPU_PHP%" "%~dp0quotation-lifecycle.php"
if errorlevel 1 exit /b 1

"%APPU_PHP%" "%~dp0quotation-conversion-lifecycle.php"
if errorlevel 1 exit /b 1

"%APPU_PHP%" "%~dp0purchase-request-approval-lifecycle.php"
if errorlevel 1 exit /b 1

"%APPU_PHP%" "%~dp0responsive-layout-contract.php"
if errorlevel 1 exit /b 1

"%APPU_PHP%" "%~dp0sales-order-document-lifecycle.php"
if errorlevel 1 exit /b 1

"%APPU_PHP%" "%~dp0validation-engine.php"
if errorlevel 1 exit /b 1

"%APPU_PHP%" "%~dp0custom-field-metadata-lifecycle.php"
if errorlevel 1 exit /b 1

"%APPU_PHP%" "%~dp0document-engine-lifecycle.php"
if errorlevel 1 exit /b 1

"%APPU_PHP%" "%~dp0document-concurrency.php"
if errorlevel 1 exit /b 1

"%APPU_PHP%" "%~dp0document-totals.php"
if errorlevel 1 exit /b 1

"%APPU_PHP%" "%~dp0document-flow-purchases.php"
if errorlevel 1 exit /b 1

"%APPU_PHP%" "%~dp0session-permissions-lifecycle.php"
if errorlevel 1 exit /b 1

echo PASS smoke suite
