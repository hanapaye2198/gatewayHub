FROM dunglas/frankenphp:1-php8.4-bookworm

RUN install-php-extensions \
        bcmath \
        curl \
        gd \
        intl \
        mbstring \
        opcache \
        pcntl \
        pdo_pgsql \
        zip

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --no-autoloader \
    --prefer-dist

COPY . .

RUN find bootstrap/cache -maxdepth 1 -type f -name '*.php' -delete \
    && mkdir -p \
        storage/app/public \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/testing \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && composer dump-autoload --classmap-authoritative --no-dev --no-interaction --no-scripts \
    && ln -s ../storage/app/public public/storage \
    && chown -R www-data:www-data storage bootstrap/cache \
    && printf '%s\n' \
        'memory_limit=256M' \
        'opcache.enable=1' \
        'opcache.enable_cli=0' \
        'opcache.memory_consumption=128' \
        'opcache.interned_strings_buffer=16' \
        'opcache.max_accelerated_files=20000' \
        'opcache.validate_timestamps=0' \
        > "$PHP_INI_DIR/conf.d/zz-kemopay.ini"

COPY Caddyfile.kemopay /etc/caddy/Caddyfile

ENV APP_ENV=production \
    APP_DEBUG=false

USER www-data

EXPOSE 8080

ENTRYPOINT ["/usr/local/bin/frankenphp"]
CMD ["run", "--config", "/etc/caddy/Caddyfile"]
