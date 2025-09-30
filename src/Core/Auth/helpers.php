<?php

use DevFramework\Core\Auth\AuthenticationManager;
use DevFramework\Core\Auth\User;
use DevFramework\Core\Auth\Exceptions\AuthenticationException;

/**
 * Authentication Helper Functions
 */

/**
 * Get the authentication manager instance
 */
function auth(): AuthenticationManager
{
    return AuthenticationManager::getInstance();
}

/**
 * Check if user is authenticated
 */
function is_authenticated(): bool
{
    return auth()->isAuthenticated();
}

/**
 * Get current authenticated user
 */
function current_user(): ?User
{
    return auth()->getCurrentUser();
}

/**
 * Require authentication - redirect if not authenticated
 */
function require_auth(string $redirectUrl = '/login'): void
{
    if (!is_authenticated()) {
        header("Location: $redirectUrl");
        exit;
    }
}

/**
 * Login user with credentials
 */
function login(string $username, string $password, ?string $authType = null): ?User
{
    try {
        return auth()->authenticate($username, $password, $authType);
    } catch (AuthenticationException $e) {
        return null;
    }
}

/**
 * Logout current user
 */
function logout(): void
{
    auth()->logout();
}

/**
 * Create new user account
 */
function create_user(string $username, string $email, string $password, string $authType = 'manual'): ?User
{
    try {
        return auth()->createUser($username, $email, $password, $authType);
    } catch (AuthenticationException $e) {
        return null;
    }
}

/**
 * Get available authentication providers
 */
function get_auth_providers(): array
{
    return auth()->getAvailableProviders();
}

/**
 * Check if user has specific authentication type
 */
function user_has_auth_type(string $authType): bool
{
    $user = current_user();
    return $user && $user->getAuthType() === $authType;
}
