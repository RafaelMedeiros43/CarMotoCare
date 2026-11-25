<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * A SAPI Veículos não respondeu como esperado (timeout, erro HTTP ou resposta inválida).
 */
final class VehiclesApiException extends RuntimeException
{
}
