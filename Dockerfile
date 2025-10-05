FROM ubuntu:24.04

# Set environment variables to prevent interactive prompts
ENV DEBIAN_FRONTEND=noninteractive
ENV TZ=UTC
ENV COMPOSER_ALLOW_SUPERUSER=1

# Install system dependencies
RUN apt-get update && apt-get install -y \
    software-properties-common \
    ca-certificates \
    lsb-release \
    apt-transport-https \
    curl \
    gnupg2 \
    supervisor \
    nginx \
    unzip \
    git \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Add Ondrej's PHP repository for PHP 8.4
RUN add-apt-repository ppa:ondrej/php -y \
    && apt-get update

# Install PHP 8.4 and extensions
RUN apt-get install -y \
    php8.4-fpm \
    php8.4-cli \
    php8.4-common \
    php8.4-mysql \
    php8.4-pgsql \
    php8.4-zip \
    php8.4-gd \
    php8.4-mbstring \
    php8.4-curl \
    php8.4-xml \
    php8.4-bcmath \
    php8.4-intl \
    php8.4-ldap \
    php8.4-opcache \
    php8.4-dom \
    php8.4-simplexml \
    php8.4-xmlreader \
    php8.4-xsl \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install Composer (fixed pipe)
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Create www-data user directories
RUN mkdir -p /var/www/html \
    && chown -R www-data:www-data /var/www/html

# Set working directory
WORKDIR /var/www

# Copy composer files first for better Docker layer caching
COPY composer.json composer.lock* ./

# Install Composer dependencies (handle out-of-date lock for newly added packages)
RUN set -e; \
    if grep -q '"mustache/mustache"' composer.json && ! grep -q 'mustache/mustache' composer.lock 2>/dev/null; then \
        echo 'Lock file missing mustache/mustache – updating that package lock entry...'; \
        composer update mustache/mustache --no-dev --no-scripts --no-interaction; \
    fi; \
    composer install --no-dev --optimize-autoloader --no-scripts; \
    chown -R www-data:www-data /var/www

# Copy configuration files
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/default.conf /etc/nginx/sites-available/default
COPY docker/www.conf /etc/php/8.4/fpm/pool.d/www.conf
COPY docker/php-fpm.conf /etc/php/8.4/fpm/php-fpm.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Copy PHP configuration script
COPY docker/configure-php.sh /usr/local/bin/configure-php.sh
RUN chmod +x /usr/local/bin/configure-php.sh

# Create log directories
RUN mkdir -p /var/log/nginx /var/log/php8.4-fpm /var/log/supervisor \
    && chown -R www-data:www-data /var/log/nginx /var/log/php8.4-fpm

# Expose port 80
EXPOSE 80

# Start supervisor
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
