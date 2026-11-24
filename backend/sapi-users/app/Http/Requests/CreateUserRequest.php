<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /users (UserCreate in docs/api/sapi-users.yaml).
 * The e-mail uniqueness is checked in UserService, because a duplicate is a 409 and not a 400.
 */
class CreateUserRequest extends FormRequest
{
    use NormalizesEmail;

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'max:72'],
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
            'password.required' => 'A palavra-passe é obrigatória.',
            'password.string' => 'A palavra-passe tem de ser texto.',
            'password.min' => 'A palavra-passe tem de ter pelo menos 8 caracteres.',
            'password.max' => 'A palavra-passe não pode ter mais de 72 caracteres.',
        ];
    }
}
