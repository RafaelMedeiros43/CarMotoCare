<?php

namespace App\Http\Requests;

/**
 * POST /vehicles/{vehicleId}/deadlines (DeadlineCreate in docs/api/sapi-vehicles.yaml).
 * Only the type and the due date: a new deadline is always PENDENTE and unpaid (extra fields are ignored).
 * A past date is accepted (an overdue IUC is a real case and will raise an alert).
 */
class CreateDeadlineRequest extends UserScopedRequest
{
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'in:IUC,SEGURO,INSPECAO'],
            'dueDate' => ['required', 'string', 'date_format:Y-m-d'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => 'O tipo é obrigatório.',
            'type.in' => 'O tipo tem de ser IUC, SEGURO ou INSPECAO.',
            'dueDate.required' => 'A data limite é obrigatória.',
            'dueDate.string' => 'A data limite tem de ser texto no formato AAAA-MM-DD.',
            'dueDate.date_format' => 'A data limite tem de ter o formato AAAA-MM-DD.',
        ];
    }
}
