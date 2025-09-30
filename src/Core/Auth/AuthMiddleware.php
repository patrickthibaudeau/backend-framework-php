<?php

namespace DevFramework\Core\Auth;

use DevFramework\Core\Auth\AuthenticationManager;

/**
 * Authentication Middleware - Protects routes and handles authentication flow
 */
class AuthMiddleware
{
    private AuthenticationManager $auth;

    public function __construct()
    {
        $this->auth = AuthenticationManager::getInstance();
    }

    /**
     * Handle authentication middleware
     */
    public function handle(callable $next, array $options = [])
    {
        $redirectUrl = $options['redirect'] ?? '/login';
        $requireActive = $options['require_active'] ?? true;
        $allowedAuthTypes = $options['auth_types'] ?? [];

        // Check if user is authenticated
        if (!$this->auth->isAuthenticated()) {
            $this->redirectToLogin($redirectUrl);
            return;
        }

        $user = $this->auth->getCurrentUser();

        // Check if user account is active
        if ($requireActive && !$user->isActive()) {
            $this->handleInactiveUser();
            return;
        }

        // Check if user has allowed authentication type
        if (!empty($allowedAuthTypes) && !in_array($user->getAuthType(), $allowedAuthTypes)) {
            $this->handleUnauthorizedAuthType();
            return;
        }

        // Continue to the next middleware/handler
        return $next();
    }

    /**
     * Middleware for guest users only (login/register pages)
     */
    public function guestOnly(callable $next, array $options = [])
    {
        $redirectUrl = $options['redirect'] ?? '/dashboard';

        if ($this->auth->isAuthenticated()) {
            header("Location: $redirectUrl");
            exit;
        }

        return $next();
    }

    /**
     * Middleware for specific authentication types only
     */
    public function authType(callable $next, array $allowedTypes = [])
    {
        if (!$this->auth->isAuthenticated()) {
            $this->redirectToLogin();
            return;
        }

        $user = $this->auth->getCurrentUser();
        if (!in_array($user->getAuthType(), $allowedTypes)) {
            $this->handleUnauthorizedAuthType();
            return;
        }

        return $next();
    }

    /**
     * API Authentication middleware (returns JSON responses)
     */
    public function apiAuth(callable $next, array $options = [])
    {
        if (!$this->auth->isAuthenticated()) {
            $this->jsonResponse(['error' => 'Authentication required'], 401);
            return;
        }

        $user = $this->auth->getCurrentUser();
        if (!$user->isActive()) {
            $this->jsonResponse(['error' => 'Account inactive'], 403);
            return;
        }

        return $next();
    }

    /**
     * Redirect to login page
     */
    private function redirectToLogin(string $loginUrl = '/login'): void
    {
        $currentUrl = $_SERVER['REQUEST_URI'] ?? '/';
        $redirectUrl = $loginUrl . '?redirect=' . urlencode($currentUrl);

        header("Location: $redirectUrl");
        exit;
    }

    /**
     * Handle inactive user
     */
    private function handleInactiveUser(): void
    {
        $this->auth->logout();
        header("Location: /login?error=account_inactive");
        exit;
    }

    /**
     * Handle unauthorized authentication type
     */
    private function handleUnauthorizedAuthType(): void
    {
        header("Location: /unauthorized");
        exit;
    }

    /**
     * Send JSON response
     */
    private function jsonResponse(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}
