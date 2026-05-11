<?php

namespace App\Services;

use RuntimeException;

class MobileApiException extends RuntimeException
{
    /**
     * @param  array<string, array<int, string>|string>  $errors
     */
    public function __construct(
        string $message,
        public readonly int $status = 0,
        public readonly array $errors = [],
    ) {
        parent::__construct($message, $status);
    }
}
