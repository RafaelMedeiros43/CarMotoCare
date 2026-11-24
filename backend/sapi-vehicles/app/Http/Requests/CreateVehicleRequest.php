<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

/**
 * POST /vehicles (VehicleCreate in docs/api/sapi-vehicles.yaml).
 * Extra fields (the EAPI also receives iucValidUntil, insuranceValidUntil and inspectionValidUntil) are ignored:
 * the deadlines are created by the EAPI through POST /vehicles/{vehicleId}/deadlines.
 * A duplicate plate is a 400 on the "plate" field, because the contract has no 409 here.
 */
class CreateVehicleRequest extends UserScopedRequest
{
    public const YEAR_MONTH = '/^\d{4}-(0[1-9]|1[0-2])$/';

    public const PLATE = '/^[A-Z0-9]{2}-[A-Z0-9]{2}-[A-Z0-9]{2}$/';

    protected function userIdRequired(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'in:CARRO,MOTO'],
            'brand' => ['required', 'string', 'min:1', 'max:50'],
            'model' => ['required', 'string', 'min:1', 'max:50'],
            'firstRegistration' => ['required', 'string', 'regex:'.self::YEAR_MONTH],
            'plate' => ['required', 'string', 'regex:'.self::PLATE, Rule::unique('vehicles', 'plate')],
            'km' => ['required', 'integer', 'min:0', 'max:2147483647'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => 'O tipo é obrigatório.',
            'type.in' => 'O tipo tem de ser CARRO ou MOTO.',
            'brand.required' => 'A marca é obrigatória.',
            'brand.string' => 'A marca tem de ser texto.',
            'brand.max' => 'A marca não pode ter mais de 50 caracteres.',
            'model.required' => 'O modelo é obrigatório.',
            'model.string' => 'O modelo tem de ser texto.',
            'model.max' => 'O modelo não pode ter mais de 50 caracteres.',
            'firstRegistration.required' => 'A data da 1.ª matrícula é obrigatória.',
            'firstRegistration.regex' => 'A 1.ª matrícula tem de ter o formato AAAA-MM.',
            'plate.required' => 'A matrícula é obrigatória.',
            'plate.regex' => 'A matrícula tem de ter o formato AB-24-CD (maiúsculas e números).',
            'plate.unique' => 'Já existe um veículo com esta matrícula.',
            'km.required' => 'A quilometragem é obrigatória.',
            'km.integer' => 'A quilometragem tem de ser um número inteiro.',
            'km.min' => 'A quilometragem não pode ser negativa.',
            'km.max' => 'A quilometragem é demasiado grande.',
        ];
    }
}
