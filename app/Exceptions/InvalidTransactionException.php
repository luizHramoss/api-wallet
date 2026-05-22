<?php

namespace App\Exceptions;

use Exception;

class InvalidTransactionException extends Exception
{
    public function __construct(string $message = 'Operação financeira inválida.')
    {
        parent::__construct($message);
    }
}
