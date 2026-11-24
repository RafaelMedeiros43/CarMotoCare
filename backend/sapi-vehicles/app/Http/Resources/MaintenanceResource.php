<?php

namespace App\Http\Resources;

use App\Models\Maintenance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Maintenance schema in docs/api/sapi-vehicles.yaml. Needs the "parts" relation loaded (MaintenanceService does it).
 * Costs are NUMERIC(10,2) and arrive as text ("146.00"); the contract says number.
 *
 * @mixin Maintenance
 */
class MaintenanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'vehicleId' => $this->vehicle_id,
            'type' => $this->type,
            'date' => $this->date->toDateString(),
            'km' => $this->km,
            'cost' => (float) $this->cost,
            'notes' => $this->notes,
            'parts' => $this->parts->sortBy('name')->values()->map(fn ($part) => [
                'partId' => $part->id,
                'partName' => $part->name,
                'cost' => $part->pivot->cost === null ? null : (float) $part->pivot->cost,
            ])->all(),
            'createdAt' => $this->created_at?->toIso8601ZuluString(),
        ];
    }
}
