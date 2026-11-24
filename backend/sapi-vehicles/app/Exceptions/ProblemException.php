<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Business error that is rendered as RFC 7807 Problem Details (see ProblemRenderer).
 * Services throw it for expected failures, e.g. 400 (duplicate plate) or 409 (conflict).
 */
class ProblemException extends RuntimeException
{
    /**
     * @param  array<int, array{field: string, message: string}>  $errors
     */
    public function __construct(
        public readonly int $status,
        public readonly string $title,
        public readonly ?string $detail = null,
        public readonly array $errors = [],
    ) {
        parent::__construct($detail ?? $title);
    }
}
