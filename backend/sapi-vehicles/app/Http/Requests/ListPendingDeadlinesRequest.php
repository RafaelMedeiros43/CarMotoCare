<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** GET /deadlines: optional filters type and dueBefore (used by the maintenance and alerts microservice). */
class ListPendingDeadlinesRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'type' => ['sometimes', 'string', 'in:IUC,SEGURO,INSPECAO'],
            'dueBefore' => ['sometimes', 'string', 'date_format:Y-m-d'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.in' => 'O tipo tem de ser IUC, SEGURO ou INSPECAO.',
            'dueBefore.date_format' => 'A data tem de ter o formato AAAA-MM-DD.',
        ];
    }
}
