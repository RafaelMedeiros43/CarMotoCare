<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * PATCH /vehicles/{vehicleId} (VehicleUpdate in docs/api/sapi-vehicles.yaml).
 * Only descriptive data: km and type are never changed here (extra fields are ignored).
 */
class UpdateVehicleRequest extends UserScopedRequest
{
    private const FIELDS = ['brand', 'model', 'firstRegistration', 'plate'];

    public function rules(): array
    {
        return [
            'brand' => ['sometimes', 'string', 'min:1', 'max:50'],
            'model' => ['sometimes', 'string', 'min:1', 'max:50'],
            'firstRegistration' => ['sometimes', 'string', 'regex:'.CreateVehicleRequest::YEAR_MONTH],
            'plate' => [
                'sometimes', 'string', 'regex:'.CreateVehicleRequest::PLATE,
                Rule::unique('vehicles', 'plate')->ignore($this->route('vehicleId')),
            ],
        ];
    }

    public function after(): array
    {
        return [
            ...parent::after(),
            function (Validator $validator): void {
                if (! $this->hasAny(self::FIELDS)) {
                    $validator->errors()->add('body', 'Indique pelo menos um campo a alterar (brand, model, firstRegistration ou plate).');
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'brand.string' => 'A marca tem de ser texto.',
            'brand.min' => 'A marca não pode estar vazia.',
            'brand.max' => 'A marca não pode ter mais de 50 caracteres.',
            'model.string' => 'O modelo tem de ser texto.',
            'model.min' => 'O modelo não pode estar vazio.',
            'model.max' => 'O modelo não pode ter mais de 50 caracteres.',
            'firstRegistration.regex' => 'A 1.ª matrícula tem de ter o formato AAAA-MM.',
            'plate.regex' => 'A matrícula tem de ter o formato AB-24-CD (maiúsculas e números).',
            'plate.unique' => 'Já existe um veículo com esta matrícula.',
        ];
    }
}
