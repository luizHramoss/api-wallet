<?php

namespace App\Exceptions;

use Exception;

class InsufficientQuantityException extends Exception
{
    public function __construct(string $message = 'Quantidade insuficiente para realizar esta operação.')
    {
        parent::__construct($message);
    }
}
