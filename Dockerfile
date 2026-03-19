FROM php:8.4-fpm
RUN apt-get update && apt-get install -y --no-install-recommends \
        libpq-dev libzip-dev unzip git librdkafka-dev \
    && docker-php-ext-install pdo pdo_pgsql zip opcache pcntl \
    && pecl install rdkafka redis apcu \
    && docker-php-ext-enable rdkafka redis apcu \
    && apt-get clean && rm -rf /var/lib/apt/lists/* /tmp/pear
RUN echo "apc.enable_cli=1" >> /usr/local/etc/php/conf.d/docker-php-ext-apcu.ini
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
WORKDIR /app
COPY composer.json composer.lock* ./
RUN composer install --no-dev --optimize-autoloader --no-scripts
COPY . .
RUN composer dump-autoload --optimize
EXPOSE 8080
CMD ["php", "-S", "0.0.0.0:8080", "-t", "public"]
