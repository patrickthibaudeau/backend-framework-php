# DevFramework - Docker Development Environment

This guide explains how to develop your PHP framework using Docker containers with PHP 8.4, Nginx, and MySQL.

## Quick Start

1. **Start the development environment:**
   ```bash
   ./dev.sh start
   ```

2. **Initialize configuration:**
   ```bash
   ./dev.sh config init
   ```

3. **Generate encryption key:**
   ```bash
   ./dev.sh config generate-key
   ```

4. **Run tests:**
   ```bash
   ./dev.sh test
   ```

5. **Access the application:**
   - Web Application: http://localhost:8080
   - Demo Page: http://localhost:8080/demo.html
   - Health Check: http://localhost:8080/health

## Development Commands

### Environment Management
- `./dev.sh start` - Start all containers (web server with PHP 8.4 + Nginx, MySQL)
- `./dev.sh stop` - Stop all containers
- `./dev.sh build` - Build/rebuild containers
- `./dev.sh status` - Show container status
- `./dev.sh clean` - Clean up containers and volumes

### Development Tools
- `./dev.sh shell` - Open interactive shell in PHP container
- `./dev.sh logs` - View PHP container logs
- `./dev.sh test` - Run configuration system tests
- `./dev.sh web` - Open web application in browser

### Configuration Management
- `./dev.sh config init` - Initialize .env file
- `./dev.sh config validate` - Validate configuration
- `./dev.sh config generate-key` - Generate encryption key
- `./dev.sh config show` - Show all configuration
- `./dev.sh config show app.name` - Show specific config value

### Package Management
- `./dev.sh composer install` - Install dependencies
- `./dev.sh composer update` - Update dependencies
- `./dev.sh composer require package/name` - Add new package

## Manual Docker Commands

If you prefer to use Docker directly:
```bash
# Build containers
docker compose build

# Start environment
docker compose up -d

# Stop environment
docker compose down

# View logs
docker compose logs -f web

# Execute commands in container
docker compose exec web php config-simple.php validate

# Enter container shell
docker compose exec web bash
```

## Services

### Web Container (PHP + Nginx)
- **Name**: web
- **PHP Version**: 8.4
- **Web Server**: Nginx with PHP-FPM
- **Port**: 8080 (host) → 80 (container)
- **PHP Extensions**: 
  - Core: CLI, FPM, Common
  - Database: MySQL (mysqli/PDO), PostgreSQL (pgsql/PDO)
  - Utility: ZIP, GD, mbstring, cURL, XML, BCMath, Intl, LDAP
  - Performance: OPcache
  - XML Processing: DOM, SimpleXML, XMLReader, XSL
- **Tools**: Composer, Git, Supervisor

### MySQL Database (Optional)
- **Profile**: with-mysql
- **Image**: MySQL 8.4
- **Host**: localhost:3306 (from host) or mysql:3306 (from containers)
- **Database**: devframework
- **Username**: devframework
- **Password**: devframework
- **Root Password**: devframework

To start with MySQL:
```bash
docker compose --profile with-mysql up -d
```

## Configuration

### Environment Variables

The container supports configuration via environment variables defined in `env.docker.example`:

#### PHP Configuration
- `PHP_MEMORY_LIMIT=512M` - PHP memory limit
- `PHP_UPLOAD_MAX_FILESIZE=128M` - Maximum upload file size
- `PHP_POST_MAX_SIZE=128M` - Maximum POST data size
- `PHP_MAX_EXECUTION_TIME=600` - Script execution timeout

#### OPcache Configuration
- `PHP_OPCACHE_ENABLE=1` - Enable/disable OPcache
- `PHP_OPCACHE_MEMORY=256` - OPcache memory allocation (MB)

#### Application Environment
- `APP_ENV=development` - Application environment
- `APP_DEBUG=true` - Debug mode (enables error display)

#### Database Configuration
- `DB_CONNECTION=mysql` - Database type
- `DB_HOST=mysql` - Database host
- `DB_PORT=3306` - Database port
- `DB_DATABASE=devframework` - Database name
- `DB_USERNAME=devframework` - Database user
- `DB_PASSWORD=devframework` - Database password

### PHP-FPM Configuration

The container uses optimized PHP-FPM settings:
- **Process Manager**: Dynamic
- **Max Children**: 20
- **Start Servers**: 2
- **Min/Max Spare Servers**: 1-3
- **Max Requests**: 500 per worker

### Nginx Configuration

- **Document Root**: `/var/www/html` (mapped to `./public`)
- **Security Headers**: X-Frame-Options, X-XSS-Protection, X-Content-Type-Options
- **Static File Caching**: 1 year for assets (CSS, JS, images, fonts)
- **PHP Processing**: Via Unix socket to PHP-FPM
- **Gzip Compression**: Enabled for text-based files

## File Structure

```
devframework/
├── docker-compose.yml          # Container orchestration
├── Dockerfile                  # Multi-service container (PHP 8.4 + Nginx)
├── dev.sh                      # Development script
├── env.docker.example          # Docker environment template
├── docker/                     # Docker configuration files
│   ├── configure-php.sh        # PHP runtime configuration script
│   ├── supervisord.conf        # Supervisor configuration (Nginx + PHP-FPM)
│   ├── nginx.conf             # Nginx main configuration
│   ├── default.conf           # Nginx site configuration
│   ├── php-fpm.conf          # PHP-FPM main configuration
│   └── www.conf              # PHP-FPM pool configuration
├── public/                    # Web document root
│   ├── index.php             # Main entry point
│   └── demo.html             # Demo page
├── src/                      # Framework source code
│   └── Core/
│       └── Config/           # Configuration system
├── storage/                  # Framework storage
│   └── logs/                # Application logs (mapped to container)
└── vendor/                  # Composer dependencies
```

## Development Workflow

1. **Initial Setup:**
   ```bash
   ./dev.sh start
   ./dev.sh config init
   ./dev.sh config generate-key
   # Edit .env file with generated key
   ./dev.sh config validate
   ```

2. **Daily Development:**
   ```bash
   ./dev.sh start          # Start environment
   ./dev.sh shell          # Enter container for development
   # Make changes to code
   ./dev.sh test           # Run tests
   ./dev.sh config validate # Validate configuration
   ```

3. **Installing Dependencies:**
   ```bash
   ./dev.sh composer require vlucas/phpdotenv
   ./dev.sh composer install
   ```

4. **Accessing Services:**
   - Development site: http://localhost:8080
   - Demo page: http://localhost:8080/demo.html
   - MySQL (if enabled): localhost:3306

## Container Architecture

The main `web` container runs multiple services via Supervisor:

- **Nginx**: Web server listening on port 80
- **PHP-FPM**: PHP processor communicating via Unix socket
- **Configure Script**: Applies environment-based PHP configuration on startup

### Volume Mounts

- `./public` → `/var/www/html` (web document root)
- `./src` → `/var/www/src` (framework source)
- `./vendor` → `/var/www/vendor` (dependencies)
- `./composer.json` → `/var/www/composer.json` (package definition)
- `./storage/logs` → `/var/log/app` (application logs)

## Troubleshooting

### Container Issues
- Check status: `docker compose ps`
- View logs: `docker compose logs web`
- Rebuild: `docker compose build --no-cache`

### Port Conflicts
If you get port conflicts, edit docker-compose.yml to change port mappings:
```yaml
services:
  web:
    ports:
      - "8081:80"  # Use 8081 instead of 8080
```

### Permission Issues
If you encounter permission issues with storage directories:
```bash
./dev.sh shell
chown -R www-data:www-data /var/log/app
```

### PHP Configuration Issues
PHP settings are configured automatically from environment variables via the `configure-php.sh` script. To debug:
```bash
./dev.sh shell
php -i | grep -E "(memory_limit|upload_max_filesize|opcache)"
```

### MySQL Connection Issues
Ensure MySQL is started with the profile:
```bash
docker compose --profile with-mysql up -d
```

## Production Considerations

When deploying to production:

1. **Environment Variables**: Set `APP_ENV=production` and `APP_DEBUG=false`
2. **OPcache**: Keep enabled but set `opcache.validate_timestamps=0`
3. **Security**: Review and harden Nginx security headers
4. **Volumes**: Use named volumes or bind mounts for persistent data
5. **Secrets**: Use Docker secrets or external secret management
6. **Monitoring**: Add health checks and monitoring endpoints

## Next Steps

With the Docker environment running, you can now:
1. Add database connectivity using the MySQL service
2. Implement the module system
3. Build user authentication
4. Create the API system
5. Add RBAC integration
6. Set up automated testing pipelines
