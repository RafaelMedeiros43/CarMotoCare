<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * PATCH /vehicles/{vehicleId} (VehicleUpdate in docs/api/eapi-web.yaml).
 * Only descriptive data: km and type are never changed here (extra fields are ignored).
 */
class UpdateVehicleRequest extends FormRequest
{
    private const FIELDS = ['brand', 'model', 'firstRegistration', 'plate'];

    public function rules(): array
    {
        return [
            'brand' => ['sometimes', 'required', 'string', 'max:50'],
            'model' => ['sometimes', 'required', 'string', 'max:50'],
            'firstRegistration' => ['sometimes', 'required', 'string', 'regex:'.CreateVehicleRequest::YEAR_MONTH],
            'plate' => ['sometimes', 'required', 'string', 'regex:'.CreateVehicleRequest::PLATE],
        ];
    }

    public function after(): array
    {
        return [
            // minProperties: 1 in the contract (km and type alone change nothing).
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
            'brand.required' => 'A marca não pode estar vazia.',
            'brand.string' => 'A marca tem de ser texto.',
            'brand.max' => 'A marca não pode ter mais de 50 caracteres.',
            'model.required' => 'O modelo não pode estar vazio.',
            'model.string' => 'O modelo tem de ser texto.',
            'model.max' => 'O modelo não pode ter mais de 50 caracteres.',
            'firstRegistration.required' => 'A data da 1.ª matrícula não pode estar vazia.',
            'firstRegistration.regex' => 'A 1.ª matrícula tem de ter o formato AAAA-MM.',
            'plate.required' => 'A matrícula não pode estar vazia.',
            'plate.regex' => 'A matrícula tem de ter o formato AB-24-CD (maiúsculas e números).',
        ];
    }
}
