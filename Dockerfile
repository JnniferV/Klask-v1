FROM dunglas/frankenphp:php8.4

RUN install-php-extensions pdo_mysql intl zip gd opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

ENV APP_ENV=prod

COPY . .

# vendor + assets compilés dans l'image : le conteneur est autonome, plus de code source
# monté en volume en prod (audit perf §3.1)
RUN composer install --no-dev --optimize-autoloader --no-interaction \
    && php bin/console cache:warmup \
    && php bin/console assets:install public \
    && php bin/console asset-map:compile
