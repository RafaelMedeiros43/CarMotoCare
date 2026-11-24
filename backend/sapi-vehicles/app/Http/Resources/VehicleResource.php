<?php

namespace App\Http\Resources;

use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Vehicle schema in docs/api/sapi-vehicles.yaml. Needs the "parts" relation and open_alerts_count loaded
 * (VehicleService does it).
 *
 * @mixin Vehicle
 */
class VehicleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'userId' => $this->user_id,
            'type' => $this->type,
            'brand' => $this->brand,
            'model' => $this->model,
            'firstRegistration' => $this->first_registration,
            'plate' => $this->plate,
            'km' => $this->km,
            'nextService' => $this->nextService(),
            'openAlerts' => (int) $this->open_alerts_count,
            'createdAt' => $this->created_at?->toIso8601ZuluString(),
        ];
    }
}
