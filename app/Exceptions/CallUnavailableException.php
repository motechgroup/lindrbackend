<?php

namespace App\Exceptions;

use Exception;

class CallUnavailableException extends Exception
{
    public function __construct(string $message = 'User is currently offline/unavailable.')
    {
        parent::__construct($message);
    }
}
