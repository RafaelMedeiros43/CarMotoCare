<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

final class SearchWorkshopsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lon' => ['required', 'numeric', 'between:-180,180'],
            'radiusKm' => ['nullable', 'numeric', 'between:1,50'],
            'type' => ['nullable', Rule::in(['OFICINA', 'LOJA_PNEUS'])],
            'q' => ['nullable', 'string'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        $errors = [];

        foreach ($validator->errors()->messages() as $field => $messages) {
            foreach ($messages as $message) {
                $errors[] = [
                    'field' => (string) $field,
                    'message' => (string) $message,
                ];
            }
        }

        throw new HttpResponseException(response()->json(
            [
                'type' => 'about:blank',
                'title' => 'Pedido inválido',
                'status' => 400,
                'detail' => 'Um ou mais parâmetros da pesquisa são inválidos.',
                'errors' => $errors,
            ],
            400,
            ['Content-Type' => 'application/problem+json'],
        ));
    }
}
