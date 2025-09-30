<?php

namespace DevFramework\Core\Auth\Providers;

use DevFramework\Core\Database\Database;
use DevFramework\Core\Auth\Exceptions\AuthenticationException;

/**
 * Manual Authentication Provider - Traditional username/password authentication
 */
class ManualAuthProvider implements AuthProviderInterface
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Authenticate user with username and password
     */
    public function authenticate(string $username, string $password): bool
    {
        // Get user record from database
        $userRecord = $this->db->get_record('users', ['username' => $username, 'auth' => 'manual']);

        if (!$userRecord) {
            return false;
        }

        // Check if user is active (using 'status' field from our schema)
        if (!isset($userRecord->status) || $userRecord->status !== 'active') {
            throw new AuthenticationException('User account is inactive');
        }

        // Verify password
        if (!isset($userRecord->password) || !password_verify($password, $userRecord->password)) {
            return false;
        }

        return true;
    }

    /**
     * Get provider name
     */
    public function getName(): string
    {
        return 'Manual Authentication';
    }

    /**
     * Manual auth supports user creation
     */
    public function supportsUserCreation(): bool
    {
        return true;
    }

    /**
     * Manual auth supports password changes
     */
    public function supportsPasswordChange(): bool
    {
        return true;
    }

    /**
     * Change user password
     */
    public function changePassword(string $username, string $newPassword): bool
    {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

        $updated = $this->db->update_record('users', [
            'password' => $hashedPassword
        ], ['username' => $username, 'auth' => 'manual']);

        return $updated !== false;
    }

    /**
     * Validate password strength
     */
    public function validatePassword(string $password): array
    {
        $errors = [];

        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters long';
        }

        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must contain at least one uppercase letter';
        }

        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Password must contain at least one lowercase letter';
        }

        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must contain at least one number';
        }

        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = 'Password must contain at least one special character';
        }

        return $errors;
    }
}
