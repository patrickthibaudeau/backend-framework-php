<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Authentication System Demo</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
        .section { margin: 20px 0; padding: 20px; border: 1px solid #ddd; border-radius: 5px; }
        .success { background-color: #d4edda; border-color: #c3e6cb; }
        .error { background-color: #f8d7da; border-color: #f5c6cb; }
        .info { background-color: #d1ecf1; border-color: #bee5eb; }
        form { margin: 10px 0; }
        input, select, button { margin: 5px; padding: 8px; }
        button { background: #007bff; color: white; border: none; cursor: pointer; border-radius: 3px; }
        button:hover { background: #0056b3; }
        .user-info { background: #f8f9fa; padding: 10px; margin: 10px 0; border-radius: 3px; }
        .auth-type { padding: 2px 6px; border-radius: 3px; font-size: 0.8em; }
        .auth-manual { background: #28a745; color: white; }
        .auth-oauth { background: #17a2b8; color: white; }
        .auth-saml2 { background: #ffc107; color: black; }
        .auth-ldap { background: #6f42c1; color: white; }
    </style>
</head>
<body>
    <h1>🔐 Multi-Type Authentication System Demo</h1>
    
    <?php
    require_once 'vendor/autoload.php';
    require_once 'src/Core/helpers.php';
    
    use DevFramework\Core\Auth\AuthInstaller;
    use DevFramework\Core\Auth\AuthenticationManager;
    use DevFramework\Core\Auth\Exceptions\AuthenticationException;
    
    $message = '';
    $messageType = '';
    
    try {
        // Install authentication tables if needed
        $installer = new AuthInstaller();
        if (!$installer->isInstalled()) {
            $installer->install();
            $message = "Authentication system installed successfully!";
            $messageType = 'success';
        }
        
        $auth = AuthenticationManager::getInstance();
        
        // Handle form submissions
        if ($_POST) {
            if (isset($_POST['action'])) {
                switch ($_POST['action']) {
                    case 'login':
                        try {
                            $user = $auth->authenticate($_POST['username'], $_POST['password']);
                            $message = "Login successful! Welcome, {$user->getUsername()}";
                            $messageType = 'success';
                        } catch (AuthenticationException $e) {
                            $message = "Login failed: {$e->getMessage()}";
                            $messageType = 'error';
                        }
                        break;
                        
                    case 'register':
                        try {
                            $user = $auth->createUser($_POST['username'], $_POST['email'], $_POST['password'], $_POST['auth_type']);
                            $message = "User '{$user->getUsername()}' created successfully with {$user->getAuthType()} authentication!";
                            $messageType = 'success';
                        } catch (AuthenticationException $e) {
                            $message = "Registration failed: {$e->getMessage()}";
                            $messageType = 'error';
                        }
                        break;
                        
                    case 'logout':
                        $auth->logout();
                        $message = "Logged out successfully!";
                        $messageType = 'info';
                        break;
                }
            }
        }
        
        $currentUser = $auth->getCurrentUser();
        $isAuthenticated = $auth->isAuthenticated();
        
    } catch (Exception $e) {
        $message = "System Error: {$e->getMessage()}";
        $messageType = 'error';
    }
    ?>
    
    <?php if ($message): ?>
        <div class="section <?php echo $messageType; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>
    
    <div class="section info">
        <h2>📋 Authentication System Features</h2>
        <ul>
            <li><strong>Manual Authentication:</strong> Traditional username/password with secure hashing</li>
            <li><strong>OAuth Support:</strong> Ready for OAuth2/OpenID Connect integration</li>
            <li><strong>SAML2 Support:</strong> Ready for SAML2 SSO integration</li>
            <li><strong>LDAP Support:</strong> Ready for LDAP/Active Directory integration</li>
            <li><strong>Session Management:</strong> Secure session handling with database tracking</li>
            <li><strong>User Management:</strong> Complete user lifecycle management</li>
        </ul>
    </div>
    
    <?php if ($isAuthenticated): ?>
        <div class="section success">
            <h2>👤 Current User</h2>
            <div class="user-info">
                <strong>Username:</strong> <?php echo htmlspecialchars($currentUser->getUsername()); ?><br>
                <strong>Email:</strong> <?php echo htmlspecialchars($currentUser->getEmail()); ?><br>
                <strong>Auth Type:</strong> <span class="auth-type auth-<?php echo $currentUser->getAuthType(); ?>"><?php echo strtoupper($currentUser->getAuthType()); ?></span><br>
                <strong>Status:</strong> <?php echo $currentUser->isActive() ? '✅ Active' : '❌ Inactive'; ?><br>
                <strong>Last Login:</strong> <?php echo $currentUser->getLastLogin() ?? 'Never'; ?>
            </div>
            
            <form method="post" style="display: inline;">
                <input type="hidden" name="action" value="logout">
                <button type="submit">Logout</button>
            </form>
        </div>
    <?php else: ?>
        <div class="section">
            <h2>🔐 Login</h2>
            <form method="post">
                <input type="hidden" name="action" value="login">
                <div>
                    <label>Username:</label><br>
                    <input type="text" name="username" required placeholder="admin or testuser">
                </div>
                <div>
                    <label>Password:</label><br>
                    <input type="password" name="password" required placeholder="admin123 or password123">
                </div>
                <button type="submit">Login</button>
            </form>
            
            <p><em>Default users: admin/admin123 or testuser/password123</em></p>
        </div>
        
        <div class="section">
            <h2>📝 Register New User</h2>
            <form method="post">
                <input type="hidden" name="action" value="register">
                <div>
                    <label>Username:</label><br>
                    <input type="text" name="username" required>
                </div>
                <div>
                    <label>Email:</label><br>
                    <input type="email" name="email" required>
                </div>
                <div>
                    <label>Password:</label><br>
                    <input type="password" name="password" required>
                </div>
                <div>
                    <label>Authentication Type:</label><br>
                    <select name="auth_type" required>
                        <option value="manual">Manual (Username/Password)</option>
                        <option value="oauth">OAuth 2.0 (Future)</option>
                        <option value="saml2">SAML2 SSO (Future)</option>
                        <option value="ldap">LDAP/AD (Future)</option>
                    </select>
                </div>
                <button type="submit">Register</button>
            </form>
        </div>
    <?php endif; ?>
    
    <div class="section">
        <h2>👥 System Users</h2>
        <?php
        try {
            $users = db_get_records('users', [], 'username ASC', 'username, email, auth, active, created_at');
            if ($users) {
                echo '<table style="width: 100%; border-collapse: collapse;">';
                echo '<tr style="background: #f8f9fa;"><th style="padding: 8px; border: 1px solid #ddd;">Username</th><th style="padding: 8px; border: 1px solid #ddd;">Email</th><th style="padding: 8px; border: 1px solid #ddd;">Auth Type</th><th style="padding: 8px; border: 1px solid #ddd;">Status</th></tr>';
                foreach ($users as $user) {
                    $status = $user->active ? '✅ Active' : '❌ Inactive';
                    echo "<tr>";
                    echo "<td style='padding: 8px; border: 1px solid #ddd;'>{$user->username}</td>";
                    echo "<td style='padding: 8px; border: 1px solid #ddd;'>{$user->email}</td>";
                    echo "<td style='padding: 8px; border: 1px solid #ddd;'><span class='auth-type auth-{$user->auth}'>" . strtoupper($user->auth) . "</span></td>";
                    echo "<td style='padding: 8px; border: 1px solid #ddd;'>{$status}</td>";
                    echo "</tr>";
                }
                echo '</table>';
            } else {
                echo '<p>No users found in the system.</p>';
            }
        } catch (Exception $e) {
            echo '<p>Error loading users: ' . htmlspecialchars($e->getMessage()) . '</p>';
        }
        ?>
    </div>
    
    <div class="section info">
        <h2>🛠️ Available Authentication Providers</h2>
        <?php
        try {
            $providers = $auth->getAvailableProviders();
            foreach ($providers as $provider) {
                echo "<span class='auth-type auth-{$provider}'>" . strtoupper($provider) . "</span> ";
            }
        } catch (Exception $e) {
            echo '<p>Error loading providers: ' . htmlspecialchars($e->getMessage()) . '</p>';
        }
        ?>
    </div>
    
    <div class="section">
        <h2>📚 Usage Examples</h2>
        <h3>Helper Functions:</h3>
        <pre><code>// Check if user is authenticated
if (is_authenticated()) {
    echo "User is logged in!";
}

// Get current user
$user = current_user();
if ($user) {
    echo "Hello, " . $user->getUsername();
}

// Login user
$user = login('username', 'password');
if ($user) {
    echo "Login successful!";
}

// Create new user
$user = create_user('newuser', 'email@example.com', 'password', 'manual');

// Logout
logout();</code></pre>
        
        <h3>Authentication Manager:</h3>
        <pre><code>use DevFramework\Core\Auth\AuthenticationManager;

$auth = AuthenticationManager::getInstance();

// Authenticate with specific auth type
$user = $auth->authenticate('username', 'password', 'manual');

// Create user with specific auth type
$user = $auth->createUser('username', 'email', 'password', 'oauth');</code></pre>
        
        <h3>Middleware Usage:</h3>
        <pre><code>use DevFramework\Core\Auth\AuthMiddleware;

$middleware = new AuthMiddleware();

// Protect route requiring authentication
$middleware->handle(function() {
    echo "Protected content!";
});

// API endpoint protection
$middleware->apiAuth(function() {
    return json_encode(['data' => 'secret']);
});</code></pre>
    </div>
</body>
</html>
