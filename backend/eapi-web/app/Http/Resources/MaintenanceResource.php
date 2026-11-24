<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Maintenance schema in docs/api/eapi-web.yaml, built from the sapi-vehicles response (an array).
 * Only the contract fields are returned, so nothing extra from the SAPI reaches the browser.
 */
class MaintenanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource['id'],
            'vehicleId' => $this->resource['vehicleId'],
            'type' => $this->resource['type'],
            'date' => $this->resource['date'],
            'km' => $this->resource['km'],
            'cost' => $this->resource['cost'],
            'notes' => $this->resource['notes'] ?? null,
            'parts' => array_map(fn (array $part) => [
                'partId' => $part['partId'],
                'partName' => $part['partName'] ?? null,
                'cost' => $part['cost'] ?? null,
            ], $this->resource['parts'] ?? []),
            'createdAt' => $this->resource['createdAt'] ?? null,
        ];
    }
}
