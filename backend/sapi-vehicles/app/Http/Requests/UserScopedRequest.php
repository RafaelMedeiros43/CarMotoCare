<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

/**
 * Base for requests that carry the X-User-Id header sent by the EAPI (docs/api/sapi-vehicles.yaml,
 * parameters UserIdRequired and UserIdOptional). The SAPI trusts it: it only checks that it is a UUID.
 */
abstract class UserScopedRequest extends FormRequest
{
    /** True when the endpoint cannot work without the header (list and create). */
    protected function userIdRequired(): bool
    {
        return false;
    }

    public function rules(): array
    {
        return [];
    }

    public function userId(): ?string
    {
        return $this->header('X-User-Id') ?: null;
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $userId = $this->userId();

            if ($userId === null && $this->userIdRequired()) {
                $validator->errors()->add('X-User-Id', 'O cabeçalho X-User-Id é obrigatório.');
            } elseif ($userId !== null && ! Str::isUuid($userId)) {
                $validator->errors()->add('X-User-Id', 'O cabeçalho X-User-Id tem de ser um UUID.');
            }
        }];
    }
}
