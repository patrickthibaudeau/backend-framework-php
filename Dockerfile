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

# -----------------------------------------------------------------------------
# Oracle Instant Client + OCI8 extension build (multi-strategy with RPM fallback + local override)
# -----------------------------------------------------------------------------
ARG ORACLE_IC_VERSION=23.5.0.24.07
ARG ORACLE_IC_FLAVOR=basiclite   # preferred flavor (basiclite|basic)
ARG ORACLE_ENABLE_OCI8=true       # set to false to skip OCI8 build
ARG ORACLE_IC_LOCAL=0             # set to 1 to use locally provided ZIPs in docker/oracle
ENV ORACLE_HOME=/opt/oracle/instantclient \
    LD_LIBRARY_PATH=/opt/oracle/instantclient \
    OCI_LIB_DIR=/opt/oracle/instantclient \
    OCI_INC_DIR=/opt/oracle/instantclient/sdk/include

# Copy any locally provided Oracle Instant Client archives (if present)
COPY docker/oracle/ /tmp/oracle/

RUN set -e; \
    if [ "$ORACLE_ENABLE_OCI8" != "true" ]; then echo "Skipping Oracle Instant Client & OCI8 build (ORACLE_ENABLE_OCI8=$ORACLE_ENABLE_OCI8)"; exit 0; fi; \
    apt-get update; \
    apt-get install -y build-essential php8.4-dev php-pear libaio-dev unzip curl rpm2cpio cpio; \
    if apt-cache show libaio1t64 >/dev/null 2>&1; then apt-get install -y libaio1t64; else apt-get install -y libaio1 || true; fi; \
    arch=$(dpkg --print-architecture); \
    case "$arch" in \
        amd64) OCI_ZIP_TOKENS="linux.x64"; RPM_ARCH=x86_64 ;; \
        arm64) OCI_ZIP_TOKENS="linux.arm64 linux.aarch64"; RPM_ARCH=aarch64 ;; \
        *) echo "Unsupported architecture: $arch" >&2; exit 1 ;; \
    esac; \
    mkdir -p /opt/oracle; cd /opt/oracle; BASE_URL="https://download.oracle.com/otn_software/linux/instantclient"; \
    success=0; FLAVOR_USED=$ORACLE_IC_FLAVOR; \
    if [ "$ORACLE_IC_LOCAL" = "1" ]; then \
        echo "Using locally provided Oracle Instant Client archives (ORACLE_IC_LOCAL=1)"; \
        if ls /tmp/oracle/instantclient-* >/dev/null 2>&1; then \
            for z in /tmp/oracle/instantclient-*.zip; do \
                [ -f "$z" ] || continue; echo "Unzipping local archive: $z"; unzip -q "$z"; \
            done; \
            success=1; \
        else \
            echo "ERROR: ORACLE_IC_LOCAL=1 but no instantclient ZIPs were found in docker/oracle/" >&2; exit 2; \
        fi; \
    fi; \
    if [ $success -eq 0 ]; then \
        echo "Attempting remote download strategies"; \
        # ZIP attempts
        for flavor_try in "$ORACLE_IC_FLAVOR" basic; do \
          [ $success -eq 0 ] || break; \
          for token in $OCI_ZIP_TOKENS; do \
            basic_zip="instantclient-${flavor_try}-${token}-${ORACLE_IC_VERSION}.zip"; \
            sdk_zip="instantclient-sdk-${token}-${ORACLE_IC_VERSION}.zip"; \
            echo "Trying ZIP: $basic_zip & $sdk_zip"; \
            if curl -fsSL -o basic.zip "$BASE_URL/$basic_zip" && curl -fsSL -o sdk.zip "$BASE_URL/$sdk_zip"; then \
              echo "Downloaded ZIP archives for flavor=$flavor_try token=$token"; \
              unzip -q basic.zip; unzip -q sdk.zip; rm -f basic.zip sdk.zip; success=1; FLAVOR_USED=$flavor_try; break; \
            else \
              echo "ZIP variant not available: $basic_zip (or sdk)"; rm -f basic.zip sdk.zip; \
            fi; \
          done; \
        done; \
    fi; \
    if [ $success -eq 0 ]; then \
      echo "Falling back to RPM download & extraction"; \
      for flavor_try in "$ORACLE_IC_FLAVOR" basic; do \
        [ $success -eq 0 ] || break; \
        basic_rpm="oracle-instantclient-${flavor_try}-${ORACLE_IC_VERSION}-1.${RPM_ARCH}.rpm"; \
        sdk_rpm="oracle-instantclient-sdk-${ORACLE_IC_VERSION}-1.${RPM_ARCH}.rpm"; \
        echo "Trying RPM: $basic_rpm & $sdk_rpm"; \
        if curl -fsSL -o basic.rpm "$BASE_URL/$basic_rpm" && curl -fsSL -o sdk.rpm "$BASE_URL/$sdk_rpm"; then \
          echo "Downloaded RPM archives for flavor=$flavor_try"; \
          mkdir -p /tmp/oci_rpm && cd /tmp/oci_rpm; \
          rpm2cpio /opt/oracle/basic.rpm | cpio -idmv >/dev/null 2>&1; \
          rpm2cpio /opt/oracle/sdk.rpm   | cpio -idmv >/dev/null 2>&1; \
          find . -type f -name "*.jar" -delete || true; \
          rootdir=$(find . -type d -path "*instantclient*" -prune -print -quit || true); \
          if [ -z "$rootdir" ]; then rootdir=$(find . -type d -maxdepth 4 -name "client64" -print -quit || true); fi; \
          if [ -n "$rootdir" ]; then mv "$rootdir" /opt/oracle/ || true; fi; \
          cd /opt/oracle; rm -rf /tmp/oci_rpm basic.rpm sdk.rpm; \
          success=1; FLAVOR_USED=$flavor_try; \
        else \
          echo "RPM variant not available: $basic_rpm (or sdk)"; rm -f basic.rpm sdk.rpm; \
        fi; \
        cd /opt/oracle; \
      done; \
    fi; \
    if [ $success -ne 1 ]; then echo "ERROR: Unable to obtain Oracle Instant Client (tried LOCAL + ZIP + RPM)" >&2; exit 3; fi; \
    cd /opt/oracle; \
    ic_dir=$(ls -d instantclient_* 2>/dev/null | head -n1 || true); \
    if [ -z "$ic_dir" ]; then ic_dir=$(find . -maxdepth 3 -type d -name "instantclient*" | head -n1 || true); fi; \
    if [ -z "$ic_dir" ]; then \
      libpath=$(find . -maxdepth 5 -type f -name 'libclntsh.so*' | head -n1 || true); \
      if [ -n "$libpath" ]; then base=$(dirname "$libpath"); mv "$base" instantclient_${ORACLE_IC_VERSION}; ic_dir=instantclient_${ORACLE_IC_VERSION}; fi; \
    fi; \
    [ -n "$ic_dir" ] || { echo "Could not identify instantclient directory after extraction" >&2; exit 4; }; \
    [ -L instantclient ] || ln -s "$ic_dir" instantclient; \
    echo "/opt/oracle/instantclient" > /etc/ld.so.conf.d/oracle-instantclient.conf; ldconfig; \
    echo "Oracle Instant Client setup complete (dir=$ic_dir flavor=$FLAVOR_USED source=$([ "$ORACLE_IC_LOCAL" = "1" ] && echo local || echo remote))"; \
    printf "instantclient,/opt/oracle/instantclient\n" | pecl install oci8 || { echo 'PECL install oci8 failed' >&2; exit 5; }; \
    echo "extension=oci8.so" > /etc/php/8.4/mods-available/oci8.ini; phpenmod oci8; \
    php -m | grep -qi '^oci8$' || { echo 'OCI8 extension not found after build' >&2; exit 6; }; \
    apt-get purge -y build-essential php8.4-dev php-pear rpm2cpio cpio; apt-get autoremove -y; apt-get clean; \
    rm -rf /var/lib/apt/lists/* /tmp/pear ~/.pearrc /tmp/oci_rpm || true

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
    if grep -q '"mustache/mustache"' composer.json; then \
        if [ -f composer.lock ] && ! grep -q 'mustache/mustache' composer.lock; then \
            echo 'Lock file present but missing mustache/mustache – performing targeted update...'; \
            composer update mustache/mustache --no-dev --no-scripts --no-interaction; \
        fi; \
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
