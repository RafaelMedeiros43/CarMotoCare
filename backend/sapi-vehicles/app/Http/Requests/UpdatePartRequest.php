<?php

namespace App\Http\Requests;

use Illuminate\Validation\Validator;

/**
 * PATCH /vehicles/{vehicleId}/parts/{partId} (PartUpdate in docs/api/sapi-vehicles.yaml).
 * Changes only the interval and the reference (last service); other fields are ignored.
 * The rules that need the database (km of the vehicle, at least one active limit) are checked in PartService.
 */
class UpdatePartRequest extends UserScopedRequest
{
    private const FIELDS = ['intervalKm', 'intervalMonths', 'lastServiceDate', 'lastServiceKm'];

    /** Limit of the INTEGER columns. */
    private const MAX_KM = 2147483647;

    private const MAX_MONTHS = 999;

    public function rules(): array
    {
        return [
            // null disables the limit.
            'intervalKm' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:'.self::MAX_KM],
            'intervalMonths' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:'.self::MAX_MONTHS],
            // The reference cannot be removed: when sent, it needs a value.
            'lastServiceDate' => ['sometimes', 'required', 'string', 'date_format:Y-m-d', 'before_or_equal:today'],
            'lastServiceKm' => ['sometimes', 'required', 'integer', 'min:0', 'max:'.self::MAX_KM],
        ];
    }

    public function after(): array
    {
        return [
            ...parent::after(),
            function (Validator $validator): void {
                if (! $this->hasAny(self::FIELDS)) {
                    $validator->errors()->add('body', 'Indique pelo menos um campo a alterar (intervalKm, intervalMonths, lastServiceDate ou lastServiceKm).');
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'intervalKm.integer' => 'O intervalo em km tem de ser um número inteiro.',
            'intervalKm.min' => 'O intervalo em km tem de ser pelo menos 1.',
            'intervalKm.max' => 'O intervalo em km é demasiado grande.',
            'intervalMonths.integer' => 'O intervalo em meses tem de ser um número inteiro.',
            'intervalMonths.min' => 'O intervalo em meses tem de ser pelo menos 1.',
            'intervalMonths.max' => 'O intervalo em meses não pode ser superior a '.self::MAX_MONTHS.'.',
            'lastServiceDate.required' => 'A data do último serviço não pode estar vazia.',
            'lastServiceDate.string' => 'A data do último serviço tem de ser texto no formato AAAA-MM-DD.',
            'lastServiceDate.date_format' => 'A data do último serviço tem de ter o formato AAAA-MM-DD.',
            'lastServiceDate.before_or_equal' => 'A data do último serviço não pode ser no futuro.',
            'lastServiceKm.required' => 'A quilometragem do último serviço não pode estar vazia.',
            'lastServiceKm.integer' => 'A quilometragem do último serviço tem de ser um número inteiro.',
            'lastServiceKm.min' => 'A quilometragem do último serviço não pode ser negativa.',
            'lastServiceKm.max' => 'A quilometragem do último serviço é demasiado grande.',
        ];
    }
}
