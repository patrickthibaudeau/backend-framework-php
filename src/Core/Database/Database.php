<?php

namespace DevFramework\Core\Database;

use PDO;
use PDOException;
use PDOStatement;
use DevFramework\Core\Config\Configuration;
use InvalidArgumentException;
use stdClass;

/**
 * Moodle-compatible database abstraction layer
 * Provides familiar $DB->get_record(), $DB->insert_record() etc. methods
 */
class Database
{
    private static ?Database $instance = null;
    private ?PDO $connection = null;
    private array $config = [];
    private string $tablePrefix = '';
    private bool $debugMode = false;

    private function __construct()
    {
        // Private constructor for singleton pattern
    }

    /**
     * Get the singleton instance
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Initialize database connection
     */
    public function connect(): void
    {
        if ($this->connection !== null) {
            return;
        }

        $config = Configuration::getInstance();

        $this->config = [
            'driver' => $config->get('database.default', 'mysql'),
            'host' => $config->get('database.connections.mysql.host', 'localhost'),
            'port' => $config->get('database.connections.mysql.port', 3306),
            'database' => $config->get('database.connections.mysql.database', ''),
            'username' => $config->get('database.connections.mysql.username', ''),
            'password' => $config->get('database.connections.mysql.password', ''),
            'charset' => $config->get('database.connections.mysql.charset', 'utf8mb4'),
            'options' => $config->get('database.connections.mysql.options', [])
        ];

        $this->tablePrefix = $config->get('database.prefix', '');
        $this->debugMode = $config->get('app.debug', false);

        // Don't attempt connection if required database config is missing
        if (empty($this->config['database']) || empty($this->config['username'])) {
            throw new DatabaseException("Database configuration is incomplete. Please check your .env file or start MySQL with: docker compose --profile with-mysql up -d");
        }

        try {
            $dsn = $this->buildDsn();

            // Build connection options with safer charset handling
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_TIMEOUT => 5, // Add timeout to fail fast if MySQL isn't available
            ];

            // Only add charset options for MySQL and only if charset is specified
            if ($this->config['driver'] === 'mysql' && !empty($this->config['charset'])) {
                // Use a safer charset - utf8 instead of utf8mb4 if there are issues
                $charset = $this->config['charset'] === 'utf8mb4' ? 'utf8' : $this->config['charset'];
                $options[PDO::MYSQL_ATTR_INIT_COMMAND] = "SET NAMES {$charset}";
            }

            // Only merge valid PDO options from config (filter out invalid ones)
            $configOptions = $this->config['options'] ?? [];
            if (is_array($configOptions)) {
                foreach ($configOptions as $key => $value) {
                    // Only allow valid PDO attribute constants
                    if (is_int($key) && defined('PDO::' . $key)) {
                        $options[$key] = $value;
                    }
                }
            }

            $this->connection = new PDO(
                $dsn,
                $this->config['username'],
                $this->config['password'],
                $options
            );

            // Set charset after connection if MySQL
            if ($this->config['driver'] === 'mysql' && !empty($this->config['charset'])) {
                $charset = $this->config['charset'] === 'utf8mb4' ? 'utf8' : $this->config['charset'];
                $this->connection->exec("SET NAMES {$charset}");
            }

        } catch (PDOException $e) {
            // Provide more helpful error message for common connection issues
            $message = $e->getMessage();
            if (strpos($message, 'getaddrinfo') !== false || strpos($message, 'Name or service not known') !== false) {
                throw new DatabaseException("MySQL container is not running. Start it with: docker compose --profile with-mysql up -d", 0, $e);
            }
            throw new DatabaseException("Database connection failed: " . $message, 0, $e);
        }
    }

    /**
     * Get a single record from database
     */
    public function get_record(string $table, array $conditions = [], string $sort = '', string $fields = '*', int $strictness = IGNORE_MISSING): ?stdClass
    {
        $records = $this->get_records($table, $conditions, $sort, $fields, 0, 1);

        if (empty($records)) {
            if ($strictness === MUST_EXIST) {
                throw new DatabaseException("Record not found in table '{$table}'");
            }
            return null;
        }

        return reset($records);
    }

    /**
     * Get multiple records from database
     */
    public function get_records(string $table, array $conditions = [], string $sort = '', string $fields = '*', int $limitfrom = 0, int $limitnum = 0): array
    {
        $sql = "SELECT {$fields} FROM {$this->addPrefix($table)}";
        $params = [];

        if (!empty($conditions)) {
            $whereClause = $this->buildWhereClause($conditions, $params);
            $sql .= " WHERE {$whereClause}";
        }

        if (!empty($sort)) {
            $sql .= " ORDER BY {$sort}";
        }

        if ($limitnum > 0) {
            $sql .= " LIMIT {$limitnum}";
            if ($limitfrom > 0) {
                $sql .= " OFFSET {$limitfrom}";
            }
        }

        return $this->get_records_sql($sql, $params);
    }

    /**
     * Get records using raw SQL
     */
    public function get_records_sql(string $sql, array $params = [], int $limitfrom = 0, int $limitnum = 0): array
    {
        if ($limitnum > 0 && strpos(strtoupper($sql), 'LIMIT') === false) {
            $sql .= " LIMIT {$limitnum}";
            if ($limitfrom > 0) {
                $sql .= " OFFSET {$limitfrom}";
            }
        }

        $stmt = $this->execute($sql, $params);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Get a single field value
     */
    public function get_field(string $table, string $return, array $conditions = [], int $strictness = IGNORE_MISSING): mixed
    {
        // Pass parameters in correct order: table, conditions, sort='', fields=$return, strictness
        $record = $this->get_record($table, $conditions, '', $return, $strictness);
        return $record ? $record->$return : null;
    }

    /**
     * Set a single field value for records matching conditions
     */
    public function set_field(string $table, string $fieldname, mixed $value, array $conditions = []): bool
    {
        if (empty($conditions)) {
            throw new InvalidArgumentException("Set field requires conditions to prevent updating entire table");
        }

        // Convert boolean values to integers for MySQL compatibility
        if (is_bool($value)) {
            $value = $value ? 1 : 0;
        }

        $params = [];
        $whereClause = $this->buildWhereClause($conditions, $params);

        // Add the field value parameter
        $params[":set_{$fieldname}"] = $value;

        $sql = "UPDATE {$this->addPrefix($table)} SET {$fieldname} = :set_{$fieldname} WHERE {$whereClause}";

        $stmt = $this->execute($sql, $params);
        return $stmt->rowCount() > 0;
    }

    /**
     * Insert a new record
     */
    public function insert_record(string $table, object|array $dataObject, bool $returnId = true, bool $bulk = false): int|bool
    {
        $data = (array) $dataObject;

        if (empty($data)) {
            throw new InvalidArgumentException("Cannot insert empty record");
        }

        // Convert boolean values to integers for MySQL compatibility
        foreach ($data as $key => $value) {
            if (is_bool($value)) {
                $data[$key] = $value ? 1 : 0;
            }
        }

        $fields = array_keys($data);
        $placeholders = ':' . implode(', :', $fields);
        $fieldList = implode(', ', $fields);

        $sql = "INSERT INTO {$this->addPrefix($table)} ({$fieldList}) VALUES ({$placeholders})";

        $params = [];
        foreach ($data as $key => $value) {
            $params[":{$key}"] = $value;
        }

        $this->execute($sql, $params);

        return $returnId ? (int) $this->connection->lastInsertId() : true;
    }

    /**
     * Update existing records
     */
    public function update_record(string $table, object|array $dataObject, bool $bulk = false): bool
    {
        $data = (array) $dataObject;

        if (!isset($data['id'])) {
            throw new InvalidArgumentException("Update requires 'id' field");
        }

        $id = $data['id'];
        unset($data['id']);

        if (empty($data)) {
            return true; // Nothing to update
        }

        $setParts = [];
        $params = [];

        foreach ($data as $field => $value) {
            $setParts[] = "{$field} = :{$field}";
            $params[":{$field}"] = $value;
        }

        $params[':id'] = $id;
        $setClause = implode(', ', $setParts);

        $sql = "UPDATE {$this->addPrefix($table)} SET {$setClause} WHERE id = :id";

        $stmt = $this->execute($sql, $params);
        return $stmt->rowCount() > 0;
    }

    /**
     * Delete records
     */
    public function delete_records(string $table, array $conditions = []): bool
    {
        if (empty($conditions)) {
            throw new InvalidArgumentException("Delete requires conditions to prevent accidental table truncation");
        }

        $params = [];
        $whereClause = $this->buildWhereClause($conditions, $params);

        $sql = "DELETE FROM {$this->addPrefix($table)} WHERE {$whereClause}";

        $stmt = $this->execute($sql, $params);
        return $stmt->rowCount() > 0;
    }

    /**
     * Count records
     */
    public function count_records(string $table, array $conditions = []): int
    {
        $sql = "SELECT COUNT(*) as count FROM {$this->addPrefix($table)}";
        $params = [];

        if (!empty($conditions)) {
            $whereClause = $this->buildWhereClause($conditions, $params);
            $sql .= " WHERE {$whereClause}";
        }

        $stmt = $this->execute($sql, $params);
        $result = $stmt->fetch(PDO::FETCH_OBJ);

        return (int) $result->count;
    }

    /**
     * Check if record exists
     */
    public function record_exists(string $table, array $conditions): bool
    {
        return $this->count_records($table, $conditions) > 0;
    }

    /**
     * Execute raw SQL
     */
    public function execute(string $sql, array $params = []): PDOStatement
    {
        // Ensure connection exists
        if ($this->connection === null) {
            $this->connect();
        }

        // If connection still null, throw meaningful error
        if ($this->connection === null) {
            throw new DatabaseException("Database connection not available. Make sure MySQL is running and configured properly.");
        }

        if ($this->debugMode) {
            error_log("SQL: {$sql}");
            error_log("Params: " . json_encode($params));
        }

        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            throw new DatabaseException("SQL execution failed: " . $e->getMessage() . "\nSQL: {$sql}", 0, $e);
        }
    }

    /**
     * Start database transaction
     */
    public function start_transaction(): void
    {
        $this->connect();
        $this->connection->beginTransaction();
    }

    /**
     * Commit transaction
     */
    public function commit_transaction(): void
    {
        $this->connection->commit();
    }

    /**
     * Rollback transaction
     */
    public function rollback_transaction(): void
    {
        $this->connection->rollBack();
    }

    /**
     * Get table names with prefix
     */
    public function get_tables(): array
    {
        $sql = "SHOW TABLES";
        $stmt = $this->execute($sql);
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

        return array_map(function($table) {
            return str_replace($this->tablePrefix, '', $table);
        }, $tables);
    }

    /**
     * Get database info
     */
    public function get_server_info(): array
    {
        // Ensure connection exists
        if ($this->connection === null) {
            $this->connect();
        }

        // If connection still null, return default info
        if ($this->connection === null) {
            return [
                'description' => 'No database connection',
                'version' => 'N/A',
                'driver' => 'none'
            ];
        }

        return [
            'description' => $this->connection->getAttribute(PDO::ATTR_SERVER_INFO),
            'version' => $this->connection->getAttribute(PDO::ATTR_SERVER_VERSION),
            'driver' => $this->connection->getAttribute(PDO::ATTR_DRIVER_NAME)
        ];
    }

    /**
     * Build WHERE clause from conditions array
     */
    private function buildWhereClause(array $conditions, array &$params): string
    {
        $whereParts = [];

        foreach ($conditions as $field => $value) {
            $paramKey = ":where_{$field}";
            $whereParts[] = "{$field} = {$paramKey}";
            $params[$paramKey] = $value;
        }

        return implode(' AND ', $whereParts);
    }

    /**
     * Add table prefix
     */
    private function addPrefix(string $table): string
    {
        return $this->tablePrefix . $table;
    }

    /**
     * Build DSN string
     */
    private function buildDsn(): string
    {
        $driver = $this->config['driver'];
        $host = $this->config['host'];
        $port = $this->config['port'];
        $database = $this->config['database'];

        switch ($driver) {
            case 'mysql':
                // Don't include charset in DSN - handle it via connection options instead
                return "mysql:host={$host};port={$port};dbname={$database}";
            case 'pgsql':
                return "pgsql:host={$host};port={$port};dbname={$database}";
            case 'sqlite':
                return "sqlite:{$database}";
            default:
                throw new InvalidArgumentException("Unsupported database driver: {$driver}");
        }
    }

    /**
     * Close connection (for testing)
     */
    public function disconnect(): void
    {
        $this->connection = null;
    }

    /**
     * Get raw PDO connection (use with caution)
     */
    public function get_connection(): ?PDO
    {
        $this->connect();
        return $this->connection;
    }

    /**
     * Get plugin configuration value
     */
    public function get_config(string $plugin, string $name, mixed $default = null): mixed
    {
        $record = $this->get_record('config_plugins', ['plugin' => $plugin, 'name' => $name]);
        return $record ? $record->value : $default;
    }

    /**
     * Set plugin configuration value
     */
    public function set_config(string $plugin, string $name, mixed $value): bool
    {
        // Convert value to string for storage
        $valueStr = is_string($value) ? $value : json_encode($value);
        $currentTime = time();

        // Check if config already exists
        if ($this->record_exists('config_plugins', ['plugin' => $plugin, 'name' => $name])) {
            // Update existing config
            $record = $this->get_record('config_plugins', ['plugin' => $plugin, 'name' => $name]);
            return $this->update_record('config_plugins', [
                'id' => $record->id,
                'value' => $valueStr,
                'timemodified' => $currentTime
            ]);
        } else {
            // Insert new config
            $result = $this->insert_record('config_plugins', [
                'plugin' => $plugin,
                'name' => $name,
                'value' => $valueStr,
                'timecreated' => $currentTime,
                'timemodified' => $currentTime
            ]);
            return $result !== false;
        }
    }

    /**
     * Get plugin version
     */
    public function get_plugin_version(string $plugin): ?string
    {
        return $this->get_config($plugin, 'version');
    }

    /**
     * Set plugin version
     */
    public function set_plugin_version(string $plugin, string $version): bool
    {
        return $this->set_config($plugin, 'version', $version);
    }

    /**
     * Get all configurations for a plugin
     */
    public function get_plugin_configs(string $plugin): array
    {
        $records = $this->get_records('config_plugins', ['plugin' => $plugin]);
        $configs = [];

        foreach ($records as $record) {
            $configs[$record->name] = $record->value;
        }

        return $configs;
    }

    /**
     * Delete plugin configuration
     */
    public function unset_config(string $plugin, string $name): bool
    {
        return $this->delete_records('config_plugins', ['plugin' => $plugin, 'name' => $name]);
    }

    /**
     * Delete all configurations for a plugin
     */
    public function unset_plugin_configs(string $plugin): bool
    {
        return $this->delete_records('config_plugins', ['plugin' => $plugin]);
    }
}
