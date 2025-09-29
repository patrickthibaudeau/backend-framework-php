<?php

namespace DevFramework\Core\Database;

use Exception;

/**
 * Database-specific exception class
 */
class DatabaseException extends Exception
{
    public function __construct(string $message = "", int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
