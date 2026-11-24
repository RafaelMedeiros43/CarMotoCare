<?php

namespace App\Exceptions;

use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Renders every error as RFC 7807 Problem Details (application/problem+json),
 * following the Problem schema in docs/api/sapi-vehicles.yaml.
 *
 * Laravel converts ModelNotFoundException into a 404 HttpException before calling this renderer.
 */
class ProblemRenderer
{
    private const TITLES = [
        400 => 'Pedido inválido',
        401 => 'Não autenticado',
        403 => 'Acesso negado',
        404 => 'Não encontrado',
        405 => 'Método não permitido',
        409 => 'Conflito',
        500 => 'Erro interno',
    ];

    private const DETAILS = [
        400 => 'Há campos inválidos.',
        404 => 'O recurso pedido não existe.',
        405 => 'Este método não é suportado neste endereço.',
        500 => 'Ocorreu um erro inesperado.',
    ];

    public function __invoke(Throwable $e, Request $request): ?JsonResponse
    {
        // A response built on purpose by the code is returned as is.
        if ($e instanceof HttpResponseException) {
            return null;
        }

        if ($e instanceof ProblemException) {
            return $this->problem($e->status, $e->title, $e->detail, $e->errors);
        }

        if ($e instanceof ValidationException) {
            return $this->problem(400, errors: $this->validationErrors($e));
        }

        if ($e instanceof HttpExceptionInterface) {
            // Framework messages (e.g. "The route ... could not be found.") are in English and
            // can reveal internals, so only the Portuguese default detail is shown.
            return $this->problem($e->getStatusCode(), headers: $e->getHeaders());
        }

        // Unexpected error: it is still logged by Laravel, but no internal details are returned.
        return $this->problem(500);
    }

    /**
     * @param  array<int, array{field: string, message: string}>  $errors
     * @param  array<string, string>  $headers
     */
    private function problem(
        int $status,
        ?string $title = null,
        ?string $detail = null,
        array $errors = [],
        array $headers = [],
    ): JsonResponse {
        $body = [
            'type' => 'about:blank',
            'title' => $title ?? self::TITLES[$status] ?? (JsonResponse::$statusTexts[$status] ?? 'Erro'),
            'status' => $status,
        ];

        $detail ??= self::DETAILS[$status] ?? null;
        if ($detail !== null) {
            $body['detail'] = $detail;
        }

        if ($errors !== []) {
            $body['errors'] = $errors;
        }

        return new JsonResponse(
            $body,
            $status,
            ['Content-Type' => 'application/problem+json'] + $headers,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
    }

    /**
     * Converts Laravel's ['field' => ['msg1', 'msg2']] into [{field, message}, ...].
     *
     * @return array<int, array{field: string, message: string}>
     */
    private function validationErrors(ValidationException $e): array
    {
        $errors = [];
        foreach ($e->errors() as $field => $messages) {
            foreach ($messages as $message) {
                $errors[] = ['field' => $field, 'message' => $message];
            }
        }

        return $errors;
    }
}
