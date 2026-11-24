<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Token schema in docs/api/eapi-web.yaml, built from the array returned by AccountService::login().
 */
class TokenResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'accessToken' => $this->resource['token'],
            'tokenType' => 'Bearer',
            'expiresIn' => $this->resource['expiresIn'],
        ];
    }
}
