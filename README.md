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

# Notification System & Tailwind CSS Integration

The framework now includes a lightweight, framework-wide notification (flash message) system plus built-in Tailwind CSS CDN helper for rapid UI prototyping.

### Tailwind CSS Usage

You can include Tailwind via CDN anywhere in your views:

```php
echo tailwind_cdn(); // Inserts the CDN script + default config
```

Optional custom configuration (merged with defaults):
```php
echo tailwind_cdn([
    'theme' => [
        'extend' => [
            'colors' => [ 'primary' => '#0ea5e9' ]
        ]
    ]
]);
```

### Notifications API

Use the global `notification()` helper to queue messages anywhere (controllers, scripts, modules):

```php
notification()->success('Profile updated');
notification()->error('Invalid password');
notification()->warning('Storage reaching capacity');
notification()->info('Background job scheduled');
notification()->debug('Raw payload: ' . json_encode($payload));
```

Each method accepts an optional options array:
```php
notification()->success('Saved!', [
    'title' => 'Success',
    'dismissible' => true,
    'data' => ['id' => 123]
]);
```

### Rendering Notifications

In a PHP view or page after including `helpers.php`:
```php
echo tailwind_cdn();
// ... your layout/header ...

// Render and consume flash notifications
echo render_notifications();
```

`render_notifications()` consumes (removes) notifications by default. To render without consuming:
```php
echo render_notifications(false); // Leaves them in the queue
```

### Behavior
- Web (HTTP) requests: notifications persist across redirects using PHP sessions.
- CLI: notifications are stored in-memory for the duration of the process (handy for tests/scripts).
- Dismiss buttons are included (client-side removal only).
- Output is Tailwind utility class based components (no additional CSS required).

### Demo Pages / Scripts
- Web demo: `public/notifications-demo.php`
- CLI test: `php test-notifications.php`

### Example Demo Page Snippet
```php
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/Core/helpers.php';
notification()->success('Logged in successfully');
notification()->error('Could not load profile');
?><!DOCTYPE html>
<html><head><?= tailwind_cdn(); ?></head>
<body class="p-8 max-w-3xl mx-auto">
  <h1 class="text-2xl font-bold mb-4">Notifications Demo</h1>
  <?= render_notifications(); ?>
</body></html>
```

### Extending
You can customize rendering by retrieving raw data:
```php
$all = notification()->all(false); // Get without consuming
foreach ($all as $note) {
    // Custom output
}
```

## Mustache Templating System

A core Output component using Mustache is available globally through the `$OUTPUT` variable and helper functions.

### Naming Convention
Templates are referenced by `component_template` (component + underscore + template file name without extension). The component maps to either a Core component directory (PascalCase) under `src/Core/` or a Module under `modules/`.

Example:
- File: `src/Core/Auth/templates/method.mustache`
- Call: `$OUTPUT->renderFromTemplate('auth_method', ['method' => 'Password Login']);`

### Directory Layout
```
src/Core/Auth/templates/*.mustache
src/Core/Notifications/templates/*.mustache
modules/YourModule/templates/*.mustache
```

### Usage
```php
require 'vendor/autoload.php';
require 'src/Core/helpers.php';

global $OUTPUT; // or use output() helper

$html = $OUTPUT->renderFromTemplate('auth_method', [
    'method' => 'Single Sign-On'
]);

echo $html;
```

### Helper Wrapper
```php
echo render_template('auth_method', ['method' => 'API Token']);
```

### Adding Custom Roots (Themes)
```php
output()->addRoot('theme', __DIR__ . '/themes/default');
// Then a file themes/default/Auth/method.mustache could override rendering
```

### Escaping & Security
All values are HTML-escaped via `htmlspecialchars` unless you use triple braces `{{{ raw_html }}}` in Mustache (ensure you trust that content).

### Error Handling
If a template cannot be located, an Exception is thrown identifying the searched path.

### Example Template (`method.mustache`)
```mustache
<div class="auth-method">
  <strong>Authentication Method:</strong> {{method}}
</div>
```

### Compiled Template Caching
Compiled Mustache templates are cached (when possible) under `storage/cache/mustache`.

Helpers / API:
```php
output_enable_cache();        // Enable (rebuild engine if Mustache present)
output_disable_cache();       // Disable caching
$dir = output_cache_dir();    // Get current cache directory (or null)
$removed = output_clear_cache(); // Delete compiled template cache files
output()->setCacheDir('/custom/path/mustache'); // Use custom cache directory
```

Behavior:
- On first use, the framework attempts to create `storage/cache/mustache`.
- If not writable, caching is disabled automatically (falls back to runtime compilation).
- Changing cache directory requires it to exist or be creatable and writable.
- Clearing cache only removes generated `*.php` compiled templates, preserving the directory.

Error Handling:
- Attempting to set an unwritable directory throws an Exception.
- Rendering without installing dependencies (missing `mustache/mustache`) will throw a clear exception when the engine is built.

## Output Rendering & Theme Layout Helpers

The framework includes an `Output` manager (Mustache-based) plus convenience layout helpers for the default admin theme.

### Header & Footer Convenience Methods
Two methods simplify page assembly without manually referencing template filenames:

```php
$OUTPUT->header(array $data = []): string
$OUTPUT->footer(array $data = []): string
```

These wrap the default theme's `header.mustache` and `footer.mustache` partials located under:
```
src/Core/Theme/default/templates/
```

#### Typical Use
```php
<?php
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/Core/helpers.php';

global $OUTPUT;

echo $OUTPUT->header([
    'page_title' => 'Dashboard',       // Appears in <title>
    'site_name'  => 'DevFramework',
    'nav' => [                          // Navigation items (optional)
        ['url' => '/index.php', 'label' => 'Home'],
        ['url' => '/dashboard.php', 'label' => 'Dashboard', 'active' => true],
    ],
    'user' => [ 'username' => 'admin' ],
    'logout_url' => '/logout.php',
    'meta_description' => 'Dashboard overview'
]);

// --- Your page content here ---

echo '<h1 class="text-2xl font-semibold">Welcome!</h1>';

echo $OUTPUT->footer([
    'footer_links' => [
        ['url' => '/privacy.php', 'label' => 'Privacy'],
        ['url' => '/terms.php',   'label' => 'Terms'],
    ],
]);
```

#### Supported Header Data Keys
| Key              | Type      | Description |
|------------------|-----------|-------------|
| `page_title`     | string    | Prepended to the document title. |
| `site_name`      | string    | Displayed next to the logo placeholder. |
| `nav`            | array[]   | Each item: `url`, `label`, optional `active` (truthy). |
| `user`           | array     | e.g. `['username' => 'admin']` displayed in header right side. |
| `logout_url`     | string    | If provided, a logout link is shown. |
| `meta_description` | string  | Populates `<meta name="description">`. |
| `extra_head`     | string (HTML) | Injected raw before closing `</head>`. |

#### Supported Footer Data Keys
| Key            | Type      | Description |
|----------------|-----------|-------------|
| `footer_links` | array[]   | Each item: `url`, `label`. |
| `current_year` | int       | Auto-filled if omitted. |
| `extra_footer` | string (HTML) | Injected before closing `</body>`. |
| `site_name`    | string    | Used in copyright line if supplied. |

#### Navigation Example
```php
$nav = [
    ['url' => '/index.php', 'label' => 'Home'],
    ['url' => '/users.php', 'label' => 'Users'],
    ['url' => '/settings.php', 'label' => 'Settings', 'active' => true],
];
echo $OUTPUT->header(['nav' => $nav, 'site_name' => 'DevFramework']);
```

#### Injecting Extra Head / Footer Content
```php
echo $OUTPUT->header([
  'site_name' => 'DevFramework',
  'extra_head' => '<link rel="preconnect" href="https://example.cdn" />'
]);
// ... content ...
echo $OUTPUT->footer([
  'extra_footer' => '<script>console.log("Page loaded")</script>'
]);
```

#### When to Use header()/footer() vs `renderFromTemplate('theme_body', ...)`
| Scenario | Use header()/footer() | Use theme_body |
|----------|----------------------|----------------|
| Streaming / progressive output | ✅ Yes | ❌ Not ideal |
| Need full layout in one render | ❌ | ✅ Yes |
| Inject content between start/end easily | ✅ | ⚠ Partial (must pass all data up front) |
| Template composition with Mustache partials only | ⚠ | ✅ |

`theme_body` internally includes the header and footer partials, and is convenient when the **entire** page context is available before rendering.

#### Tailwind & Alpine.js
The default header automatically includes **Tailwind CSS via CDN**. The footer includes **Alpine.js** (deferred). If you introduce a build pipeline later (e.g., Vite, Webpack), you can replace or disable these by editing:
```
src/Core/Theme/default/templates/header.mustache
src/Core/Theme/default/templates/footer.mustache
```

#### Example Page Included
A full demonstration page exists at:
```
public/theme-example.php
```
It illustrates metric cards, tables, alerts, and environment info using the new helpers.

#### Customizing the Theme
To override or extend:
1. Copy the default theme templates to a new theme directory (future multi-theme support can register alternative roots).
2. Add new partials (e.g., `sidebar.mustache`) and render them inside your content section.
3. Add keys to your data arrays and reference them in templates using Mustache.

#### Common Patterns
```php
// Flash messages (build array and inject into body HTML or create a Mustache partial)
$alerts = [
  ['class' => 'bg-blue-50 border-blue-300 text-blue-700', 'message' => 'Welcome back!']
];

// Build body content manually (or via a separate template)
$body = '<div class="space-y-4">';
foreach ($alerts as $a) {
  $body .= '<div class="rounded border px-4 py-2 ' . htmlspecialchars($a['class']) . '">' . htmlspecialchars($a['message']) . '</div>';
}
$body .= '</div>';

echo $OUTPUT->header(['page_title' => 'Messages']);
echo $body;
echo $OUTPUT->footer();
```

> Tip: For large, repeatable sections, consider creating a dedicated Mustache template and calling `renderFromTemplate()` in place of inline HTML.

## UI Theme & Output System

The framework includes a default admin theme with Tailwind CSS, reusable Mustache templates, and convenience rendering helpers.

### Header & Footer Rendering
Use the global `$OUTPUT` object (auto-initialized in `helpers.php`).

```php
require_once __DIR__ . '/../src/Core/helpers.php';

echo $OUTPUT->header([
  'page_title' => 'Dashboard',       // Optional, becomes <title> prefix
  'site_name'  => 'DevFramework',    // Optional, defaults can be supplied centrally
  // 'nav'      => [...],             // Optional override of top nav (see Navigation section)
  // 'drawer_items' => [...],        // Optional override of drawer nav items
]);

// Page content here...

echo $OUTPUT->footer([
  'footer_links' => [
    ['url' => '/privacy.php', 'label' => 'Privacy'],
    ['url' => '/terms.php',   'label' => 'Terms'],
  ]
]);
```

If you omit `nav` or `drawer_items`, defaults are injected automatically by the Navigation Manager.

### Default Theme Structure
```
src/Core/Theme/default/
  templates/          # Mustache partials (header, footer, left_drawer, navigation, icons)
  lang/en/strings.php # Theme language strings (used by {{#str}} helper)
  navigation.php      # Default nav + drawer configuration
  version.php         # Theme plugin version (stored in config_plugins)
```

### Partials Overview
- `header.mustache` – Outer HTML, includes `<head>`, navigation, icons sprite, left drawer, `<main>` start.
- `footer.mustache` – Closes layout, renders footer & scripts.
- `navigation.mustache` – Top bar branding + primary links.
- `left_drawer.mustache` – Collapsible side navigation (Alpine.js driven state).
- `icons.mustache` – Inline SVG symbol sprite (Lucide subset) referenced with `<use href="#icon-name"/>`.

### Icons (Lucide Sprite)
Add additional icons by editing `icons.mustache` and inserting more `<symbol>` blocks:
```mustache
<symbol id="icon-user" viewBox="0 0 24 24" ...>
  <path d="..." />
</symbol>
```
Then reference:
```html
<svg class="h-5 w-5"><use href="#icon-user" /></svg>
```

### Accessibility Patterns
- Drawer toggle uses `aria-label` that switches between localized expand/collapse strings.
- Screen‑reader only text (`sr-only`) is present for stateful controls.
- Provide descriptive labels for custom icons where semantics matter; decorative icons should use `aria-hidden="true"`.

## Localization in Templates ({{#str}})
You can localize strings directly inside Mustache templates with a section lambda:
```mustache
<h1>{{#str}}theme_name, theme_default{{/str}}</h1>
<p>{{#str}}welcome_message, auth, name=Alice{{/str}}</p>
```
Format: `{{#str}}key, component[, param=value, param2=value]{{/str}}`
- `component` for theme strings: `theme_default`
- `component` for modules: module directory name (e.g. `auth`, `test`)
- Additional `param=value` pairs replace `{param}` placeholders inside the language string.
Fallback order: specific component → `theme_default` → raw key.

## Navigation System
Navigation is centrally managed; no need to redefine arrays on every page.

### Default Configuration
Defined in `src/Core/Theme/default/navigation.php`:
```php
return [
  'nav' => [ ['url'=>'/index.php','label'=>'Home'], ... ],
  'drawer' => [ ['url'=>'/dashboard.php','label'=>'Dashboard','icon'=>'<svg...>'], ... ],
];
```
Active state is auto-detected by matching the current script path with each item’s URL.

### Global Helper Functions
```php
nav_config();                       // Get current top nav
nav_config([['url'=>'/x','label'=>'X']]);       // Replace nav
nav_config([['url'=>'/extra','label'=>'Extra']], true); // Merge/append
add_nav_item(['url'=>'/faq.php','label'=>'FAQ']);       // Append single item

drawer_config();                   // Get current drawer items
drawer_config([['url'=>'/tools.php','label'=>'Tools']], true); // Merge
```
If you pass custom `nav` or `drawer_items` arrays directly to `$OUTPUT->header()`, those override the defaults for that render only.

### Navigation Caching (APCu)
Navigation configuration is cached to reduce file I/O.
- Enabled automatically if APCu is available and not disabled.
- Uses file modification time of `navigation.php` for invalidation.

Environment vars:
```
NAV_CACHE_DISABLE=1   # Disable nav caching
NAV_CACHE_TTL=600     # Override default TTL (seconds, default 300)
```
Programmatic cache clearing:
```php
\DevFramework\Core\Theme\NavigationManager::getInstance()->clearCache();
```

## Directory Root Helper (dirroot)
Every template automatically receives `{{dirroot}}` pointing to the installation root. Use it for constructing absolute filesystem paths (NOT necessarily public URLs):
```mustache
<img src="/assets/logo.svg" alt="Logo"> {{! for web URLs prefer route-relative paths }}
```
If you need a base public URL, introduce (and pass) a `base_url` variable separately.

## Adding a New Page (Minimal Example)
```php
<?php
require_once __DIR__ . '/../src/Core/helpers.php';

// Optionally extend navigation for just this request
add_nav_item(['url' => '/reports.php', 'label' => 'Reports']);

echo $OUTPUT->header(['page_title' => 'Reports']);
?>
  <h1 class="text-2xl font-bold mb-4">Reports</h1>
  <p>Reports overview content...</p>
<?php
echo $OUTPUT->footer();
```

## Extending Icons
1. Add `<symbol>` in `icons.mustache`.
2. Reference with `<use href="#icon-yourid" />`.
3. Keep stroke properties consistent for visual coherence.

## Overriding Theme Templates
Override search order by registering extra template roots (future enhancement) or adding new partials inside the default theme. Current resolution order (simplified):
1. `src/Core/<Component>/templates/<name>.mustache`
2. `src/Core/<component>/templates/<name>.mustache` (lowercase)
3. `src/Core/<Component>/default/templates/<name>.mustache` (theme path)
4. `modules/<Component>/templates/<name>.mustache`
5. `modules/<component>/templates/<name>.mustache`

## Summary of Newly Added Features
- Default Tailwind-based admin theme (header, footer, navigation, left drawer)
- Icons sprite partial (Lucide subset) and `<use>` referencing
- Automatic nav & drawer injection via `NavigationManager`
- APCu caching for navigation (mtime-invalidated)
- Global helpers: `nav_config`, `drawer_config`, `add_nav_item`
- Localization lambda: `{{#str}}key, component[, param=value]{{/str}}`
- Accessibility patterns (aria-label, sr-only) in interactive components
- Automatic `dirroot` injection into template data
- Theme version install/upgrade tracked in `config_plugins`

