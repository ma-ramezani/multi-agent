# App image: Laravel 12 + Neuron AI platform (PHP 8.3, sqlite, OCR, queue worker).
# Single container runs the web server AND the queue worker via supervisord.
FROM php:8.4-cli-bookworm

ARG DEBIAN_FRONTEND=noninteractive

# System deps: sqlite, image/text tooling, OCR (fa+en), PDF tools, supervisord.
RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip curl ca-certificates supervisor \
        libsqlite3-dev libpng-dev libjpeg-dev libfreetype6-dev \
        libzip-dev libicu-dev libonig-dev libxml2-dev libcurl4-openssl-dev \
        tesseract-ocr tesseract-ocr-fas tesseract-ocr-eng \
        poppler-utils \
    && rm -rf /var/lib/apt/lists/*

# PHP extensions required by the app (see composer.json + code usage):
# pdo_sqlite/sqlite3 (DB) mbstring/xml(dom)/zip (Office docs) gd (images)
# curl bcmath (calculator toolkit) intl (Jalali dates) fileinfo pcntl (queue).
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_sqlite pdo_mysql mbstring dom xml zip gd \
        curl bcmath intl fileinfo pcntl opcache \
    && docker-php-ext-enable opcache

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .

RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader \
    && rm -rf /root/.composer/cache

# Supervisor: web (artisan serve) + queue worker (database queue).
COPY docker/supervisord.conf /etc/supervisor/conf.d/app.conf
COPY docker/entrypoint.sh /usr/local/bin/app-entrypoint.sh
RUN chmod +x /usr/local/bin/app-entrypoint.sh \
    && mkdir -p database storage/app storage/logs bootstrap/cache \
    && touch database/database.sqlite \
    && chown -R www-data:www-data storage bootstrap/cache database \
    && chmod -R 775 storage bootstrap/cache

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    SESSION_DRIVER=database \
    CACHE_STORE=database \
    QUEUE_CONNECTION=database \
    DB_CONNECTION=sqlite \
    DB_DATABASE=/var/www/html/database/database.sqlite \
    PHP_CLI_SERVER_WORKERS=4

EXPOSE 8000

ENTRYPOINT ["app-entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisor/supervisord.conf"]
