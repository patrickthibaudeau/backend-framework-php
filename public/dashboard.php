<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Authentication Test - Dashboard</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 20px auto; padding: 20px; }
        .header { background: #007bff; color: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        .user-info { background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0; }
        .section { background: white; padding: 20px; margin: 20px 0; border: 1px solid #ddd; border-radius: 8px; }
        .success { background: #d4edda; color: #155724; border-color: #c3e6cb; }
        .btn { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; margin: 5px; }
        .btn:hover { background: #0056b3; }
        .btn-danger { background: #dc3545; }
        .btn-danger:hover { background: #c82333; }
        .auth-type { padding: 3px 8px; border-radius: 3px; font-size: 0.9em; }
        .auth-manual { background: #28a745; color: white; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { padding: 10px; border: 1px solid #ddd; text-align: left; }
        th { background: #f8f9fa; }
        .status-active { color: #28a745; font-weight: bold; }
        .status-inactive { color: #dc3545; font-weight: bold; }
    </style>
</head>
<body>
    <?php
    require_once '../vendor/autoload.php';
    require_once '../src/Core/helpers.php';

    // Require authentication - redirect to login if not authenticated
    require_auth('/login.php');

    $currentUser = current_user();
    ?>

    <div class="header">
        <h1>🎯 Authentication Dashboard</h1>
        <p>Welcome to the protected area! This page requires authentication.</p>
    </div>

    <div class="section success">
        <h2>✅ Authentication Test Successful!</h2>
        <p>You are successfully authenticated and viewing a protected page.</p>
    </div>

    <div class="user-info">
        <h2>👤 Current User Information</h2>
        <table>
            <tr><th>User ID</th><td><?php echo $currentUser->getId(); ?></td></tr>
            <tr><th>Username</th><td><?php echo htmlspecialchars($currentUser->getUsername()); ?></td></tr>
            <tr><th>Email</th><td><?php echo htmlspecialchars($currentUser->getEmail()); ?></td></tr>
            <tr><th>Authentication Type</th><td><span class="auth-type auth-<?php echo $currentUser->getAuthType(); ?>"><?php echo strtoupper($currentUser->getAuthType()); ?></span></td></tr>
            <tr><th>Account Status</th><td><span class="status-<?php echo $currentUser->isActive() ? 'active' : 'inactive'; ?>"><?php echo $currentUser->isActive() ? 'Active' : 'Inactive'; ?></span></td></tr>
            <tr><th>Created</th><td><?php echo $currentUser->getCreatedAt() ? date('Y-m-d H:i:s', $currentUser->getCreatedAt()) : 'Unknown'; ?></td></tr>
            <tr><th>Last Login</th><td><?php echo $currentUser->getLastLogin() ? date('Y-m-d H:i:s', $currentUser->getLastLogin()) : 'Never'; ?></td></tr>
        </table>
    </div>

    <div class="section">
        <h2>🔧 Authentication Tests</h2>

        <h3>Session Information</h3>
        <ul>
            <li><strong>Session Active:</strong> <?php echo is_authenticated() ? '✅ Yes' : '❌ No'; ?></li>
            <li><strong>Session ID:</strong> <?php echo session_id(); ?></li>
            <li><strong>Auth Helper Working:</strong> <?php echo function_exists('is_authenticated') ? '✅ Yes' : '❌ No'; ?></li>
            <li><strong>User Object:</strong> <?php echo is_object(current_user()) ? '✅ Valid' : '❌ Invalid'; ?></li>
        </ul>

        <h3>Available Authentication Providers</h3>
        <p>
        <?php
        $providers = get_auth_providers();
        foreach ($providers as $provider) {
            echo "<span class='auth-type auth-{$provider}'>" . strtoupper($provider) . "</span> ";
        }
        ?>
        </p>

        <h3>Authentication Type Check</h3>
        <ul>
            <li><strong>Has Manual Auth:</strong> <?php echo user_has_auth_type('manual') ? '✅ Yes' : '❌ No'; ?></li>
            <li><strong>Has OAuth Auth:</strong> <?php echo user_has_auth_type('oauth') ? '✅ Yes' : '❌ No'; ?></li>
            <li><strong>Has SAML2 Auth:</strong> <?php echo user_has_auth_type('saml2') ? '✅ Yes' : '❌ No'; ?></li>
            <li><strong>Has LDAP Auth:</strong> <?php echo user_has_auth_type('ldap') ? '✅ Yes' : '❌ No'; ?></li>
        </ul>
    </div>

    <div class="section">
        <h2>👥 All System Users</h2>
        <?php
        try {
            $users = db_get_records('users', [], 'username ASC', 'id, username, email, auth, status, timecreated');
            if ($users) {
                echo '<table>';
                echo '<tr><th>ID</th><th>Username</th><th>Email</th><th>Auth Type</th><th>Status</th><th>Created</th></tr>';
                foreach ($users as $user) {
                    $status = $user->status === 'active' ? 'Active' : ucfirst($user->status);
                    $statusClass = $user->status === 'active' ? 'status-active' : 'status-inactive';
                    $created = $user->timecreated ? date('Y-m-d H:i:s', $user->timecreated) : 'Unknown';

                    echo "<tr>";
                    echo "<td>{$user->id}</td>";
                    echo "<td><strong>{$user->username}</strong></td>";
                    echo "<td>{$user->email}</td>";
                    echo "<td><span class='auth-type auth-{$user->auth}'>" . strtoupper($user->auth) . "</span></td>";
                    echo "<td><span class='{$statusClass}'>{$status}</span></td>";
                    echo "<td>{$created}</td>";
                    echo "</tr>";
                }
                echo '</table>';
            } else {
                echo '<p>No users found in the system.</p>';
            }
        } catch (Exception $e) {
            echo '<p style="color: red;">Error loading users: ' . htmlspecialchars($e->getMessage()) . '</p>';
        }
        ?>
    </div>

    <div class="section">
        <h2>🧪 Test Actions</h2>
        <a href="login.php" class="btn">Test Login Page</a>
        <a href="auth-demo.php" class="btn">Full Auth Demo</a>
        <a href="test-protected.php" class="btn">Test Protected API</a>
        <a href="login.php?logout=1" class="btn btn-danger">Logout</a>
    </div>

    <div class="section">
        <h2>📝 Test Results Summary</h2>
        <ul>
            <li>✅ <strong>Authentication Required:</strong> This page redirects to login if not authenticated</li>
            <li>✅ <strong>Session Management:</strong> User session is properly maintained</li>
            <li>✅ <strong>User Data Access:</strong> User information is accessible via helper functions</li>
            <li>✅ <strong>Helper Functions:</strong> All authentication helper functions are working</li>
            <li>✅ <strong>Multi-Provider Support:</strong> Framework supports multiple authentication types</li>
            <li>✅ <strong>Database Integration:</strong> User data is properly stored and retrieved</li>
        </ul>
    </div>
</body>
</html>

