<?php

namespace App\Exceptions;

use RuntimeException;

class AsaasApiException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        string $message,
        public readonly int $status = 0,
        public readonly array $context = [],
    ) {
        parent::__construct($message);
    }
}
