# capturadorWebhooks
Capturador de Webhooks para testing de API

## Uso de tokens (endpoints personalizados)

Este proyecto soporta múltiples "instancias" identificadas por un token sencillo en la URL. Cada usuario o servicio puede usar su token para enviar webhooks a un endpoint dedicado y visualizar sólo sus registros.

Ejemplo de endpoint:

- Forma legible: https://tudominio/webhooks/abc123
- Bajo el capó esto se mapea a `webhook.php?token=abc123` mediante `.htaccess` (o reglas equivalentes en nginx).

Cómo usar:

- En la UI (`index.php`) introduce tu token en el campo "Token" y pulsa "Aplicar". La interfaz pasará el token a la API y mostrará sólo los registros de ese token.
- Para enviar un webhook (ejemplo en curl):

```bash
curl -X POST https://tudominio/webhooks/abc123 \
	-H "Content-Type: application/json" \
	-d '{"event":"test","value":123}'
```

Ejemplo en PowerShell (Invoke-RestMethod):

```powershell
Invoke-RestMethod -Uri 'https://tudominio/webhooks/abc123' -Method Post -Body '{"event":"test","value":123}' -ContentType 'application/json'
```

Notas:

- No hay autenticación adicional. El token actuará sólo como un selector para separar los registros.
- Puedes compartir los tokens con los usuarios que necesiten enviar webhooks. No es una medida de seguridad robusta.

## API y parámetros relevantes

- `api.php?action=get_webhooks&limit=50&token=abc123` — devuelve los últimos 50 webhooks del token `abc123`.
- `api.php?action=get_new_webhooks&since=2025-10-17%2012:00:00&token=abc123` — devuelve los webhooks posteriores a `since` para `abc123`.
- `api.php?action=clear_webhooks&token=abc123` — elimina sólo los webhooks del token `abc123`. Si no se pasa `token`, se eliminarán todos los registros.

## Despliegue (Apache / nginx)

### Requisitos mínimos

- PHP (la versión que uses en tu servidor).
- Un servidor web (Apache o nginx).
- Base de datos: el proyecto viene preparado para MySQL o SQLite. Por defecto las configuraciones de `database.php` apuntan a un DSN local; ajusta las variables de entorno o el archivo para tu entorno.

### Apache (con .htaccess)

1. Asegúrate que `mod_rewrite` esté activo y que AllowOverride esté configurado para leer `.htaccess`.
2. Coloca el archivo `.htaccess` incluido en la raíz del proyecto; contiene la regla que convierte `/webhooks/{token}` en `webhook.php?token={token}`.
3. Ajusta permisos y propietario del directorio si usas SQLite (archivo `webhooks.db` debe ser escribible por el usuario del servidor web):

```bash
# En Linux (ejemplo)
chown www-data:www-data webhooks.db
chmod 660 webhooks.db
```

### nginx (sin .htaccess)

En nginx no se usa `.htaccess`. Añade una regla en la configuración del servidor para reenviar rutas `/webhooks/{token}` a `webhook.php`:

```nginx
location ~ ^/webhooks/([^/]+)/?$ {
		fastcgi_param  SCRIPT_FILENAME  /ruta/a/tu/proyecto/webhook.php;
		fastcgi_param  QUERY_STRING     token=$1&$query_string;
		include fastcgi_params;
		fastcgi_pass php-fpm; # ajusta según tu setup
}
```

### Notas sobre la base de datos

- Si usas SQLite verifica que `database.php` apunte al archivo correcto (`webhooks.db`) y que el proceso de PHP tenga permisos de lectura/escritura.
- Si usas MySQL, configura el DSN/usuario/contraseña mediante variables de entorno (`DB_DSN`, `DB_USERNAME`, `DB_PASSWORD`) o editando `database.php`.

## Seguridad y mejores prácticas

- El token es una conveniencia para separar registros, no un mecanismo de seguridad. Para entornos públicos/producción considera añadir autenticación (HTTP Basic, HMAC, tokens más largos/secretos o IP whitelisting).
- Limita el tamaño máximo del body en el servidor o en `php.ini` (post_max_size, upload_max_filesize) para evitar abuso.
- Implementa rotación/archivado de registros si esperas grandes volúmenes.

## Ejemplo de flujo rápido

1. Generas o acuerdas un token (p. ej. `abc123`) para un usuario.
2. Le facilitas el endpoint `https://tudominio/webhooks/abc123`.
3. El usuario envía eventos a esa URL.
4. En `index.php` aplicas el token `abc123` y ves sólo sus eventos. Puedes borrar sólo sus eventos con el botón Clear.

