<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * MileageResult schema in docs/api/sapi-vehicles.yaml. Wraps the array returned by VehicleService::updateMileage.
 */
class MileageResultResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'vehicleId' => $this->resource['vehicleId'],
            'km' => $this->resource['km'],
            'previousKm' => $this->resource['previousKm'],
            'nextAlertKm' => $this->resource['nextAlertKm'],
        ];
    }
}
