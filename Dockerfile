# Dockerfile developed by THE LIFE COINCOIN
FROM php:8.4-fpm

# Branding and Metadata
LABEL maintainer="THE LIFE COINCOIN"
LABEL description="Universal API Foundation - PHP FPM Service"

# Install Dependencies and build tools for FIPS OpenSSL
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libzip-dev \
    git \
    unzip \
    libicu-dev \
    wget \
    build-essential \
    && rm -rf /var/lib/apt/lists/*

# Compile OpenSSL FIPS provider module and configure OpenSSL
# This uses the exact OpenSSL version bundled in the Debian container to build the fips.so module
RUN OPENSSL_VERSION=$(openssl version | awk '{print $2}') \
    && wget https://www.openssl.org/source/openssl-$OPENSSL_VERSION.tar.gz \
    && tar -zxf openssl-$OPENSSL_VERSION.tar.gz \
    && cd openssl-$OPENSSL_VERSION \
    && ./config enable-fips \
    && make -j$(nproc) \
    && make install_fips \
    && cd .. \
    && rm -rf openssl-$OPENSSL_VERSION*

# Configure OpenSSL to use the FIPS provider by default
RUN OPENSSL_CONF_PATH="/usr/lib/ssl/openssl.cnf" \
    && if [ ! -f "$OPENSSL_CONF_PATH" ]; then OPENSSL_CONF_PATH="/etc/ssl/openssl.cnf"; fi \
    && sed -i 's/# \.include.*fipsmodule\.cnf/.include \/usr\/local\/ssl\/fipsmodule.cnf/g' $OPENSSL_CONF_PATH \
    && sed -i '/\[openssl_init\]/a providers = provider_sect' $OPENSSL_CONF_PATH \
    && sed -i '/\[provider_sect\]/a fips = fips_sect\nbase = base_sect' $OPENSSL_CONF_PATH \
    && sed -i '/\[default_sect\]/a activate = 1' $OPENSSL_CONF_PATH \
    && echo "\n[fips_sect]\nactivate = 1\n" >> $OPENSSL_CONF_PATH \
    && echo "[base_sect]\nactivate = 1\n" >> $OPENSSL_CONF_PATH

# Install PHP Extensions
RUN docker-php-ext-install pdo pdo_mysql pdo_pgsql zip opcache intl

# Install Redis and MongoDB
RUN pecl install redis mongodb \
    && docker-php-ext-enable redis mongodb

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
