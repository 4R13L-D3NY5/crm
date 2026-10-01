FROM php:8.3-cli-alpine

# Install system dependencies, PostgreSQL dev libraries, and extensions
RUN apk add --no-cache \
    postgresql-dev \
    sqlite-dev \
    libzip-dev \
    linux-headers \
    git \
    unzip \
    curl \
    $PHPIZE_DEPS \
    && docker-php-ext-install pdo pdo_pgsql pgsql pdo_sqlite bcmath pcntl zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del $PHPIZE_DEPS

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

EXPOSE 8010

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8010"]
