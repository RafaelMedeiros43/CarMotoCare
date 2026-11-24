<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * User schema in docs/api/eapi-web.yaml, built from the sapi-users response (an array).
 * Only the contract fields are returned, so nothing extra from the SAPI reaches the browser.
 */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource['id'],
            'name' => $this->resource['name'],
            'email' => $this->resource['email'],
            'createdAt' => $this->resource['createdAt'] ?? null,
        ];
    }
}
