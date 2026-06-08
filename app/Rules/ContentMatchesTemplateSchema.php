<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\ValidatorAwareRule;
use Illuminate\Validation\Validator;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator as JsonSchemaValidator;

class ContentMatchesTemplateSchema implements ValidationRule, ValidatorAwareRule
{
    private Validator $validator;

    public function __construct(private readonly array $jsonSchema) {}

    public function setValidator(Validator $validator): static
    {
        $this->validator = $validator;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (empty($this->jsonSchema)) {
            return;
        }

        $jsonSchemaValidator = new JsonSchemaValidator;
        $schema = json_decode(json_encode($this->jsonSchema));
        $data = json_decode(json_encode($value));

        try {
            $result = $jsonSchemaValidator->validate($data, $schema);
        } catch (\Throwable) {
            $fail('The :attribute could not be validated because the form template has an invalid schema.');

            return;
        }

        if ($result->isValid()) {
            return;
        }

        $formatter = new ErrorFormatter;
        $errors = $formatter->format($result->error(), true);
        $hasSubFieldErrors = false;

        foreach ($errors as $pointer => $messages) {
            $isRoot = $pointer === '/' || $pointer === '';
            $path = $isRoot ? $attribute : $attribute.'.'.ltrim(str_replace('/', '.', $pointer), '.');

            foreach ($messages as $message) {
                if ($isRoot) {
                    $fail("The {$path} field failed schema validation: {$message}");
                } else {
                    $this->validator->errors()->add($path, "The {$path} field failed schema validation: {$message}");
                    $hasSubFieldErrors = true;
                }
            }
        }

        // Ensure the parent attribute is also marked invalid when only sub-field errors exist.
        if ($hasSubFieldErrors) {
            $fail('The :attribute has schema validation errors.');
        }
    }
}
