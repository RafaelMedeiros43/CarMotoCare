<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class OverpassUnavailableException extends RuntimeException
{
    /**
     * Renderiza a exceção como RFC 7807 (502 Bad Gateway).
     */
    public function render(Request $request): JsonResponse
    {
        return response()->json(
            [
                'type' => 'about:blank',
                'title' => 'Serviço externo indisponível',
                'status' => 502,
                'detail' => $this->getMessage() !== ''
                    ? $this->getMessage()
                    : 'O serviço de mapas (Overpass API) não está disponível.',
            ],
            502,
            ['Content-Type' => 'application/problem+json'],
        );
    }
}
