<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * PATCH /users/{userId} (UserUpdate in docs/api/sapi-users.yaml).
 * The rule "currentPassword is needed to change the e-mail or password" is checked in UserService,
 * because an e-mail equal to the current one is not a change.
 */
class UpdateUserRequest extends FormRequest
{
    use NormalizesEmail;

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'email' => ['sometimes', 'required', 'string', 'email', 'max:255'],
            'newPassword' => ['sometimes', 'required', 'string', 'min:8', 'max:72'],
            'currentPassword' => ['sometimes', 'nullable', 'string'],
        ];
    }

    public function after(): array
    {
        return [
            // minProperties: 1 in the contract (currentPassword alone changes nothing).
            function (Validator $validator): void {
                if ($validator->errors()->isEmpty() && ! $this->hasAny(['name', 'email', 'newPassword'])) {
                    $validator->errors()->add('name', 'Indique pelo menos um campo para alterar.');
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'O nome é obrigatório.',
            'name.string' => 'O nome tem de ser texto.',
            'name.max' => 'O nome não pode ter mais de 100 caracteres.',
            'email.required' => 'O e-mail é obrigatório.',
            'email.string' => 'O e-mail tem de ser texto.',
            'email.email' => 'O e-mail não é válido.',
            'email.max' => 'O e-mail não pode ter mais de 255 caracteres.',
            'newPassword.required' => 'A nova palavra-passe é obrigatória.',
            'newPassword.string' => 'A nova palavra-passe tem de ser texto.',
            'newPassword.min' => 'A nova palavra-passe tem de ter pelo menos 8 caracteres.',
            'newPassword.max' => 'A nova palavra-passe não pode ter mais de 72 caracteres.',
            'currentPassword.string' => 'A palavra-passe atual tem de ser texto.',
        ];
    }
}
