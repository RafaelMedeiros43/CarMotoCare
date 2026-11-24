<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Deadline schema in docs/api/eapi-web.yaml, built from the sapi-vehicles response (an array).
 * Only the contract fields are returned, so nothing extra from the SAPI reaches the browser.
 */
class DeadlineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource['id'],
            'vehicleId' => $this->resource['vehicleId'],
            'type' => $this->resource['type'],
            'status' => $this->resource['status'],
            'dueDate' => $this->resource['dueDate'],
            'paidAt' => $this->resource['paidAt'] ?? null,
            'amount' => $this->resource['amount'] ?? null,
            'description' => $this->resource['description'] ?? null,
            'createdAt' => $this->resource['createdAt'] ?? null,
        ];
    }
}
