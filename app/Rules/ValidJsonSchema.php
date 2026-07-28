<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Opis\JsonSchema\Validator;

class ValidJsonSchema implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value)) {
            $fail('The :attribute must be a valid JSON Schema object.');

            return;
        }

        // An empty PHP array represents an empty schema {} which matches anything.
        if ($value === []) {
            return;
        }

        try {
            $validator = new Validator;
            $validator->validate(new \stdClass, json_decode(json_encode($value)));
        } catch (\Throwable) {
            $fail('The :attribute is not a valid JSON Schema.');
        }
    }
}
