<?php

namespace DevFramework\Core\Module\Exceptions;

use Exception;

/**
 * Exception thrown when module operations fail
 */
class ModuleException extends Exception
{
    public function __construct(string $message = "", int $code = 0, Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
