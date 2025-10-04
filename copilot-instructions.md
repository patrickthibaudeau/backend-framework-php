# DevFramework Copilot Instructions

## Project Context
- **Project**: DevFramework - Modern PHP Backend Framework
- **Language**: PHP 8.4+ (strict requirement)
- **Namespace**: `DevFramework\` (PSR-4 autoloading)
- **Architecture**: Modular framework with singleton patterns for core services
- **Environment**: Docker-based with Nginx + PHP-FPM via Supervisor
- **Database**: MySQL 8.4 with Moodle-compatible abstraction layer
- **Dependency Manager**: Composer

## PHP Standards & Style
- **Always** start PHP files with `<?php` and `declare(strict_types=1);`
- Follow PSR-12 coding standards strictly
- Use strict scalar type hints for all parameters and return types
- Prefer explicit return types over mixed/void when possible
- Use promoted constructor properties where appropriate
- Use `readonly` properties for immutable data
- Prefer early returns to reduce nesting depth

## Architecture Patterns
- **Singleton Pattern**: Used for core services (Database, Configuration, AuthenticationManager)
- **Provider Pattern**: Used for authentication providers implementing `AuthProviderInterface`
- **Factory Pattern**: Used for database connections via `DatabaseFactory`
- **Module System**: Extensible modules in `src/modules/` with version control
- **Configuration Management**: Centralized config via `Configuration` class with `.env` support

## Framework-Specific Guidelines

### Database Layer
- Use the Moodle-compatible `Database` class methods:
  - `get_record()`, `get_records()` for SELECT queries
  - `insert_record()`, `update_record()`, `delete_records()` for DML
  - Always use parameterized queries via the abstraction layer
- Table names use configurable prefix via `$tablePrefix`
- Return `stdClass` objects for database records (Moodle compatibility)

### Authentication System
- Use `AuthenticationManager` singleton for all auth operations
- Implement new auth methods via `AuthProviderInterface`
- Available providers: Manual, LDAP, OAuth, SAML2
- User objects extend the `User` class
- Authentication exceptions extend `AuthenticationException`

### Configuration System
- Use `Configuration::getInstance()` for config access
- Support type casting: `getBool()`, `getInt()`, `getFloat()`, `getString()`
- Validate configuration via `ConfigValidator`
- Use `SimpleConfiguration` for minimal setups
- All config keys should have defaults and validation

### Module System
- Modules live in `src/modules/{ModuleName}/`
- Each module requires `version.php` with version info
- Language files in `lang/{locale}/strings.php` format
- Use `ModuleManager` for module lifecycle operations
- Validate modules via `ModuleValidator`

## Docker & Development
- Use `docker compose` instead of `docker-compose` (v2 syntax)
- Development via `./dev.sh` script for common tasks
- Configuration files in `docker/` directory
- Use PHP-FPM with Nginx via Supervisor
- Mount volumes for development (see docker-compose.yml)

## Security Guidelines
- **Never** log credentials, tokens, or sensitive data
- Always escape output when rendering to HTML
- Use parameterized queries exclusively
- Validate all external input via configuration validators
- Implement proper session management in auth layer
- Use HTTPS in production (configure via Nginx)

## Error Handling
- Throw domain-specific exceptions (extend base framework exceptions)
- Use try-catch blocks judiciously - don't catch and ignore
- Log errors appropriately without exposing internals
- Provide meaningful error messages for developers
- Use `DatabaseException` for database-related errors

## Testing Guidelines
- Use PHPUnit for all tests (configured in composer.json)
- Test files in `tests/` directory with `DevFramework\Tests\` namespace
- Mock external dependencies (database, file system, network)
- Test configuration validation and error conditions
- Integration tests for module interactions

## Code Organization
- Core framework code in `src/Core/`
- Modules in `src/modules/`
- Public web files in `public/`
- CLI tools via `console.php`
- Configuration examples and tests in root directory

## Performance Considerations
- Use singleton pattern for expensive-to-create objects
- Lazy-load database connections
- Cache configuration values after first load
- Use prepared statements for repeated queries
- Minimize memory usage in CLI scripts

## Documentation Standards
- PHPDoc blocks for all public methods and classes
- Document configuration options and their types
- Include usage examples for complex APIs
- Maintain README.md with current feature status
- Document module APIs and integration points

## Git & Commits
- Use conventional commits: `feat:`, `fix:`, `refactor:`, `docs:`, `test:`
- No generated files or vendor dependencies in commits
- Keep commits focused and atomic
- Update documentation with API changes

## Environment Variables
- Define all config in `.env` file (copy from `env.example`)
- Use type-safe getters from Configuration class
- Validate required environment variables on startup
- Document new environment variables in env.example

## CLI Tools
- Use `console.php` for command-line operations
- Commands in `src/Core/Console/` namespace
- Support for configuration and module management
- Proper exit codes and error handling

## Dependencies
- Minimize external dependencies
- Use Composer for dependency management
- Pin versions for stability in production
- Document rationale for new dependencies

## Logging
- Use structured logging where possible
- No `var_dump()` or `print_r()` in committed code
- Log to appropriate levels (error, warning, info, debug)
- Store logs in `storage/logs/` directory

## Output Preferences for AI Assistant
- When modifying existing code, show only the changed portions
- Use the framework's existing patterns and conventions
- Suggest improvements that align with the modular architecture
- Always validate configuration and handle errors appropriately
- Prefer dependency injection over global state where possible

