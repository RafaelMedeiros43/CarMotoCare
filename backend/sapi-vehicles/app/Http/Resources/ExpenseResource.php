<?php

namespace App\Http\Resources;

use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Expense schema in docs/api/sapi-vehicles.yaml (one row of the expenses view).
 *
 * @mixin Expense
 */
class ExpenseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category' => $this->category,
            'date' => $this->date->toDateString(),
            'amount' => (float) $this->amount,
            'description' => $this->description,
            // Only maintenances have a km (null for the other categories).
            'km' => $this->km,
        ];
    }
}
