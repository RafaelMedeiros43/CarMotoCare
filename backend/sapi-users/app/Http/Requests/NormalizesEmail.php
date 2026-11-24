<?php

namespace App\Http\Requests;

/**
 * Domain rule R11 (docs/data/domain-rules.md): e-mails are compared in lowercase.
 * Laravel's TrimStrings middleware already removes surrounding spaces.
 */
trait NormalizesEmail
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower($this->input('email'))]);
        }
    }
}
