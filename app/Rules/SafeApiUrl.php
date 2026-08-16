<?php

namespace App\Rules;

use App\Support\SafeApiUrl as SafeApiUrlValidator;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class SafeApiUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $error = SafeApiUrlValidator::validate((string) $value);
        if ($error !== null) {
            $fail($error);
        }
    }
}
