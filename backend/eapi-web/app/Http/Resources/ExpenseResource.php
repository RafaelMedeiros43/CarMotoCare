<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Expense schema in docs/api/eapi-web.yaml, built from the sapi-vehicles response (an array).
 */
class ExpenseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource['id'],
            'category' => $this->resource['category'],
            'date' => $this->resource['date'],
            'amount' => $this->resource['amount'],
            'description' => $this->resource['description'] ?? null,
            'km' => $this->resource['km'] ?? null,
        ];
    }
}
