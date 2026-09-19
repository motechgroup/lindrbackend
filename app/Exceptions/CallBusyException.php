<?php

namespace App\Exceptions;

use Exception;

class CallBusyException extends Exception
{
    public function __construct(string $message = 'User is currently on another call.')
    {
        parent::__construct($message);
    }
}
