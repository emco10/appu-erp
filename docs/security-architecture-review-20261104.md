# Revisión de seguridad de APPU ERP

Fecha: 4 de noviembre de 2026

## Dictamen

APPU ERP tiene una base de seguridad razonable, pero aún no debe considerarse una plataforma de alta seguridad sin completar el endurecimiento del hosting y una prueba dinámica sobre producción. La aplicación usa PDO con consultas preparadas nativas, autenticación con `password_verify`, renovación del ID de sesión, protección CSRF para operaciones de escritura y autorización centralizada por rol y permiso.

El riesgo residual se clasifica como **medio**. No se identificó en la revisión estática una ruta directa de inyección SQL desde parámetros HTTP. Las consultas con nombres de tabla o columna dinámicos revisadas proceden de catálogos cerrados de la aplicación; los valores externos se enlazan mediante parámetros. Esto reduce significativamente el riesgo, pero no sustituye una prueba DAST autenticada y recurrente.

## Cambios aplicados

- Cookies de sesión con `HttpOnly`, `SameSite=Lax`, `Secure` bajo HTTPS, modo estricto y uso exclusivo de cookies.
- Caducidad por inactividad a las 2 horas, duración absoluta de 12 horas y rotación del ID cada 30 minutos.
- Cabeceras CSP, HSTS bajo HTTPS, anti-clickjacking, anti-MIME sniffing, política de referentes y restricción de capacidades del navegador.
- Límite persistente de acceso: 8 fallos por combinación usuario/IP o 30 por IP durante 15 minutos.
- Actualización automática del hash de contraseña al algoritmo vigente después de un acceso válido.
- Usuario e IP se almacenan como huellas SHA-256, no como texto claro, en el registro de intentos.
- Bloqueo web de archivos ocultos, copias, llaves, logs, configuraciones y artefactos de desarrollo.
- Producción rechaza el usuario MySQL `root` y contraseñas vacías.

## Riesgos que requieren operación de infraestructura

1. **PHP fuera de soporte.** El entorno local ejecuta PHP 8.1.4. PHP 8.1 terminó soporte el 31 de diciembre de 2025. Producción debe actualizarse preferentemente a PHP 8.4 u 8.5 y ejecutar toda la batería de regresión antes del cambio.
2. **Privilegios de MySQL.** La cuenta de la aplicación debe tener únicamente permisos DML y los permisos mínimos de esquema que exija el proceso de migración. La cuenta de migraciones debe ser separada de la cuenta web.
3. **Secretos.** Mantener la configuración privada fuera del directorio público con permiso `0600`; rotar credenciales de BD, SUNAT, certificados y SSH si alguna vez estuvieron en respaldos o canales inseguros.
4. **TLS y proxy.** Forzar HTTPS en Hostinger, mantener certificados automáticos y verificar que PHP recibe correctamente el estado HTTPS para emitir cookies `Secure` y HSTS.
5. **Observabilidad.** Centralizar logs de autenticación, errores 401/403/419/429 y envíos SUNAT; crear alertas por volumen anormal sin registrar contraseñas, tokens, XML firmados ni datos sensibles completos.
6. **Respaldo y recuperación.** Copias cifradas, restauración probada y acceso separado. Un respaldo no probado no es un control efectivo.

## Arquitectura objetivo

Internet → TLS/Hostinger/WAF → Apache con archivos sensibles bloqueados → PHP soportado → autenticación, CSRF y RBAC centralizados → servicios de dominio → MySQL con mínimo privilegio.

Las integraciones SUNAT deben salir mediante un adaptador único con lista permitida de hosts, tiempos de espera, validación TLS, trazabilidad y separación estricta entre BETA y producción. Los artefactos XML, CDR, certificados y claves deben residir fuera del directorio público; su descarga debe comprobar permiso y pertenencia del documento.

## Próximas verificaciones prioritarias

- Ejecutar un escaneo autenticado de caja negra en un clon de producción y luego una validación no destructiva en producción.
- Revisar dependencias PHP/JavaScript heredadas y retirar librerías sin versión o sin mantenimiento.
- Migrar la CSP progresivamente a nonces y eliminar JavaScript inline para poder aplicar `script-src` estricto.
- Añadir segundo factor para administradores y cuentas financieras.
- Revisar trimestralmente roles, cuentas inactivas, logs, certificados y recuperación de respaldos.

## Criterio de aceptación

No declarar el sistema “seguro” sólo porque las pruebas funcionales pasan. La aceptación requiere: PHP soportado, HTTPS forzado, migración aplicada, cuenta MySQL restringida, cabeceras verificadas desde Internet, pruebas autenticadas sin hallazgos altos/críticos y procedimiento de respuesta a incidentes probado.
