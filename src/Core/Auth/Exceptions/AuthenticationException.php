<?php

namespace DevFramework\Core\Auth\Exceptions;

use Exception;

/**
 * Authentication Exception - thrown when authentication fails
 */
class AuthenticationException extends Exception
{
    public function __construct(string $message = "Authentication failed", int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
