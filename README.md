# DevFramework - Configuration Environment System

A modular PHP framework for secure business applications with both CLI and web capabilities. This documentation covers the configuration environment system and web application features.

## Overview

The configuration system provides a robust way to manage application settings using `.env` files with validation, type casting, and easy access patterns. The framework now supports both command-line tools and web applications.

## Features

- ✅ `.env` file loading with vlucas/phpdotenv
- ✅ Hierarchical configuration with dot notation access
- ✅ Environment variable type casting (boolean, null values)
- ✅ Configuration validation
- ✅ CLI tools for configuration management
- ✅ Web application support with Nginx + PHP-FPM
- ✅ Docker containerized environment
- ✅ RESTful API endpoints
- ✅ Database support (MySQL, PostgreSQL, Redis)
- ✅ Singleton pattern for consistent configuration access
- ✅ Helper functions for easy access

## Quick Start

### 1. Installation

```bash
composer install
```

### 2. Start the Development Environment

```bash
# Start web application and databases
./dev.sh start

# Or build and start
./dev.sh build && ./dev.sh start
```

### 3. Access the Application

- **Web Application**: http://localhost:8080
- **Demo Page**: http://localhost:8080/demo.html
- **API Health Check**: http://localhost:8080/health
- **Configuration API**: http://localhost:8080/config

### 4. CLI Tools (Preserved)

```bash
# Initialize Configuration
php config.php init

# Generate encryption key
php config.php generate-key

# Validate configuration
php config.php validate
```

### 3. Basic Usage

```php
<?php
require_once 'vendor/autoload.php';
require_once 'src/Core/helpers.php';

use DevFramework\Core\Config\Configuration;

// Load configuration
$config = Configuration::getInstance();
$config->load();

// Access configuration values
echo config('app.name');              // "DevFramework Application"
echo config('app.debug');             // true/false
echo config('database.default');      // "mysql"

// Access with defaults
echo config('non.existent', 'default'); // "default"

// Environment variables
echo env('APP_ENV', 'production');    // "development"
```

## Configuration Structure

The configuration system organizes settings into logical groups:

### Application Settings
```env
APP_NAME="Your Application Name"
APP_ENV=development
APP_DEBUG=true
APP_URL=http://localhost:8000
APP_TIMEZONE=UTC
APP_KEY=your-app-key
```

### Database Settings
```env
DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=your_database
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

### Security Settings
```env
ENCRYPTION_KEY=your-32-char-encryption-key
HASH_ALGO=bcrypt
```

## CLI Commands

### Configuration Management

```bash
# Initialize .env file from example
php config.php init

# Validate current configuration
php config.php validate

# Generate encryption key
php config.php generate-key

# Show all configuration
php config.php show

# Show specific configuration value
php config.php show app.name

# Show help
php config.php help
```

## Web Application

The framework now includes a full web application stack with Nginx and PHP-FPM.

### Development Commands

```bash
# Start the web application
./dev.sh start

# Open web application in browser
./dev.sh web

# View container logs
./dev.sh logs

# Check container status
./dev.sh status

# Stop the application
./dev.sh stop

# Rebuild containers
./dev.sh build

# Clean up containers and volumes
./dev.sh clean
```

### Web Endpoints

- **GET /**: API information and available endpoints
- **GET /config**: Configuration system status
- **GET /health**: Application health check
- **GET /demo.html**: Interactive web demo
- **GET /phpinfo**: PHP configuration (debug mode only)

### Docker Services

The application includes the following services:

- **PHP-FPM**: Web application backend (port 8080)
- **MySQL**: Database server (port 3306)
- **Redis**: Caching server (port 6379)
- **Nginx**: Web server (internal)

### Web Application Structure

```
public/
├── index.php      # Main web entry point
├── demo.html      # Interactive demo page
└── .htaccess      # URL rewriting rules

docker/
├── nginx.conf     # Nginx main configuration
├── default.conf   # Virtual host configuration
├── www.conf       # PHP-FPM configuration
└── supervisord.conf # Process management
```

### Environment Variables for Web

```env
APP_ENV=development
APP_DEBUG=true
```

## API Reference

### Configuration Class

```php
use DevFramework\Core\Config\Configuration;

$config = Configuration::getInstance();

// Load configuration
$config->load('/path/to/project');

// Get values
$config->get('key.name', 'default');
$config->has('key.name');
$config->all();

// Set values (runtime only)
$config->set('key.name', 'value');

// Environment variables
$config->env('VAR_NAME', 'default');
$config->envRequired('REQUIRED_VAR');
```

### Helper Functions

```php
// Configuration access
config('app.name');
config('app.debug', false);
config(); // Get all

// Environment variables  
env('APP_ENV', 'production');
```

### Configuration Validator

```php
use DevFramework\Core\Config\ConfigValidator;

$validator = new ConfigValidator();
$isValid = $validator->validate();
$errors = $validator->getErrors();
$validator->displayResults();

// Generate keys
$key = ConfigValidator::generateEncryptionKey();
```

## Configuration Categories

### app
- `name`: Application name
- `env`: Environment (development, testing, staging, production)
- `debug`: Debug mode toggle
- `url`: Application URL
- `timezone`: Application timezone
- `key`: Application key

### database
- `default`: Default database connection
- `connections`: Database connection configurations

### security
- `encryption_key`: Encryption key for sensitive data
- `hash_algo`: Password hashing algorithm

### cache
- `default`: Default cache driver
- `prefix`: Cache key prefix

### session
- `driver`: Session storage driver
- `lifetime`: Session lifetime in minutes
- `encrypt`: Whether to encrypt session data

## Environment Variable Type Casting

The system automatically casts environment variables:

```env
APP_DEBUG=true          # becomes boolean true
APP_DEBUG=false         # becomes boolean false
APP_DEBUG=(null)        # becomes null
APP_DEBUG=(empty)       # becomes empty string ""
```

## Validation Rules

The validator checks:

- **Application**: Required name, valid environment, valid URL, valid timezone
- **Database**: Required host, database, username; numeric port
- **Security**: Required encryption key (32+ chars), valid hash algorithm

## Next Steps

This configuration system provides the foundation for:
1. Database connectivity configuration
2. Module system configuration
3. Authentication system configuration
4. API system configuration
5. Security settings management

## Example Usage

See `example.php` for a complete demonstration of the configuration system features.
