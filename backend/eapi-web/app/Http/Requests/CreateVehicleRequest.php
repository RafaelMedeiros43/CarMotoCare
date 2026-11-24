<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /vehicles (VehicleCreate in docs/api/eapi-web.yaml).
 * The plate uniqueness is decided by the sapi-vehicles (400 on the "plate" field), not here.
 * The three validity dates are optional; for now they are validated but not used (see VehicleService::create).
 */
class CreateVehicleRequest extends FormRequest
{
    public const YEAR_MONTH = '/^\d{4}-(0[1-9]|1[0-2])$/';

    public const PLATE = '/^[A-Z0-9]{2}-[A-Z0-9]{2}-[A-Z0-9]{2}$/';

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'in:CARRO,MOTO'],
            'brand' => ['required', 'string', 'max:50'],
            'model' => ['required', 'string', 'max:50'],
            'firstRegistration' => ['required', 'string', 'regex:'.self::YEAR_MONTH],
            'plate' => ['required', 'string', 'regex:'.self::PLATE],
            'km' => ['required', 'integer', 'min:0', 'max:2147483647'],
            'iucValidUntil' => ['nullable', 'date_format:Y-m-d'],
            'insuranceValidUntil' => ['nullable', 'date_format:Y-m-d'],
            'inspectionValidUntil' => ['nullable', 'date_format:Y-m-d'],
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
            'km.required' => 'A quilometragem é obrigatória.',
            'km.integer' => 'A quilometragem tem de ser um número inteiro.',
            'km.min' => 'A quilometragem não pode ser negativa.',
            'km.max' => 'A quilometragem é demasiado grande.',
            'iucValidUntil.date_format' => 'A validade do IUC tem de ter o formato AAAA-MM-DD.',
            'insuranceValidUntil.date_format' => 'A validade do seguro tem de ter o formato AAAA-MM-DD.',
            'inspectionValidUntil.date_format' => 'A validade da inspeção tem de ter o formato AAAA-MM-DD.',
        ];
    }
}
