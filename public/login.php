<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Authentication Test - Login</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 500px; margin: 50px auto; padding: 20px; }
        .container { background: #f9f9f9; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .form-group { margin: 15px 0; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type="text"], input[type="password"], select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        button { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; width: 100%; font-size: 16px; }
        button:hover { background: #0056b3; }
        .message { padding: 10px; margin: 10px 0; border-radius: 4px; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        .links { text-align: center; margin-top: 20px; }
        .links a { color: #007bff; text-decoration: none; margin: 0 10px; }
        .links a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔐 Login to Test Authentication</h1>

        <?php
        require_once '../vendor/autoload.php';
        require_once '../src/Core/helpers.php';

        $message = '';
        $messageType = '';

        // Handle login form submission
        if ($_POST && isset($_POST['action']) && $_POST['action'] === 'login') {
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';

            if (empty($username) || empty($password)) {
                $message = 'Please enter both username and password.';
                $messageType = 'error';
            } else {
                // Attempt login using the helper function
                $user = login($username, $password);
                if ($user) {
                    $message = "Login successful! Welcome, {$user->getUsername()}";
                    $messageType = 'success';
                    // Redirect to dashboard after successful login
                    header('Location: dashboard.php');
                    exit;
                } else {
                    $message = 'Invalid username or password.';
                    $messageType = 'error';
                }
            }
        }

        // Check if user is already logged in
        if (is_authenticated()) {
            $currentUser = current_user();
            echo '<div class="message success">';
            echo "You are already logged in as: <strong>" . htmlspecialchars($currentUser->getUsername()) . "</strong>";
            echo '<br><a href="dashboard.php">Go to Dashboard</a> | <a href="login.php?logout=1">Logout</a>';
            echo '</div>';
        } else {
        ?>

        <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <form method="post">
            <input type="hidden" name="action" value="login">

            <div class="form-group">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" required value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
            </div>

            <button type="submit">Login</button>
        </form>

        <div class="message info">
            <strong>Default Test Accounts:</strong><br>
            • Username: <code>admin</code> / Password: <code>admin123</code><br>
            • Username: <code>testuser</code> / Password: <code>password123</code>
        </div>

        <?php } ?>

        <div class="links">
            <a href="auth-demo.php">Full Authentication Demo</a> |
            <a href="dashboard.php">Dashboard</a> |
            <a href="../">Back to Home</a>
        </div>
    </div>

    <?php
    // Handle logout
    if (isset($_GET['logout'])) {
        logout();
        header('Location: login.php');
        exit;
    }
    ?>
</body>
</html>

