<?php

namespace App\Http\Requests;

use Illuminate\Validation\Validator;

/**
 * PATCH /vehicles/{vehicleId}/maintenances/{maintenanceId} (MaintenanceUpdate in docs/api/sapi-vehicles.yaml).
 * Corrects only the cost and the notes (R7); type, date, km and parts are never changed (extra fields are ignored).
 */
class UpdateMaintenanceRequest extends UserScopedRequest
{
    public function rules(): array
    {
        return [
            'cost' => ['sometimes', 'required', ...CreateMaintenanceRequest::MONEY],
            'notes' => ['sometimes', 'nullable', 'string', 'max:'.CreateMaintenanceRequest::NOTES_MAX],
        ];
    }

    public function after(): array
    {
        return [
            ...parent::after(),
            function (Validator $validator): void {
                if (! $this->hasAny(['cost', 'notes'])) {
                    $validator->errors()->add('body', 'Indique pelo menos um campo a alterar (cost ou notes).');
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'cost.required' => 'O custo não pode estar vazio.',
            'cost.numeric' => 'O custo tem de ser um número.',
            'cost.decimal' => 'O custo tem no máximo 2 casas decimais.',
            'cost.min' => 'O custo não pode ser negativo.',
            'cost.max' => 'O custo é demasiado grande.',
            'notes.string' => 'As notas têm de ser texto.',
            'notes.max' => 'As notas não podem ter mais de '.CreateMaintenanceRequest::NOTES_MAX.' caracteres.',
        ];
    }
}
