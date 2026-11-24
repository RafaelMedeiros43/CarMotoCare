<?php

namespace App\Http\Requests;

/** GET /vehicles: X-User-Id plus the type, limit and offset query parameters. */
class ListVehiclesRequest extends UserScopedRequest
{
    public const DEFAULT_LIMIT = 20;

    protected function userIdRequired(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['sometimes', 'string', 'in:CARRO,MOTO'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'offset' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.in' => 'O tipo tem de ser CARRO ou MOTO.',
            'limit.integer' => 'O limite tem de ser um número inteiro.',
            'limit.min' => 'O limite tem de ser pelo menos 1.',
            'limit.max' => 'O limite não pode ser superior a 100.',
            'offset.integer' => 'O offset tem de ser um número inteiro.',
            'offset.min' => 'O offset não pode ser negativo.',
        ];
    }
}
