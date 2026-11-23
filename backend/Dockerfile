# Imagem comum aos 6 serviços Laravel (eapi-web, sapis e microserviços).
# O código não é copiado para a imagem: o docker-compose monta a pasta de cada serviço em /app.
FROM php:8.4-cli

RUN apt-get update \
 && apt-get install -y --no-install-recommends git unzip libpq-dev libzip-dev \
 && docker-php-ext-install pdo_pgsql pgsql zip pcntl \
 && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

WORKDIR /app
ENV PHP_CLI_SERVER_WORKERS=4 \
    COMPOSER_PROCESS_TIMEOUT=1200

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["sh", "-c", "php artisan serve --host=0.0.0.0 --port=${PORT:-8000}"]
