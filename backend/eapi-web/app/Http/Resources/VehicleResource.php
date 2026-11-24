<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Vehicle schema in docs/api/eapi-web.yaml, built from the sapi-vehicles response (an array).
 * Only the contract fields are returned, so nothing extra from the SAPI reaches the browser.
 */
class VehicleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $next = $this->resource['nextService'] ?? null;

        return [
            'id' => $this->resource['id'],
            'userId' => $this->resource['userId'],
            'type' => $this->resource['type'],
            'brand' => $this->resource['brand'],
            'model' => $this->resource['model'],
            'firstRegistration' => $this->resource['firstRegistration'],
            'plate' => $this->resource['plate'],
            'km' => $this->resource['km'],
            'nextService' => $next === null ? null : [
                'partId' => $next['partId'],
                'partName' => $next['partName'],
                'targetKm' => $next['targetKm'],
                'kmRemaining' => $next['kmRemaining'],
                'dueDate' => $next['dueDate'] ?? null,
            ],
            'openAlerts' => $this->resource['openAlerts'],
            'createdAt' => $this->resource['createdAt'] ?? null,
        ];
    }
}
