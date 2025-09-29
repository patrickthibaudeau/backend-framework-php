#!/bin/bash

# Script to configure PHP settings from environment variables

PHP_INI_DIR="/etc/php/8.4/fpm"
CLI_INI_DIR="/etc/php/8.4/cli"

# Function to update PHP configuration
update_php_config() {
    local ini_file=$1

    # Memory and resource limits
    sed -i "s/memory_limit = .*/memory_limit = ${PHP_MEMORY_LIMIT:-512M}/" $ini_file
    sed -i "s/upload_max_filesize = .*/upload_max_filesize = ${PHP_UPLOAD_MAX_FILESIZE:-128M}/" $ini_file
    sed -i "s/post_max_size = .*/post_max_size = ${PHP_POST_MAX_SIZE:-128M}/" $ini_file
    sed -i "s/max_execution_time = .*/max_execution_time = ${PHP_MAX_EXECUTION_TIME:-600}/" $ini_file

    # Enable error reporting for development
    if [ "${APP_ENV:-development}" = "development" ]; then
        sed -i "s/display_errors = .*/display_errors = On/" $ini_file
        sed -i "s/display_startup_errors = .*/display_startup_errors = On/" $ini_file
        sed -i "s/error_reporting = .*/error_reporting = E_ALL/" $ini_file
    else
        sed -i "s/display_errors = .*/display_errors = Off/" $ini_file
        sed -i "s/display_startup_errors = .*/display_startup_errors = Off/" $ini_file
        sed -i "s/error_reporting = .*/error_reporting = E_ALL \& ~E_DEPRECATED \& ~E_STRICT/" $ini_file
    fi

    # Configure OPcache
    if [ "${PHP_OPCACHE_ENABLE:-1}" = "1" ]; then
        echo "opcache.enable=1" >> $ini_file
        echo "opcache.enable_cli=1" >> $ini_file
        echo "opcache.memory_consumption=${PHP_OPCACHE_MEMORY:-256}" >> $ini_file
        echo "opcache.interned_strings_buffer=8" >> $ini_file
        echo "opcache.max_accelerated_files=4000" >> $ini_file
        echo "opcache.revalidate_freq=2" >> $ini_file
        echo "opcache.fast_shutdown=1" >> $ini_file

        if [ "${APP_ENV:-development}" = "development" ]; then
            echo "opcache.validate_timestamps=1" >> $ini_file
        else
            echo "opcache.validate_timestamps=0" >> $ini_file
        fi
    fi
}

# Update both FPM and CLI configurations
update_php_config "$PHP_INI_DIR/php.ini"
update_php_config "$CLI_INI_DIR/php.ini"

echo "PHP configuration updated with environment variables"
