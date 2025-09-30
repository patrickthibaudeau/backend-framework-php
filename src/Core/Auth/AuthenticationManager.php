<?php

namespace DevFramework\Core\Auth;

use DevFramework\Core\Database\Database;
use DevFramework\Core\Auth\Providers\AuthProviderInterface;
use DevFramework\Core\Auth\Providers\ManualAuthProvider;
use DevFramework\Core\Auth\Exceptions\AuthenticationException;
use stdClass;

/**
 * Authentication Manager - Handles multiple authentication types
 */
class AuthenticationManager
{
    private static ?AuthenticationManager $instance = null;
    private Database $db;
    private array $providers = [];
    private ?User $currentUser = null;

    private function __construct()
    {
        $this->db = Database::getInstance();
        $this->registerDefaultProviders();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Register default authentication providers
     */
    private function registerDefaultProviders(): void
    {
        $this->registerProvider('manual', new ManualAuthProvider());
        // Future providers will be registered here:
        // $this->registerProvider('oauth', new OAuthProvider());
        // $this->registerProvider('saml2', new SAML2Provider());
        // $this->registerProvider('ldap', new LDAPProvider());
    }

    /**
     * Register an authentication provider
     */
    public function registerProvider(string $type, AuthProviderInterface $provider): void
    {
        $this->providers[$type] = $provider;
    }

    /**
     * Authenticate a user based on their auth type
     */
    public function authenticate(string $username, string $password, ?string $authType = null): ?User
    {
        // If no auth type specified, look up the user to determine their auth type
        if ($authType === null) {
            $userRecord = $this->db->get_record('users', ['username' => $username]);
            if (!$userRecord) {
                throw new AuthenticationException('User not found');
            }
            $authType = $userRecord->auth;
        }

        // Get the appropriate provider
        if (!isset($this->providers[$authType])) {
            throw new AuthenticationException("Authentication provider '{$authType}' not found");
        }

        $provider = $this->providers[$authType];

        // Attempt authentication
        $authenticated = $provider->authenticate($username, $password);

        if ($authenticated) {
            $this->currentUser = $this->loadUser($username);
            $this->startSession();
            return $this->currentUser;
        }

        throw new AuthenticationException('Authentication failed');
    }

    /**
     * Load user data from database
     */
    private function loadUser(string $username): User
    {
        $userRecord = $this->db->get_record('users', ['username' => $username]);
        if (!$userRecord) {
            throw new AuthenticationException('User not found');
        }

        return new User(
            $userRecord->id,
            $userRecord->username,
            $userRecord->email ?? '',
            $userRecord->auth,
            $userRecord->status === 'active', // Convert status to boolean for User class
            $userRecord->timecreated ?? null,  // Use correct field name
            $userRecord->lastlogin ?? null     // Use correct field name
        );
    }

    /**
     * Start user session
     */
    private function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION['user_id'] = $this->currentUser->getId();
        $_SESSION['username'] = $this->currentUser->getUsername();
        $_SESSION['auth_type'] = $this->currentUser->getAuthType();
        $_SESSION['authenticated'] = true;
        $_SESSION['login_time'] = time();

        // Update last login time in database - use correct field name and Unix timestamp
        $this->db->update_record('users', [
            'id' => $this->currentUser->getId(),
            'lastlogin' => time(),  // Use 'lastlogin' field with Unix timestamp
            'timemodified' => time()  // Also update the modified time
        ]);
    }

    /**
     * Check if user is authenticated
     */
    public function isAuthenticated(): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        return isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true;
    }

    /**
     * Get current authenticated user
     */
    public function getCurrentUser(): ?User
    {
        if ($this->currentUser === null && $this->isAuthenticated()) {
            $this->currentUser = $this->loadUser($_SESSION['username']);
        }

        return $this->currentUser;
    }

    /**
     * Logout current user
     */
    public function logout(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        session_destroy();
        $this->currentUser = null;
    }

    /**
     * Get available authentication providers
     */
    public function getAvailableProviders(): array
    {
        return array_keys($this->providers);
    }

    /**
     * Create a new user account
     */
    public function createUser(string $username, string $email, string $password, string $authType = 'manual'): User
    {
        // Check if provider exists
        if (!isset($this->providers[$authType])) {
            throw new AuthenticationException("Authentication provider '{$authType}' not found");
        }

        // Check if username already exists
        if ($this->db->record_exists('users', ['username' => $username])) {
            throw new AuthenticationException('Username already exists');
        }

        $currentTime = time();

        // Prepare user data using correct field names from our schema
        $userData = [
            'username' => $username,
            'email' => $email,
            'auth' => $authType,
            'status' => 'active',        // Use 'status' instead of 'active'
            'emailverified' => 0,        // Add required field
            'timecreated' => $currentTime,   // Use Unix timestamp
            'timemodified' => $currentTime   // Use Unix timestamp
        ];

        // For manual auth, hash the password
        if ($authType === 'manual') {
            $userData['password'] = password_hash($password, PASSWORD_DEFAULT);
        }

        // Insert user record
        $userId = $this->db->insert_record('users', $userData);

        return new User(
            $userId,
            $username,
            $email,
            $authType,
            true,
            $currentTime,
            null
        );
    }
}
