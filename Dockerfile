# syntax=docker/dockerfile:1

FROM php:8.3-cli-alpine AS vendor
WORKDIR /app
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN apk add --no-cache git linux-headers unzip \
  && docker-php-ext-install sockets
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --no-scripts
COPY . ./
RUN composer dump-autoload -o

FROM vendor AS test
RUN composer install --prefer-dist --no-interaction --no-progress
RUN cp .env.example .env
CMD ["php", "artisan", "test"]

FROM php:8.3-fpm-alpine AS runtime
WORKDIR /var/www/html

# Install system deps and PHP extensions
RUN apk add --no-cache icu-dev libpq-dev libzip-dev oniguruma-dev linux-headers git curl tzdata bash $PHPIZE_DEPS \
  && docker-php-ext-install intl pdo pdo_mysql pdo_pgsql opcache sockets \
  && pecl install redis \
  && docker-php-ext-enable redis

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
