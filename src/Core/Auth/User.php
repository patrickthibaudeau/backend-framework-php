<?php

namespace DevFramework\Core\Auth;

/**
 * User class representing an authenticated user
 */
class User
{
    private int $id;
    private string $username;
    private string $email;
    private string $authType;
    private bool $active;
    private ?string $createdAt;
    private ?string $lastLogin;

    public function __construct(
        int $id,
        string $username,
        string $email,
        string $authType,
        bool $active = true,
        ?string $createdAt = null,
        ?string $lastLogin = null
    ) {
        $this->id = $id;
        $this->username = $username;
        $this->email = $email;
        $this->authType = $authType;
        $this->active = $active;
        $this->createdAt = $createdAt;
        $this->lastLogin = $lastLogin;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getAuthType(): string
    {
        return $this->authType;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function getCreatedAt(): ?string
    {
        return $this->createdAt;
    }

    public function getLastLogin(): ?string
    {
        return $this->lastLogin;
    }

    /**
     * Set user as inactive
     */
    public function deactivate(): void
    {
        $this->active = false;
    }

    /**
     * Set user as active
     */
    public function activate(): void
    {
        $this->active = true;
    }

    /**
     * Convert user to array for API responses
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'email' => $this->email,
            'auth_type' => $this->authType,
            'active' => $this->active,
            'created_at' => $this->createdAt,
            'last_login' => $this->lastLogin
        ];
    }
}
