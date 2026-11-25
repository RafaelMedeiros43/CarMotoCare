<?php

namespace App\Http\Resources;

use App\Models\Part;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Part schema in docs/api/sapi-vehicles.yaml. The next and remaining values are calculated, never stored (D6).
 * Needs the "vehicle" relation (its km is used by kmRemaining); set it on the parts to avoid one query per part.
 *
 * @mixin Part
 */
class PartResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'vehicleId' => $this->vehicle_id,
            'name' => $this->name,
            'intervalKm' => $this->interval_km,
            'intervalMonths' => $this->interval_months,
            'intervalSource' => $this->interval_source,
            'lastServiceKm' => $this->last_service_km,
            'lastServiceDate' => $this->last_service_date->toDateString(),
            'nextKm' => $this->nextKm(),
            'nextDate' => $this->nextDate()?->toDateString(),
            'kmRemaining' => $this->kmRemaining($this->vehicle->km),
            'daysRemaining' => $this->daysRemaining(),
        ];
    }
}
