# Core Database API User Guide

## Overview

The DevFramework Core Database system provides a Moodle-compatible database abstraction layer with modern PHP features. It offers familiar methods like `$DB->get_record()`, `$DB->insert_record()`, etc., making it easy for developers familiar with Moodle to work with.

## Table of Contents

1. [Getting Started](#getting-started)
2. [Configuration](#configuration)
3. [Basic Usage](#basic-usage)
4. [Reading Data](#reading-data)
5. [Writing Data](#writing-data)
6. [Transactions](#transactions)
7. [Global Helper Functions](#global-helper-functions)
8. [Constants](#constants)
9. [Error Handling](#error-handling)
10. [Best Practices](#best-practices)

## Getting Started

### Accessing the Database

The database is available globally through the `$DB` variable or via helper functions:

```php
// Global database instance
global $DB;

// Or using the helper function
$db = db();
```

### Initialization

The database is automatically initialized when the framework starts. You can also manually initialize it:

```php
use DevFramework\Core\Database\DatabaseFactory;

// Initialize database
$DB = DatabaseFactory::initialize();

// Create global $DB variable
DatabaseFactory::createGlobal();
```

## Configuration

Database configuration is handled through environment variables in your `.env` file:

```env
# Database Configuration
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=devframework
DB_USERNAME=devframework
DB_PASSWORD=devframework
DB_PREFIX=dev_
```

Supported database drivers:
- `mysql` - MySQL/MariaDB
- `pgsql` - PostgreSQL
- `sqlite` - SQLite (file-based)

## Basic Usage

### Table Naming

The database system automatically handles table prefixes. When you specify a table name like `'users'`, it becomes `'dev_users'` if your prefix is `'dev_'`.

```php
// This will query the 'dev_users' table if prefix is 'dev_'
$user = $DB->get_record('users', ['id' => 1]);
```

## Reading Data

### Get Single Record

```php
// Get a single record by conditions
$user = $DB->get_record('users', ['email' => 'john@example.com']);

// Get record with specific fields only
$user = $DB->get_record('users', ['id' => 1], '', 'id, name, email');

// Require record to exist (throws exception if not found)
$user = $DB->get_record('users', ['id' => 1], '', '*', MUST_EXIST);
```

**Parameters:**
- `$table` (string) - Table name (without prefix)
- `$conditions` (array) - WHERE conditions as key-value pairs
- `$sort` (string) - ORDER BY clause (optional)
- `$fields` (string) - Fields to select (default: '*')
- `$strictness` (int) - IGNORE_MISSING or MUST_EXIST

### Get Multiple Records

```php
// Get all records
$users = $DB->get_records('users');

// Get records with conditions
$activeUsers = $DB->get_records('users', ['status' => 'active']);

// Get records with sorting
$users = $DB->get_records('users', [], 'name ASC, created_at DESC');

// Get records with limit and offset
$users = $DB->get_records('users', [], 'name ASC', '*', 10, 20); // LIMIT 20 OFFSET 10
```

**Parameters:**
- `$table` (string) - Table name
- `$conditions` (array) - WHERE conditions
- `$sort` (string) - ORDER BY clause
- `$fields` (string) - Fields to select
- `$limitfrom` (int) - OFFSET value
- `$limitnum` (int) - LIMIT value

### Get Records with Raw SQL

```php
// Execute custom SQL query
$results = $DB->get_records_sql(
    "SELECT u.*, p.name as profile_name FROM users u 
     LEFT JOIN profiles p ON u.profile_id = p.id 
     WHERE u.created_at > :date",
    ['date' => '2023-01-01']
);

// With pagination
$results = $DB->get_records_sql($sql, $params, 0, 10); // First 10 records
```

### Get Single Field Value

```php
// Get a specific field value
$userName = $DB->get_field('users', 'name', ['id' => 1]);

// Require field to exist
$email = $DB->get_field('users', 'email', ['id' => 1], MUST_EXIST);
```

### Count Records

```php
// Count all records
$totalUsers = $DB->count_records('users');

// Count with conditions
$activeUsers = $DB->count_records('users', ['status' => 'active']);
```

### Check if Record Exists

```php
// Check if record exists
$exists = $DB->record_exists('users', ['email' => 'john@example.com']);

if ($exists) {
    echo "User already exists!";
}
```

## Writing Data

### Insert Records

```php
// Insert new record (returns auto-increment ID)
$userId = $DB->insert_record('users', [
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'status' => 'active',
    'created_at' => date('Y-m-d H:i:s')
]);

// Insert without returning ID
$success = $DB->insert_record('users', $userData, false);

// Insert using object
$user = new stdClass();
$user->name = 'Jane Doe';
$user->email = 'jane@example.com';
$userId = $DB->insert_record('users', $user);
```

### Update Records

```php
// Update record (requires 'id' field)
$success = $DB->update_record('users', [
    'id' => 1,
    'name' => 'John Smith',
    'updated_at' => date('Y-m-d H:i:s')
]);

// Update using object
$user = $DB->get_record('users', ['id' => 1]);
$user->name = 'Updated Name';
$success = $DB->update_record('users', $user);
```

### Set Single Field

```php
// Update a single field for records matching conditions
$success = $DB->set_field('users', 'status', 'inactive', ['last_login' => null]);

// Set field with boolean value (automatically converted to 1/0)
$success = $DB->set_field('users', 'verified', true, ['id' => 1]);
```

### Delete Records

```php
// Delete records (conditions required for safety)
$success = $DB->delete_records('users', ['status' => 'deleted']);

// Delete specific record
$success = $DB->delete_records('users', ['id' => 1]);

// Note: Empty conditions array will throw an exception to prevent accidental table truncation
```

## Transactions

```php
// Manual transaction handling
try {
    $DB->start_transaction();
    
    $userId = $DB->insert_record('users', $userData);
    $DB->insert_record('user_profiles', ['user_id' => $userId, 'bio' => 'Hello']);
    
    $DB->commit_transaction();
} catch (Exception $e) {
    $DB->rollback_transaction();
    throw $e;
}
```

## Global Helper Functions

The framework provides convenient global functions that wrap the database methods:

```php
// Get single record
$user = db_get_record('users', ['id' => 1]);

// Get multiple records
$users = db_get_records('users', ['status' => 'active']);

// Insert record
$id = db_insert_record('users', $userData);

// Update record
$success = db_update_record('users', $userData);

// Delete records
$success = db_delete_records('users', ['id' => 1]);

// Count records
$count = db_count_records('users');

// Get database instance
$database = db();
```

## Constants

### Strictness Levels

```php
IGNORE_MISSING  // Don't throw error if record doesn't exist (default)
MUST_EXIST      // Throw exception if record doesn't exist
```

### SQL Comparison Operators

```php
SQL_COMPARE_EQUAL           // '='
SQL_COMPARE_NOT_EQUAL       // '!='
SQL_COMPARE_GREATER         // '>'
SQL_COMPARE_GREATER_EQUAL   // '>='
SQL_COMPARE_LESS            // '<'
SQL_COMPARE_LESS_EQUAL      // '<='
SQL_COMPARE_LIKE            // 'LIKE'
SQL_COMPARE_NOT_LIKE        // 'NOT LIKE'
```

### Transaction Isolation Levels

```php
READ_UNCOMMITTED
READ_COMMITTED
REPEATABLE_READ
SERIALIZABLE
```

## Error Handling

The database system throws `DatabaseException` for various error conditions:

```php
use DevFramework\Core\Database\DatabaseException;

try {
    $user = $DB->get_record('users', ['id' => 999], '', '*', MUST_EXIST);
} catch (DatabaseException $e) {
    echo "Database error: " . $e->getMessage();
}
```

Common exceptions:
- **Connection failed** - Database server not available
- **Record not found** - When using MUST_EXIST strictness
- **Invalid arguments** - Missing required parameters
- **SQL errors** - Malformed queries or constraint violations

## Best Practices

### 1. Use Prepared Statements (Automatic)

The database layer automatically uses prepared statements for all queries, protecting against SQL injection:

```php
// Safe - parameters are automatically escaped
$users = $DB->get_records('users', ['name' => $userInput]);
```

### 2. Handle Database Connections Gracefully

```php
// Check if database is available
try {
    $count = $DB->count_records('users');
} catch (DatabaseException $e) {
    // Handle gracefully - maybe show cached data or error message
    error_log("Database unavailable: " . $e->getMessage());
}
```

### 3. Use Transactions for Related Operations

```php
// Group related database operations
$DB->start_transaction();
try {
    $orderId = $DB->insert_record('orders', $orderData);
    
    foreach ($items as $item) {
        $item['order_id'] = $orderId;
        $DB->insert_record('order_items', $item);
    }
    
    $DB->commit_transaction();
} catch (Exception $e) {
    $DB->rollback_transaction();
    throw $e;
}
```

### 4. Use Appropriate Field Selection

```php
// Don't select unnecessary fields
$users = $DB->get_records('users', [], '', 'id, name, email');

// Instead of
$users = $DB->get_records('users'); // Selects all fields
```

### 5. Use Helper Functions for Common Operations

```php
// Use global helpers for cleaner code
$user = db_get_record('users', ['id' => 1]);

// Instead of
global $DB;
$user = $DB->get_record('users', ['id' => 1]);
```

### 6. Validate Data Before Database Operations

```php
// Validate before inserting
if (empty($userData['email']) || !filter_var($userData['email'], FILTER_VALIDATE_EMAIL)) {
    throw new InvalidArgumentException('Valid email required');
}

$userId = $DB->insert_record('users', $userData);
```

### 7. Use Meaningful Table and Field Names

```php
// Good
$DB->get_records('user_sessions', ['expires_at' => time()]);

// Avoid
$DB->get_records('us', ['exp' => time()]);
```

### 8. Handle Boolean Values Properly

```php
// Boolean values are automatically converted to 1/0 for MySQL compatibility
$DB->insert_record('users', [
    'name' => 'John',
    'is_active' => true,     // Becomes 1
    'is_verified' => false   // Becomes 0
]);
```

## Advanced Usage

### Raw PDO Access

For advanced operations, you can access the raw PDO connection:

```php
$pdo = $DB->get_connection();
$stmt = $pdo->prepare("CALL stored_procedure(?)");
$stmt->execute([$param]);
```

### Database Information

```php
// Get database server information
$info = $DB->get_server_info();
echo "Database: " . $info['description'];
echo "Version: " . $info['version'];
echo "Driver: " . $info['driver'];

// Get table list
$tables = $DB->get_tables();
foreach ($tables as $table) {
    echo "Table: " . $table . "\n";
}
```

### Custom SQL Execution

```php
// Execute custom SQL
$stmt = $DB->execute(
    "UPDATE users SET last_seen = NOW() WHERE id = :id", 
    ['id' => $userId]
);

echo "Affected rows: " . $stmt->rowCount();
```

---

This API provides a robust, secure, and familiar interface for database operations while maintaining compatibility with Moodle-style database access patterns. The automatic parameter binding, connection management, and error handling make it safe and easy to use in production applications.
