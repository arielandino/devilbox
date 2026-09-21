---
name: devilbox-php-version
description: Procedimiento para configurar, cambiar y ejecutar versiones de PHP en Devilbox (incluyendo PHP 8.4 con imágenes de la comunidad).
---

# Gestión de Versiones de PHP en Devilbox

Esta skill describe cómo gestionar, cambiar y habilitar versiones modernas de PHP (como PHP 8.4) en el entorno Devilbox local.

## 1. Configuración de PHP 8.4

En las versiones fijadas de Devilbox, las imágenes oficiales solo llegan hasta 8.2 con el tag `${PHP_SERVER}-work-0.150`.
Para PHP 8.3 y PHP 8.4 se utilizan las imágenes mantenidas por la comunidad:

- **Para PHP 8.3:**
  ```bash
  docker pull devilboxcommunity/php-fpm:8.3-work-0.160
  docker tag devilboxcommunity/php-fpm:8.3-work-0.160 devilbox/php-fpm:8.3-work-0.150
  cp -r cfg/php-fpm-8.2 cfg/php-fpm-8.3
  mkdir -p log/php-fpm-8.3
  ```

- **Para PHP 8.4:**
  ```bash
  docker pull devilboxcommunity/php-fpm:8.4-work-0.160
  docker tag devilboxcommunity/php-fpm:8.4-work-0.160 devilbox/php-fpm:8.4-work-0.150
  cp -r cfg/php-fpm-8.2 cfg/php-fpm-8.4
  mkdir -p log/php-fpm-8.4
  ```

## 2. Directorios de Configuración Requeridos

Cada versión `${PHP_SERVER}` requiere la existencia de sus directorios en `cfg/` y `log/`:
- `cfg/php-ini-${PHP_SERVER}/` (configuraciones `.ini` personalizadas)
- `cfg/php-fpm-${PHP_SERVER}/` (configuración de PHP-FPM pools)
- `cfg/php-startup-${PHP_SERVER}/` (scripts de arranque)
- `log/php-fpm-${PHP_SERVER}/` (logs del contenedor)

Para PHP 8.4:
```bash
cp -r cfg/php-fpm-8.2 cfg/php-fpm-8.4
mkdir -p log/php-fpm-8.4
```

## 3. Cambiar la versión activa de PHP

En el archivo `.env` en la raíz de Devilbox:
```ini
# Descomentar la versión deseada:
#PHP_SERVER=8.2
PHP_SERVER=8.4
```

## 4. Reiniciar o Levantar el Servicio

Para aplicar los cambios:
```bash
docker compose up -d php
```
O usando el script habitual:
```bash
./run.sh
```

## 5. Verificar la versión en ejecución

Verificar la versión de PHP dentro del contenedor:
```bash
docker exec devilbox-php-1 php -v
```
Verificar a través del servidor web HTTPD:
```bash
curl -I http://localhost
```
Debe retornar la cabecera `X-Powered-By: PHP/8.4.x`.

## 6. Compatibilidad de phpMyAdmin con PHP 8.4

phpMyAdmin <= 5.2.1 no es compatible con PHP 8.4 debido a parámetros nullable implícitos en librerías dependientes (como `thecodingmachine/safe`). Se requiere **phpMyAdmin 5.2.3** (o superior).

- Ubicación en Devilbox: `.devilbox/www/htdocs/vendor/phpmyadmin-5.2.3/`
- Enrutamiento intranet: configurado en `.devilbox/www/include/lib/Html.php` (menú Tools -> phpMyAdmin).
- Acceso web: `http://localhost/vendor/phpmyadmin-5.2.3/index.php`.

