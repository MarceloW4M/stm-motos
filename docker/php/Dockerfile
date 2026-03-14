FROM php:8.2-fpm

# Instalar extensiones necesarias
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip

# Instalar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copiar configuración de PHP
COPY docker/php/php.ini /usr/local/etc/php/conf.d/custom.ini

# Configurar el directorio de trabajo
WORKDIR /var/www/html

# Exponer el puerto 9000 para PHP-FPM
EXPOSE 9000

# Salud check para el contenedor
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD curl -f http://localhost/ping || exit 1
