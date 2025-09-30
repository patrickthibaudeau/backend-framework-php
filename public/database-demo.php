<?php
require_once '../vendor/autoload.php';
require_once '../src/Core/helpers.php';

// Set content type for proper display
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DevFramework - Database Demo</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #007cba; padding-bottom: 10px; }
        h2 { color: #007cba; margin-top: 30px; }
        .success { background: #d4edda; color: #155724; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .error { background: #f8d7da; color: #721c24; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .info { background: #d1ecf1; color: #0c5460; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .code { background: #f8f9fa; border: 1px solid #e9ecef; padding: 10px; border-radius: 4px; font-family: monospace; margin: 10px 0; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
        th { background-color: #007cba; color: white; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        .method-list { background: #f8f9fa; padding: 15px; border-radius: 4px; }
        .method-list code { background: #e9ecef; padding: 2px 4px; border-radius: 3px; }
        .demo-section { background: #fff; border: 1px solid #ddd; padding: 20px; margin: 20px 0; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🗄️ DevFramework Database System Demo</h1>
        <p>This page demonstrates the Moodle-compatible database functions available in DevFramework.</p>

        <?php
        try {
            // Initialize global $DB if not already done
            if (!isset($DB)) {
                echo '<div class="info">Initializing global $DB variable...</div>';
                \DevFramework\Core\Database\DatabaseFactory::createGlobal();
            }

            // Test database connection
            echo '<div class="demo-section">';
            echo '<h2>1. Database Connection Status</h2>';

            try {
                $serverInfo = $DB->get_server_info();

                if ($serverInfo['driver'] === 'none') {
                    throw new Exception("No database connection available");
                }

                echo '<div class="success">✅ Database Connected Successfully!</div>';
                echo '<div class="info">';
                echo "<strong>Server:</strong> {$serverInfo['description']}<br>";
                echo "<strong>Driver:</strong> {$serverInfo['driver']}<br>";
                echo "<strong>Version:</strong> {$serverInfo['version']}";
                echo '</div>';

                // Only proceed with database operations if connected
                $databaseConnected = true;

            } catch (Exception $dbError) {
                $databaseConnected = false;
                echo '<div class="error">❌ Database Connection Failed</div>';
                echo '<div class="info">';
                echo '<strong>Error:</strong> ' . htmlspecialchars($dbError->getMessage()) . '<br><br>';
                echo '<strong>To fix this:</strong><br>';
                echo '1. Start MySQL container: <code>docker compose --profile with-mysql up -d</code><br>';
                echo '2. Wait 10-15 seconds for MySQL to fully start<br>';
                echo '3. Refresh this page<br><br>';
                echo '<strong>Alternative:</strong> You can still explore the framework without the database functionality.';
                echo '</div>';
            }
            echo '</div>';

            if ($databaseConnected) {
                // Create demo table using framework's schema system
                echo '<div class="demo-section">';
                echo '<h2>2. Table Setup</h2>';

                // Define the demo_posts table schema
                $demoTableSchema = [
                    'fields' => [
                        'id' => [
                            'type' => 'int',
                            'length' => 11,
                            'auto_increment' => true,
                            'primary' => true,
                            'null' => false,
                            'comment' => 'Unique post ID'
                        ],
                        'title' => [
                            'type' => 'varchar',
                            'length' => 200,
                            'null' => false,
                            'comment' => 'Post title'
                        ],
                        'content' => [
                            'type' => 'text',
                            'null' => true,
                            'comment' => 'Post content'
                        ],
                        'author' => [
                            'type' => 'varchar',
                            'length' => 50,
                            'null' => false,
                            'comment' => 'Post author'
                        ],
                        'timecreated' => [
                            'type' => 'timestamp',
                            'null' => false,
                            'comment' => 'Unix timestamp when created'
                        ],
                        'published' => [
                            'type' => 'boolean',
                            'default' => false,
                            'null' => false,
                            'comment' => 'Whether post is published'
                        ],
                        'views' => [
                            'type' => 'int',
                            'default' => 0,
                            'null' => false,
                            'comment' => 'Number of views'
                        ]
                    ],
                    'indexes' => [
                        'author' => 'author',
                        'published' => 'published',
                        'timecreated' => 'timecreated'
                    ]
                ];

                // Create the table using the framework's schema builder
                try {
                    create_table_from_schema('demo_posts', $demoTableSchema);
                    echo '<div class="success">✅ Demo table "dev_demo_posts" created/verified using framework schema system</div>';
                } catch (Exception $e) {
                    echo '<div class="error">❌ Failed to create demo table: ' . htmlspecialchars($e->getMessage()) . '</div>';
                }
                echo '</div>';

                // Clear existing demo data first
                try {
                    $DB->delete_records('demo_posts', ['author' => 'John Developer']);
                    $DB->delete_records('demo_posts', ['author' => 'Jane Coder']);
                    $DB->delete_records('demo_posts', ['author' => 'Bob Programmer']);
                } catch (Exception $e) {
                    // Ignore errors if table doesn't exist yet
                }

                // Insert sample data
                echo '<div class="demo-section">';
                echo '<h2>3. Data Insertion ($DB->insert_record)</h2>';

                $posts = [
                    [
                        'title' => 'Welcome to DevFramework',
                        'content' => 'This is our first blog post about the amazing DevFramework!',
                        'author' => 'John Developer',
                        'published' => true,
                        'views' => 42,
                        'timecreated' => time()
                    ],
                    [
                        'title' => 'Database Integration Guide',
                        'content' => 'Learn how to use the Moodle-compatible database functions.',
                        'author' => 'Jane Coder',
                        'published' => true,
                        'views' => 156,
                        'timecreated' => time()
                    ],
                    [
                        'title' => 'Advanced PHP Features',
                        'content' => 'Exploring PHP 8.4 features in our framework.',
                        'author' => 'Bob Programmer',
                        'published' => false,
                        'views' => 23,
                        'timecreated' => time()
                    ]
                ];

                foreach ($posts as $post) {
                    try {
                        $id = $DB->insert_record('demo_posts', $post);
                        echo "<div class='info'>✅ Inserted post: \"{$post['title']}\" with ID: {$id}</div>";
                    } catch (Exception $e) {
                        echo "<div class='error'>❌ Failed to insert post \"{$post['title']}\": " . htmlspecialchars($e->getMessage()) . "</div>";
                    }
                }
                echo '</div>';

                // Display records
                echo '<div class="demo-section">';
                echo '<h2>4. Data Retrieval ($DB->get_records)</h2>';

                $allPosts = $DB->get_records('demo_posts', [], 'timecreated DESC');

                echo '<div class="code">$allPosts = $DB->get_records("demo_posts", [], "timecreated DESC");</div>';
                echo "<div class='success'>Retrieved " . count($allPosts) . " posts</div>";

                echo '<table>';
                echo '<tr><th>ID</th><th>Title</th><th>Author</th><th>Published</th><th>Views</th><th>Created</th></tr>';
                foreach ($allPosts as $post) {
                    $published = $post->published ? 'Yes' : 'No';
                    $createdDate = date('Y-m-d H:i:s', $post->timecreated);
                    echo "<tr>";
                    echo "<td>{$post->id}</td>";
                    echo "<td>{$post->title}</td>";
                    echo "<td>{$post->author}</td>";
                    echo "<td>{$published}</td>";
                    echo "<td>{$post->views}</td>";
                    echo "<td>{$createdDate}</td>";
                    echo "</tr>";
                }
                echo '</table>';
                echo '</div>';

                // Conditional queries
                echo '<div class="demo-section">';
                echo '<h2>5. Conditional Queries</h2>';

                $publishedPosts = $DB->get_records('demo_posts', ['published' => 1]);
                echo '<div class="code">$publishedPosts = $DB->get_records("demo_posts", ["published" => 1]);</div>';
                echo "<div class='success'>Found " . count($publishedPosts) . " published posts</div>";

                echo '<h3>Published Posts:</h3>';
                echo '<ul>';
                foreach ($publishedPosts as $post) {
                    echo "<li><strong>{$post->title}</strong> by {$post->author} ({$post->views} views)</li>";
                }
                echo '</ul>';
                echo '</div>';

                // Single record retrieval
                echo '<div class="demo-section">';
                echo '<h2>6. Single Record ($DB->get_record)</h2>';

                $popularPost = $DB->get_record('demo_posts', [], 'views DESC');
                echo '<div class="code">$popularPost = $DB->get_record("demo_posts", [], "views DESC");</div>';

                if ($popularPost) {
                    echo '<div class="success">Most popular post found:</div>';
                    echo "<div class='info'>";
                    echo "<strong>Title:</strong> {$popularPost->title}<br>";
                    echo "<strong>Author:</strong> {$popularPost->author}<br>";
                    echo "<strong>Views:</strong> {$popularPost->views}<br>";
                    echo "<strong>Content:</strong> " . substr($popularPost->content, 0, 100) . "...";
                    echo "</div>";
                }
                echo '</div>';

                // Update record
                echo '<div class="demo-section">';
                echo '<h2>7. Record Updates ($DB->update_record)</h2>';

                if ($popularPost) {
                    $popularPost->views += 10; // Add 10 more views
                    $success = $DB->update_record('demo_posts', $popularPost);

                    echo '<div class="code">$popularPost->views += 10;<br>$DB->update_record("demo_posts", $popularPost);</div>';

                    if ($success) {
                        echo "<div class='success'>✅ Updated post views to {$popularPost->views}</div>";
                    } else {
                        echo "<div class='error'>❌ Failed to update post</div>";
                    }
                }
                echo '</div>';

                // Count records
                echo '<div class="demo-section">';
                echo '<h2>8. Record Counting ($DB->count_records)</h2>';

                $totalPosts = $DB->count_records('demo_posts');
                $publishedCount = $DB->count_records('demo_posts', ['published' => 1]);

                echo '<div class="code">$totalPosts = $DB->count_records("demo_posts");<br>';
                echo '$publishedCount = $DB->count_records("demo_posts", ["published" => 1]);</div>';

                echo "<div class='info'>";
                echo "<strong>Total Posts:</strong> {$totalPosts}<br>";
                echo "<strong>Published Posts:</strong> {$publishedCount}<br>";
                echo "<strong>Draft Posts:</strong> " . ($totalPosts - $publishedCount);
                echo "</div>";
                echo '</div>';

                // Raw SQL query
                echo '<div class="demo-section">';
                echo '<h2>9. Raw SQL Queries ($DB->get_records_sql)</h2>';

                $sqlResults = $DB->get_records_sql(
                    "SELECT author, COUNT(*) as post_count, AVG(views) as avg_views
                     FROM demo_posts
                     WHERE published = :published
                     GROUP BY author
                     ORDER BY post_count DESC",
                    [':published' => 1]
                );

                echo '<div class="code">';
                echo 'SELECT author, COUNT(*) as post_count, AVG(views) as avg_views<br>';
                echo 'FROM demo_posts WHERE published = :published<br>';
                echo 'GROUP BY author ORDER BY post_count DESC';
                echo '</div>';

                echo '<div class="success">SQL Query Results:</div>';
                echo '<table>';
                echo '<tr><th>Author</th><th>Post Count</th><th>Average Views</th></tr>';
                foreach ($sqlResults as $result) {
                    echo "<tr>";
                    echo "<td>{$result->author}</td>";
                    echo "<td>{$result->post_count}</td>";
                    echo "<td>" . round($result->avg_views, 1) . "</td>";
                    echo "</tr>";
                }
                echo '</table>';
                echo '</div>';

                // Set field demonstration
                echo '<div class="demo-section">';
                echo '<h2>10. Field Updates ($DB->set_field)</h2>';

                echo '<div class="info">The set_field method allows you to update a single field for records matching specific conditions:</div>';

                // Demonstrate updating a single field
                $beforeUpdate = $DB->get_record('demo_posts', ['author' => 'Bob Programmer']);
                if ($beforeUpdate) {
                    echo "<div class='info'>Before update - Bob's post status: " . ($beforeUpdate->published ? 'Published' : 'Draft') . "</div>";

                    // Update the published status
                    $success = $DB->set_field('demo_posts', 'published', 1, ['author' => 'Bob Programmer']);

                    echo '<div class="code">$DB->set_field("demo_posts", "published", 1, ["author" => "Bob Programmer"]);</div>';

                    if ($success) {
                        echo "<div class='success'>✅ Updated Bob's post status successfully</div>";

                        // Verify the update
                        $afterUpdate = $DB->get_record('demo_posts', ['author' => 'Bob Programmer']);
                        echo "<div class='info'>After update - Bob's post status: " . ($afterUpdate->published ? 'Published' : 'Draft') . "</div>";
                    } else {
                        echo "<div class='error'>❌ Failed to update post status</div>";
                    }
                }

                // Demonstrate updating view count
                echo '<br><div class="info">Updating view counts for all published posts:</div>';

                $publishedPosts = $DB->get_records('demo_posts', ['published' => 1]);
                $originalViewCounts = [];

                echo '<table style="margin: 10px 0;">';
                echo '<tr><th>Title</th><th>Views Before</th><th>Views After</th></tr>';

                foreach ($publishedPosts as $post) {
                    $originalViewCounts[$post->id] = $post->views;

                    // Add 5 views to each published post
                    $newViews = $post->views + 5;
                    $DB->set_field('demo_posts', 'views', $newViews, ['id' => $post->id]);

                    echo "<tr>";
                    echo "<td>" . substr($post->title, 0, 30) . "...</td>";
                    echo "<td>{$post->views}</td>";
                    echo "<td>{$newViews}</td>";
                    echo "</tr>";
                }
                echo '</table>';

                echo '<div class="code">';
                echo '// Update views for each post<br>';
                echo 'foreach ($publishedPosts as $post) {<br>';
                echo '&nbsp;&nbsp;&nbsp;&nbsp;$newViews = $post->views + 5;<br>';
                echo '&nbsp;&nbsp;&nbsp;&nbsp;$DB->set_field("demo_posts", "views", $newViews, ["id" => $post->id]);<br>';
                echo '}';
                echo '</div>';

                echo "<div class='success'>✅ Updated view counts for " . count($publishedPosts) . " published posts</div>";
                echo '</div>';

                // Helper functions demonstration (renumbered to 11)
                echo '<div class="demo-section">';
                echo '<h2>11. Helper Functions</h2>';

                echo '<div class="info">DevFramework provides convenient helper functions for common database operations:</div>';

                // Using helper functions
                $helperPost = db_get_record('demo_posts', ['author' => 'John Developer']);
                $helperCount = db_count_records('demo_posts', ['published' => 1]);

                echo '<div class="code">';
                echo '$post = db_get_record("demo_posts", ["author" => "John Developer"]);<br>';
                echo '$count = db_count_records("demo_posts", ["published" => 1]);';
                echo '</div>';

                if ($helperPost) {
                    echo "<div class='success'>Helper function found post: \"{$helperPost->title}\"</div>";
                }
                echo "<div class='success'>Helper function counted: {$helperCount} published posts</div>";
                echo '</div>';

            } else {
                // Database not connected - show how to start MySQL
                echo '<div class="demo-section">';
                echo '<h2>🚀 Start MySQL to See Database Demo</h2>';
                echo '<div class="info">';
                echo '<strong>To see the full database demo in action:</strong><br><br>';
                echo '<strong>Step 1:</strong> Open a terminal and navigate to your project directory<br>';
                echo '<strong>Step 2:</strong> Run the following command:<br>';
                echo '<div class="code">docker compose --profile with-mysql up -d</div>';
                echo '<strong>Step 3:</strong> Wait 10-15 seconds for MySQL to start<br>';
                echo '<strong>Step 4:</strong> Refresh this page<br><br>';
                echo '<strong>Alternative:</strong> You can still use the framework without MySQL for non-database features.';
                echo '</div>';
                echo '</div>';
            }

        } catch (Exception $e) {
            echo '<div class="error">';
            echo '<h2>❌ Application Error</h2>';
            echo '<strong>Error:</strong> ' . htmlspecialchars($e->getMessage()) . '<br>';
            echo '<strong>File:</strong> ' . $e->getFile() . ':' . $e->getLine();
            echo '</div>';

            echo '<div class="info">';
            echo '<h3>Troubleshooting:</h3>';
            echo '<ul>';
            echo '<li>Make sure containers are running: <code>docker compose --profile with-mysql up -d</code></li>';
            echo '<li>Check that you have Docker installed and running</li>';
            echo '<li>Verify your .env configuration file exists</li>';
            echo '</ul>';
            echo '</div>';
        }
        ?>

        <div class="demo-section">
            <h2>📚 Available Database Methods</h2>
            <div class="method-list">
                <h3>Core Moodle-Compatible Methods:</h3>
                <ul>
                    <li><code>$DB->get_record($table, $conditions, $fields, $strictness)</code> - Get single record</li>
                    <li><code>$DB->get_records($table, $conditions, $sort, $fields, $limitfrom, $limitnum)</code> - Get multiple records</li>
                    <li><code>$DB->get_records_sql($sql, $params, $limitfrom, $limitnum)</code> - Execute raw SQL SELECT</li>
                    <li><code>$DB->get_field($table, $return, $conditions, $strictness)</code> - Get single field value</li>
                    <li><code>$DB->set_field($table, $fieldname, $value, $conditions)</code> - Set single field value</li>
                    <li><code>$DB->insert_record($table, $dataobject, $returnid, $bulk)</code> - Insert new record</li>
                    <li><code>$DB->update_record($table, $dataobject, $bulk)</code> - Update existing record</li>
                    <li><code>$DB->delete_records($table, $conditions)</code> - Delete records</li>
                    <li><code>$DB->count_records($table, $conditions)</code> - Count records</li>
                    <li><code>$DB->record_exists($table, $conditions)</code> - Check if record exists</li>
                </ul>

                <h3>Transaction Methods:</h3>
                <ul>
                    <li><code>$DB->start_transaction()</code> - Begin transaction</li>
                    <li><code>$DB->commit_transaction()</code> - Commit transaction</li>
                    <li><code>$DB->rollback_transaction()</code> - Rollback transaction</li>
                </ul>

                <h3>Utility Methods:</h3>
                <ul>
                    <li><code>$DB->execute($sql, $params)</code> - Execute raw SQL</li>
                    <li><code>$DB->get_server_info()</code> - Get database server information</li>
                    <li><code>$DB->get_tables()</code> - Get list of tables</li>
                </ul>

                <h3>Helper Functions:</h3>
                <ul>
                    <li><code>db()</code> - Get database instance</li>
                    <li><code>db_get_record(...)</code> - Helper for $DB->get_record()</li>
                    <li><code>db_get_records(...)</code> - Helper for $DB->get_records()</li>
                    <li><code>db_insert_record(...)</code> - Helper for $DB->insert_record()</li>
                    <li><code>db_update_record(...)</code> - Helper for $DB->update_record()</li>
                    <li><code>db_delete_records(...)</code> - Helper for $DB->delete_records()</li>
                    <li><code>db_count_records(...)</code> - Helper for $DB->count_records()</li>
                </ul>
            </div>
        </div>

        <div class="demo-section">
            <h2>🚀 Getting Started</h2>
            <div class="info">
                <h3>1. Start the development environment with database:</h3>
                <div class="code">docker compose --profile with-mysql up -d</div>

                <h3>2. Initialize your configuration:</h3>
                <div class="code">./dev.sh config init</div>

                <h3>3. Test the database connection:</h3>
                <div class="code">./dev.sh shell<br>php test-database.php</div>

                <h3>4. Use in your PHP code:</h3>
                <div class="code">
require_once 'vendor/autoload.php';<br>
require_once 'src/Core/helpers.php';<br><br>

// Global $DB is automatically available<br>
$users = $DB->get_records('users', ['active' => 1]);<br><br>

// Or use helper functions<br>
$user = db_get_record('users', ['id' => 123]);
                </div>
            </div>
        </div>

        <div style="text-align: center; margin-top: 30px; color: #666;">
            <p>DevFramework Database System - Powered by PHP <?= PHP_VERSION ?></p>
        </div>
    </div>
</body>
</html>
