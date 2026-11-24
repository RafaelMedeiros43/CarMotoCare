<?php

namespace App\Http\Resources;

use App\Models\Deadline;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Deadline schema in docs/api/sapi-vehicles.yaml.
 *
 * @mixin Deadline
 */
class DeadlineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'vehicleId' => $this->vehicle_id,
            'type' => $this->type,
            'status' => $this->status,
            'dueDate' => $this->due_date->toDateString(),
            'paidAt' => $this->paid_at?->toDateString(),
            // The column is NUMERIC(10,2) and arrives as text ("146.00"); the contract says number.
            'amount' => $this->amount === null ? null : (float) $this->amount,
            'description' => $this->description,
            'createdAt' => $this->created_at?->toIso8601ZuluString(),
        ];
    }
}
