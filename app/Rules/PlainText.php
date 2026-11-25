<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class PlainText implements ValidationRule
{
    public const PATTERN = '/^[\p{L}\p{M}0-9 .,\-\/&()\'#+%]*$/u';

    public const MESSAGE = 'Use letras, números e pontuação simples.';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (! is_string($value) || preg_match(self::PATTERN, $value) !== 1) {
            $fail(self::MESSAGE);
        }
    }

    public static function collapse(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $collapsed = preg_replace('/\s+/u', ' ', trim($value));

        return is_string($collapsed) ? $collapsed : trim($value);
    }
}
