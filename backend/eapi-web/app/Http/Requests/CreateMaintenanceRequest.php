<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /vehicles/{vehicleId}/maintenances (MaintenanceCreate in docs/api/eapi-web.yaml).
 * That each partId belongs to the vehicle is decided by the sapi-vehicles (400 on parts.N.partId).
 */
class CreateMaintenanceRequest extends FormRequest
{
    public const TYPES = 'REVISAO_PERIODICA,MUDANCA_OLEO,SUBSTITUICAO_PNEUS,REPARACAO,AVARIA,OUTRO';

    /** Money: euros with at most 2 decimals (NUMERIC(10,2) in the database). */
    public const MONEY = ['numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'];

    public const NOTES_MAX = 1000;

    /**
     * A UUID is valid in upper or lower case: normalize it first so the "distinct" rule (the same part twice)
     * works whatever the case, and the SAPI always receives it in lower case.
     */
    protected function prepareForValidation(): void
    {
        $parts = $this->input('parts');

        if (is_array($parts)) {
            $this->merge(['parts' => array_map(
                fn ($part) => is_array($part) && is_string($part['partId'] ?? null)
                    ? [...$part, 'partId' => strtolower($part['partId'])]
                    : $part,
                $parts,
            )]);
        }
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'in:'.self::TYPES],
            // A maintenance that has not happened yet cannot be registered.
            'date' => ['required', 'string', 'date_format:Y-m-d', 'before_or_equal:today'],
            'km' => ['nullable', 'integer', 'min:0', 'max:2147483647'],
            'cost' => ['required', ...self::MONEY],
            'notes' => ['nullable', 'string', 'max:'.self::NOTES_MAX],
            'parts' => ['nullable', 'array'],
            'parts.*' => ['array'],
            'parts.*.partId' => ['required', 'uuid', 'distinct'],
            'parts.*.cost' => ['nullable', ...self::MONEY],
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => 'O tipo é obrigatório.',
            'type.in' => 'O tipo de intervenção não é válido.',
            'date.required' => 'A data é obrigatória.',
            'date.string' => 'A data tem de ser texto no formato AAAA-MM-DD.',
            'date.date_format' => 'A data tem de ter o formato AAAA-MM-DD.',
            'date.before_or_equal' => 'A data não pode ser no futuro.',
            'km.integer' => 'A quilometragem tem de ser um número inteiro.',
            'km.min' => 'A quilometragem não pode ser negativa.',
            'km.max' => 'A quilometragem é demasiado grande.',
            'cost.required' => 'O custo é obrigatório.',
            'cost.numeric' => 'O custo tem de ser um número.',
            'cost.decimal' => 'O custo tem no máximo 2 casas decimais.',
            'cost.min' => 'O custo não pode ser negativo.',
            'cost.max' => 'O custo é demasiado grande.',
            'notes.string' => 'As notas têm de ser texto.',
            'notes.max' => 'As notas não podem ter mais de '.self::NOTES_MAX.' caracteres.',
            'parts.array' => 'As peças têm de ser uma lista.',
            'parts.*.array' => 'Cada peça tem de ser um objeto com partId.',
            'parts.*.partId.required' => 'O partId é obrigatório.',
            'parts.*.partId.uuid' => 'O partId tem de ser um UUID.',
            'parts.*.partId.distinct' => 'A mesma peça não pode aparecer duas vezes.',
            'parts.*.cost.numeric' => 'O custo da peça tem de ser um número.',
            'parts.*.cost.decimal' => 'O custo da peça tem no máximo 2 casas decimais.',
            'parts.*.cost.min' => 'O custo da peça não pode ser negativo.',
            'parts.*.cost.max' => 'O custo da peça é demasiado grande.',
        ];
    }
}
