<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /vehicles/{vehicleId}/deadlines (DeadlineCreate in docs/api/eapi-web.yaml).
 * A past date is accepted (an overdue deadline is a real case). The 409 for a second pending deadline of the
 * same type is decided by the sapi-vehicles.
 */
class CreateDeadlineRequest extends FormRequest
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
