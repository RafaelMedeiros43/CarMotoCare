<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /authentications (Credentials in docs/api/sapi-users.yaml).
 */
class VerifyCredentialsRequest extends FormRequest
{
    use NormalizesEmail;

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'O e-mail é obrigatório.',
            'email.string' => 'O e-mail tem de ser texto.',
            'email.email' => 'O e-mail não é válido.',
            'email.max' => 'O e-mail não pode ter mais de 255 caracteres.',
            'password.required' => 'A palavra-passe é obrigatória.',
            'password.string' => 'A palavra-passe tem de ser texto.',
        ];
    }
}
