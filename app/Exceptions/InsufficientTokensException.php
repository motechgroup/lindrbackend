<?php

namespace App\Exceptions;

use Exception;

class InsufficientTokensException extends Exception
{
    public function __construct(string $message = 'Insufficient tokens to initiate call.')
    {
        parent::__construct($message);
    }
}
