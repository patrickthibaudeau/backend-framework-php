# DevFramework - Docker Development Environment

This guide explains how to develop your PHP framework using Docker containers.

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

## Development Commands

### Environment Management
- `./dev.sh start` - Start all containers (PHP, MySQL, PostgreSQL, Redis)
- `./dev.sh stop` - Stop all containers
- `./dev.sh build` - Build/rebuild containers
- `./dev.sh status` - Show container status
- `./dev.sh clean` - Clean up containers and volumes

### Development Tools
- `./dev.sh shell` - Open interactive shell in PHP container
- `./dev.sh logs` - View PHP container logs
- `./dev.sh test` - Run configuration system tests

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
docker compose logs -f php

# Execute commands in container
docker compose exec php php config-simple.php validate

# Enter container shell
docker compose exec php bash
```

## Services

### PHP Container
- **Name**: devframework-php
- **PHP Version**: 8.2
- **Extensions**: PDO, MySQL, PostgreSQL, BCMath, OpenSSL
- **Tools**: Composer, Git

### MySQL Database
- **Host**: localhost:3306 (from host) or mysql:3306 (from containers)
- **Database**: devframework
- **Username**: devframework
- **Password**: devframework
- **Root Password**: root

### PostgreSQL Database
- **Host**: localhost:5432 (from host) or postgres:5432 (from containers)
- **Database**: devframework
- **Username**: devframework
- **Password**: devframework

### Redis Cache
- **Host**: localhost:6379 (from host) or redis:6379 (from containers)
- **No authentication required**

## File Structure

```
devframework/
├── docker-compose.yml    # Container orchestration
├── Dockerfile           # PHP container definition
├── dev.sh              # Development script
├── .env.example         # Environment template
├── .env                # Your environment (created by init)
├── src/                # Framework source code
│   └── Core/
│       └── Config/     # Configuration system
├── storage/            # Framework storage
│   ├── logs/          # Log files
│   └── cache/         # Cache files
└── vendor/            # Composer dependencies
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

## Troubleshooting

### Container Issues
- Check status: `docker compose ps`
- View logs: `docker compose logs php`
- Rebuild: `docker compose build --no-cache`

### Port Conflicts
If you get port conflicts, edit docker-compose.yml to change port mappings:
```yaml
ports:
  - "3307:3306"  # Use 3307 instead of 3306
```

### Permission Issues
If you encounter permission issues with storage directories:
```bash
./dev.sh shell
chown -R www-data:www-data /app/storage
```

## Next Steps

With the configuration system working, you can now:
1. Add database connectivity
2. Implement the module system
3. Build user authentication
4. Create the API system
5. Add RBAC integration
