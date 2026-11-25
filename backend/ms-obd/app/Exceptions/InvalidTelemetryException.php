<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Leitura OBD que viola uma das regras de validação V1–V3 (a leitura é descartada).
 */
final class InvalidTelemetryException extends RuntimeException
{
    public function __construct(public readonly string $rule, string $reason)
    {
        parent::__construct($reason);
    }
}
