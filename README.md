# DevFramework - Modern PHP Backend Framework

A modular PHP 8.4 backend framework for secure business applications with Docker-based development environment, configuration management, and RESTful API capabilities.

## Overview

DevFramework provides a robust foundation for building modern PHP applications with a focus on configuration management, Docker containerization, and developer experience. The framework features a comprehensive configuration system, web application support, and CLI tools for development.

## Features

- ✅ **PHP 8.4** with modern syntax and performance
- ✅ **Docker Environment** with Nginx + PHP-FPM via Supervisor
- ✅ **Configuration System** with `.env` file support and validation
- ✅ **CLI Tools** for configuration management and development
- ✅ **Web Application** with RESTful API endpoints
- ✅ **Database Support** MySQL 8.4 (optional via Docker profiles)
- ✅ **Development Tools** integrated via `./dev.sh` script
- ✅ **Type Casting** for environment variables
- ✅ **Validation** for configuration integrity
- ✅ **Helper Functions** for easy configuration access

## Quick Start

### 1. Clone and Setup

```bash
git clone <repository-url>
cd backend-framework-php
```

### 2. Start Development Environment

```bash
# Start web application (PHP 8.4 + Nginx)
./dev.sh start

# Initialize configuration
./dev.sh config init

# Generate encryption key
./dev.sh config generate-key

# Validate setup
./dev.sh config validate
```

### 3. Access the Application

- **Web Application**: http://localhost:8080
- **Demo Page**: http://localhost:8080/demo.html
- **Health Check**: http://localhost:8080/health

### 4. Optional: Start with Database

```bash
# Start with MySQL 8.4
docker compose --profile with-mysql up -d
```

## Development Commands

### Environment Management
```bash
./dev.sh start      # Start containers
./dev.sh stop       # Stop containers
./dev.sh build      # Build/rebuild containers
./dev.sh status     # Show container status
./dev.sh clean      # Clean up containers and volumes
./dev.sh logs       # View application logs
```

### Development Tools
```bash
./dev.sh shell      # Open shell in container
./dev.sh web        # Open application in browser
./dev.sh test       # Run configuration tests
```

### Configuration Management
```bash
./dev.sh config init                # Initialize .env file
./dev.sh config validate           # Validate configuration
./dev.sh config generate-key       # Generate encryption key
./dev.sh config show               # Show all configuration
./dev.sh config show app.name      # Show specific value
```

### Package Management
```bash
./dev.sh composer install          # Install dependencies
./dev.sh composer update           # Update dependencies
./dev.sh composer require pkg/name # Add package
```

## Configuration System

### Basic Usage

```php
<?php
require_once 'vendor/autoload.php';
require_once 'src/Core/helpers.php';

use DevFramework\Core\Config\Configuration;

// Load configuration
$config = Configuration::getInstance();
$config->load();

// Access configuration values
echo config('app.name');              // Application name
echo config('app.debug');             // Debug mode (boolean)
echo config('database.default');      // Default database

// Access with defaults
echo config('non.existent', 'default'); // Returns 'default'

// Environment variables
echo env('APP_ENV', 'production');    // Environment with default
```

### Environment Configuration

Create `.env` file with your settings:

```env
# Application Settings
APP_NAME="Your Application Name"
APP_ENV=development
APP_DEBUG=true
APP_URL=http://localhost:8080
APP_TIMEZONE=UTC
APP_KEY=your-32-character-encryption-key

# Database Settings (when using MySQL profile)
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=devframework
DB_USERNAME=devframework
DB_PASSWORD=devframework

# Security Settings
ENCRYPTION_KEY=your-32-char-encryption-key
HASH_ALGO=bcrypt

# Cache Settings
CACHE_DRIVER=file
CACHE_PREFIX=devframework_

# Session Settings
SESSION_DRIVER=file
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
```

## Docker Architecture

### Container Stack
- **Web Container**: PHP 8.4 + Nginx + Supervisor
- **MySQL Container**: MySQL 8.4 (optional, via profile)

### Container Features
- **PHP Extensions**: MySQL/PostgreSQL PDO, BCMath, GD, mbstring, cURL, XML, OPcache
- **Web Server**: Nginx with optimized configuration
- **Process Management**: Supervisor managing Nginx + PHP-FPM
- **Volume Mounts**: Live code reloading during development

### Environment Variables for Docker

```env
# PHP Configuration
PHP_MEMORY_LIMIT=512M
PHP_UPLOAD_MAX_FILESIZE=128M
PHP_POST_MAX_SIZE=128M
PHP_MAX_EXECUTION_TIME=600

# OPcache Configuration
PHP_OPCACHE_ENABLE=1
PHP_OPCACHE_MEMORY=256

# Application Environment
APP_ENV=development
APP_DEBUG=true
```

## API Endpoints

The web application provides several built-in endpoints:

### Core Endpoints
- **GET /** - API information and status
- **GET /health** - Application health check
- **GET /config** - Configuration system status (debug mode)

### Development Endpoints
- **GET /demo.html** - Interactive demo page
- **GET /test.php** - Configuration system test page

## CLI Tools

### Configuration Commands

```bash
# Using dev.sh (recommended)
./dev.sh config init          # Initialize .env from template
./dev.sh config validate      # Validate current configuration
./dev.sh config generate-key  # Generate new encryption key
./dev.sh config show          # Display all configuration
./dev.sh config show app.name # Display specific value

# Direct PHP usage
php config-simple.php init
php config-simple.php validate
php config-simple.php generate-key
```

## Project Structure

```
devframework/
├── docker-compose.yml          # Container orchestration
├── Dockerfile                  # Multi-service container definition
├── dev.sh                      # Development script
├── env.docker.example          # Docker environment template
├── env.example                 # Application environment template
├── composer.json               # PHP dependencies
├── docker/                     # Docker configuration
│   ├── configure-php.sh        # PHP runtime configuration
│   ├── supervisord.conf        # Process management
│   ├── nginx.conf             # Nginx main config
│   ├── default.conf           # Nginx site config
│   ├── php-fpm.conf          # PHP-FPM main config
│   └── www.conf              # PHP-FPM pool config
├── public/                     # Web document root
│   ├── index.php             # Main entry point
│   ├── demo.html             # Demo page
│   └── test.php              # Test page
├── src/                        # Framework source code
│   └── Core/
│       ├── helpers.php        # Helper functions
│       ├── Config/           # Configuration system
│       │   ├── Configuration.php
│       │   ├── ConfigValidator.php
│       │   └── SimpleConfiguration.php
│       └── Console/          # CLI commands
│           └── ConfigCommand.php
├── storage/                    # Application storage
│   └── logs/                  # Log files
└── vendor/                    # Composer dependencies
```

## Configuration System API

### Configuration Class

```php
use DevFramework\Core\Config\Configuration;

$config = Configuration::getInstance();

// Load configuration
$config->load('/path/to/project');

// Get values with dot notation
$value = $config->get('app.name', 'Default Name');
$exists = $config->has('app.name');
$all = $config->all();

// Set values (runtime only)
$config->set('app.name', 'New Name');

// Environment variables
$env = $config->env('APP_ENV', 'production');
$required = $config->envRequired('APP_KEY');
```

### Helper Functions

```php
// Configuration access
$name = config('app.name');
$debug = config('app.debug', false);
$all = config(); // Get all configuration

// Environment variables  
$env = env('APP_ENV', 'production');
```

### Configuration Validator

```php
use DevFramework\Core\Config\ConfigValidator;

$validator = new ConfigValidator();
$isValid = $validator->validate();
$errors = $validator->getErrors();
$validator->displayResults();

// Generate encryption key
$key = ConfigValidator::generateEncryptionKey();
```

## Type Casting

Environment variables are automatically cast to appropriate PHP types:

```env
APP_DEBUG=true          # → boolean true
APP_DEBUG=false         # → boolean false
APP_DEBUG=(null)        # → null
APP_DEBUG=(empty)       # → empty string ""
APP_SOME_NUMBER=123     # → string "123" (cast manually if needed)
```

## Validation Rules

The configuration validator checks:

- **Application**: Required name, valid environment, valid URL format, valid timezone
- **Database**: Required host/database/username, numeric port, valid connection type
- **Security**: Required encryption key (32+ characters), valid hash algorithm
- **Cache**: Valid cache driver, proper prefix format
- **Session**: Valid session driver, numeric lifetime

## Development Workflow

### Daily Development

1. **Start Environment**:
   ```bash
   ./dev.sh start
   ```

2. **Open Application**: http://localhost:8080

3. **Make Changes**: Edit files in `src/`, `public/`, etc.

4. **Test Changes**: Refresh browser or run tests

5. **Debug**: Use `./dev.sh logs` to view application logs

6. **Shell Access**: Use `./dev.sh shell` for container access

### Database Development

1. **Start with Database**:
   ```bash
   docker compose --profile with-mysql up -d
   ```

2. **Access MySQL**:
   - Host: localhost:3306
   - Database: devframework
   - Username: devframework  
   - Password: devframework

## Production Deployment

### Environment Setup

1. Set production environment variables:
   ```env
   APP_ENV=production
   APP_DEBUG=false
   ```

2. Generate secure encryption key:
   ```bash
   ./dev.sh config generate-key
   ```

3. Configure production database settings

### Docker Production

For production deployment, consider:
- Using multi-stage Dockerfile builds
- External database services
- Load balancers for scaling
- Proper secret management
- Health monitoring and logging

## Troubleshooting

### Common Issues

1. **Port Conflicts**: Change port in `docker-compose.yml`
2. **Permission Issues**: Check file ownership in containers
3. **Configuration Errors**: Use `./dev.sh config validate`
4. **Container Issues**: Use `./dev.sh logs` to debug

### Debug Commands

```bash
./dev.sh status                 # Check container status
./dev.sh logs                   # View application logs
docker compose logs web         # Direct Docker logs
./dev.sh shell                  # Access container shell
```

## Next Steps

This framework provides the foundation for:

1. **Database Layer**: Add ORM/database abstraction
2. **Authentication System**: User management and JWT tokens
3. **API Router**: RESTful routing and middleware
4. **Module System**: Plugin architecture
5. **RBAC System**: Role-based access control
6. **Testing Framework**: Unit and integration tests
7. **Logging System**: Structured application logging
8. **Caching Layer**: Redis/Memcached integration

## Examples

See the following files for usage examples:
- `public/demo.html` - Interactive web demo
- `public/test.php` - Configuration system testing
- `example.php` - Complete PHP usage examples
