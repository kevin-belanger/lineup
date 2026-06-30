FROM dunglas/frankenphp:php8.4

ENV SERVER_NAME=:8000

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
        zip \
    && install-php-extensions \
        bcmath \
        curl \
        intl \
        mbstring \
        opcache \
        pcntl \
        pdo_mysql \
        redis \
        xml \
        zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

RUN mkdir -p storage bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 8000

CMD ["sh", "-lc", "until [ -f vendor/autoload.php ]; do sleep 2; done; php artisan octane:frankenphp --host=0.0.0.0 --port=8000 --workers=${OCTANE_WORKERS:-auto} --max-requests=${OCTANE_MAX_REQUESTS:-500}"]
