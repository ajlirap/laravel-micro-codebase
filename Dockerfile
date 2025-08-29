# syntax=docker/dockerfile:1

FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress
COPY . ./
RUN composer dump-autoload -o

FROM php:8.3-fpm-alpine AS runtime
WORKDIR /var/www/html

# Install system deps and PHP extensions
RUN apk add --no-cache icu-dev libpq-dev libzip-dev rabbitmq-c-dev oniguruma-dev git curl tzdata bash \
  && docker-php-ext-install intl pdo pdo_mysql pdo_pgsql opcache sockets

# Copy project files
COPY --from=vendor /app /var/www/html

# Set production php.ini tweaks
RUN { \
  echo "opcache.enable=1"; \
  echo "opcache.enable_cli=1"; \
  echo "opcache.validate_timestamps=0"; \
} > /usr/local/etc/php/conf.d/opcache.ini

# Non-root user
RUN addgroup -g 1000 app && adduser -G app -u 1000 -D app \
  && chown -R app:app /var/www/html /var/www
USER app

CMD ["php-fpm"]

