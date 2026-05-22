<?php

namespace App\Exceptions;

use Exception;

class InsufficientBalanceException extends Exception
{
    public function __construct(string $message = 'Saldo insuficiente para realizar esta operação.')
    {
        parent::__construct($message);
    }
}
