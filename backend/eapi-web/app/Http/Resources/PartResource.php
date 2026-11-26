<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Part schema in docs/api/eapi-web.yaml, built from the sapi-vehicles response (an array).
 */
class PartResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource['id'],
            'vehicleId' => $this->resource['vehicleId'],
            'name' => $this->resource['name'],
            'intervalKm' => $this->resource['intervalKm'],
            'intervalMonths' => $this->resource['intervalMonths'],
            'intervalSource' => $this->resource['intervalSource'],
            'lastServiceKm' => $this->resource['lastServiceKm'] ?? null,
            'lastServiceDate' => $this->resource['lastServiceDate'] ?? null,
            'nextKm' => $this->resource['nextKm'] ?? null,
            'nextDate' => $this->resource['nextDate'] ?? null,
            'kmRemaining' => $this->resource['kmRemaining'] ?? null,
            'daysRemaining' => $this->resource['daysRemaining'] ?? null,
        ];
    }
}
