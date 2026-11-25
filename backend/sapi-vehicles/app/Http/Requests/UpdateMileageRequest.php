<?php

namespace App\Http\Requests;

/**
 * PUT /vehicles/{vehicleId}/mileage (MileageUpdate in docs/api/sapi-vehicles.yaml), called by the OBD microservice.
 * That the km does not go down (R3, 409) is checked in the service, which knows the current km.
 */
class UpdateMileageRequest extends UserScopedRequest
{
    public function rules(): array
    {
        return [
            // The maximum is the limit of the INTEGER column.
            'km' => ['required', 'integer', 'min:0', 'max:2147483647'],
        ];
    }

    public function messages(): array
    {
        return [
            'km.required' => 'A quilometragem é obrigatória.',
            'km.integer' => 'A quilometragem tem de ser um número inteiro.',
            'km.min' => 'A quilometragem não pode ser negativa.',
            'km.max' => 'A quilometragem é demasiado grande.',
        ];
    }
}
