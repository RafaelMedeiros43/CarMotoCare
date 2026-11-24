<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * AuthResult schema in docs/api/sapi-users.yaml. The EAPI uses it to issue the JWT.
 *
 * @mixin User
 */
class AuthResultResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'userId' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
        ];
    }
}
