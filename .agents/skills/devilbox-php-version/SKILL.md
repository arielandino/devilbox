---
name: devilbox-php-version
description: Procedimiento para configurar, cambiar y ejecutar versiones de PHP en Devilbox (incluyendo PHP 8.4 con imágenes de la comunidad).
---

# Gestión de Versiones de PHP en Devilbox

Esta skill describe cómo gestionar, cambiar y habilitar versiones modernas de PHP (como PHP 8.4) en el entorno Devilbox local.

## 1. Configuración de PHP 8.4

En las versiones fijadas de Devilbox, las imágenes oficiales están fijadas a `devilbox/php-fpm:${PHP_SERVER}-work-0.150`.
Para PHP 8.4 se utiliza la imagen mantenida por la comunidad:
- Imagen base: `devilboxcommunity/php-fpm:8.4-work-0.160`
- Tag local para compatibilidad con Devilbox:
  ```bash
  docker pull devilboxcommunity/php-fpm:8.4-work-0.160
  docker tag devilboxcommunity/php-fpm:8.4-work-0.160 devilbox/php-fpm:8.4-work-0.150
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
