<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class Phone implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value) || !preg_match('/^(\+|00)?[0-9]{8,15}$/', preg_replace('/[\s.\-()]/', '', $value))) {
            $fail('Indiquez un numéro de téléphone valide.');
        }
    }
}
