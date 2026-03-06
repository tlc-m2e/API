# Dockerfile developed by Bastivan Consulting
FROM php:8.4-fpm-alpine

# Branding and Metadata
LABEL maintainer="Bastivan Consulting"
LABEL description="Universal API Foundation - PHP FPM Service"

# Install Dependencies
RUN apk add --no-cache \
    postgresql-dev \
    libzip-dev \
    git \
    unzip \
    icu-dev

# Install PHP Extensions
RUN docker-php-ext-install pdo pdo_mysql pdo_pgsql zip opcache intl

# Install Redis and MongoDB
RUN apk add --no-cache pcre-dev openssl-dev $PHPIZE_DEPS \
    && pecl install redis mongodb \
    && docker-php-ext-enable redis mongodb \
    && apk del pcre-dev openssl-dev $PHPIZE_DEPS

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Work Directory
WORKDIR /var/www/html

# Copy Source Code
COPY . .

# Install PHP Deps (Prod)
RUN composer install --no-dev --optimize-autoloader

# Permissions
RUN mkdir -p /var/www/html/logs \
    && chown -R www-data:www-data /var/www/html/logs

EXPOSE 9000

CMD ["php-fpm"]
