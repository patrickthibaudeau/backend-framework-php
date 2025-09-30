<?php

namespace DevFramework\Core\Auth\Providers;

/**
 * Interface for authentication providers
 */
interface AuthProviderInterface
{
    /**
     * Authenticate a user with the given credentials
     *
     * @param string $username The username
     * @param string $password The password or token
     * @return bool True if authentication successful, false otherwise
     */
    public function authenticate(string $username, string $password): bool;

    /**
     * Get the provider name
     *
     * @return string The provider name
     */
    public function getName(): string;

    /**
     * Check if the provider supports user creation
     *
     * @return bool True if provider supports creating users
     */
    public function supportsUserCreation(): bool;

    /**
     * Check if the provider supports password changes
     *
     * @return bool True if provider supports password changes
     */
    public function supportsPasswordChange(): bool;
}
