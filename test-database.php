<?php

require_once 'vendor/autoload.php';
require_once 'src/Core/helpers.php';

/**
 * Database System Test - Demonstrates Moodle-compatible database functions
 */

echo "=== DevFramework Database System Test ===\n\n";

try {
    // Test database connection
    echo "1. Testing database connection...\n";
    $serverInfo = $DB->get_server_info();
    echo "✅ Connected to: {$serverInfo['description']}\n";
    echo "   Driver: {$serverInfo['driver']}\n";
    echo "   Version: {$serverInfo['version']}\n\n";

    // Test table creation (using raw SQL for demo)
    echo "2. Creating test table...\n";
    $DB->execute("
        CREATE TABLE IF NOT EXISTS test_users (
            id INT PRIMARY KEY AUTO_INCREMENT,
            username VARCHAR(50) NOT NULL UNIQUE,
            email VARCHAR(100) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            active BOOLEAN DEFAULT TRUE
        )
    ");
    echo "✅ Test table 'test_users' created\n\n";

    // Test insert_record
    echo "3. Testing record insertion...\n";
    $user1 = [
        'username' => 'john_doe',
        'email' => 'john@example.com',
        'active' => true
    ];

    $userId1 = $DB->insert_record('test_users', $user1);
    echo "✅ Inserted user with ID: {$userId1}\n";

    $user2 = (object) [
        'username' => 'jane_smith',
        'email' => 'jane@example.com',
        'active' => false
    ];

    $userId2 = db_insert_record('test_users', $user2);
    echo "✅ Inserted user with ID: {$userId2} (using helper function)\n\n";

    // Test get_record
    echo "4. Testing single record retrieval...\n";
    $user = $DB->get_record('test_users', ['username' => 'john_doe']);
    if ($user) {
        echo "✅ Found user: {$user->username} ({$user->email})\n";
        echo "   Created: {$user->created_at}\n";
        echo "   Active: " . ($user->active ? 'Yes' : 'No') . "\n";
    }

    // Test get_records
    echo "\n5. Testing multiple record retrieval...\n";
    $users = $DB->get_records('test_users', [], 'username ASC');
    echo "✅ Found " . count($users) . " users:\n";
    foreach ($users as $user) {
        $status = $user->active ? 'Active' : 'Inactive';
        echo "   - {$user->username} ({$user->email}) - {$status}\n";
    }

    // Test get_records with conditions
    echo "\n6. Testing conditional queries...\n";
    $activeUsers = db_get_records('test_users', ['active' => 1]);
    echo "✅ Found " . count($activeUsers) . " active users\n";

    // Test update_record
    echo "\n7. Testing record updates...\n";
    $userToUpdate = $DB->get_record('test_users', ['username' => 'jane_smith']);
    if ($userToUpdate) {
        $userToUpdate->active = true;
        $userToUpdate->email = 'jane.smith@example.com';

        $success = $DB->update_record('test_users', $userToUpdate);
        if ($success) {
            echo "✅ Updated Jane's record\n";

            // Verify update
            $updatedUser = $DB->get_record('test_users', ['id' => $userToUpdate->id]);
            echo "   New email: {$updatedUser->email}\n";
            echo "   Active: " . ($updatedUser->active ? 'Yes' : 'No') . "\n";
        }
    }

    // Test count_records
    echo "\n8. Testing record counting...\n";
    $totalUsers = $DB->count_records('test_users');
    $activeCount = db_count_records('test_users', ['active' => 1]);
    echo "✅ Total users: {$totalUsers}\n";
    echo "✅ Active users: {$activeCount}\n";

    // Test get_field
    echo "\n9. Testing single field retrieval...\n";
    $email = $DB->get_field('test_users', 'email', ['username' => 'john_doe']);
    echo "✅ John's email: {$email}\n";

    // Test record_exists
    echo "\n10. Testing record existence...\n";
    $exists = $DB->record_exists('test_users', ['username' => 'john_doe']);
    echo "✅ John exists: " . ($exists ? 'Yes' : 'No') . "\n";

    $notExists = $DB->record_exists('test_users', ['username' => 'nonexistent']);
    echo "✅ Nonexistent user: " . ($notExists ? 'Yes' : 'No') . "\n";

    // Test get_records_sql
    echo "\n11. Testing raw SQL queries...\n";
    $sqlResults = $DB->get_records_sql(
        "SELECT username, email FROM test_users WHERE active = :active ORDER BY username",
        [':active' => 1]
    );
    echo "✅ SQL query returned " . count($sqlResults) . " active users\n";

    // Test transactions
    echo "\n12. Testing database transactions...\n";
    $DB->start_transaction();

    try {
        $testUser = ['username' => 'test_transaction', 'email' => 'test@example.com'];
        $testId = $DB->insert_record('test_users', $testUser);
        echo "✅ Inserted test user in transaction (ID: {$testId})\n";

        // Simulate an error condition
        $shouldFail = false; // Change to true to test rollback
        if ($shouldFail) {
            throw new Exception("Simulated error");
        }

        $DB->commit_transaction();
        echo "✅ Transaction committed successfully\n";

    } catch (Exception $e) {
        $DB->rollback_transaction();
        echo "❌ Transaction rolled back: " . $e->getMessage() . "\n";
    }

    // Test delete_records
    echo "\n13. Testing record deletion...\n";
    $deleted = db_delete_records('test_users', ['username' => 'test_transaction']);
    echo "✅ Deleted test user: " . ($deleted ? 'Yes' : 'No') . "\n";

    // Final count
    echo "\n14. Final verification...\n";
    $finalCount = $DB->count_records('test_users');
    echo "✅ Final user count: {$finalCount}\n";

    echo "\n=== All Database Tests Completed Successfully! ===\n";

} catch (Exception $e) {
    echo "\n❌ Database Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";

    if (isset($DB) && method_exists($DB, 'rollback_transaction')) {
        try {
            $DB->rollback_transaction();
        } catch (Exception $rollbackError) {
            // Ignore rollback errors if no transaction is active
        }
    }
}

echo "\n=== Available Global Database Functions ===\n";
echo "Global variable: \$DB\n";
echo "Helper functions:\n";
echo "- db() - Get database instance\n";
echo "- db_get_record(\$table, \$conditions, \$fields, \$strictness)\n";
echo "- db_get_records(\$table, \$conditions, \$sort, \$fields, \$limitfrom, \$limitnum)\n";
echo "- db_insert_record(\$table, \$data, \$returnId)\n";
echo "- db_update_record(\$table, \$data)\n";
echo "- db_delete_records(\$table, \$conditions)\n";
echo "- db_count_records(\$table, \$conditions)\n";

echo "\n=== Moodle-Compatible Methods ===\n";
echo "\$DB->get_record(\$table, \$conditions, \$fields, \$strictness)\n";
echo "\$DB->get_records(\$table, \$conditions, \$sort, \$fields, \$limitfrom, \$limitnum)\n";
echo "\$DB->get_records_sql(\$sql, \$params, \$limitfrom, \$limitnum)\n";
echo "\$DB->get_field(\$table, \$return, \$conditions, \$strictness)\n";
echo "\$DB->insert_record(\$table, \$dataobject, \$returnid, \$bulk)\n";
echo "\$DB->update_record(\$table, \$dataobject, \$bulk)\n";
echo "\$DB->delete_records(\$table, \$conditions)\n";
echo "\$DB->count_records(\$table, \$conditions)\n";
echo "\$DB->record_exists(\$table, \$conditions)\n";
echo "\$DB->execute(\$sql, \$params)\n";
echo "\$DB->start_transaction()\n";
echo "\$DB->commit_transaction()\n";
echo "\$DB->rollback_transaction()\n";
