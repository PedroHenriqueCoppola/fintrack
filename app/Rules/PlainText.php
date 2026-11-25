<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class PlainText implements ValidationRule
{
    /**
     * Letters, numbers, space and simple punctuation, including : ; ! ? " @ $ *.
     */
    private const CHARACTERS = '\p{L}\p{M}0-9 .,\-\/&()\'#+%:;!?"@$*';

    public const MESSAGE = 'Use letras, números e pontuação simples.';

    public function __construct(private readonly bool $multiline = false) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $extra = $this->multiline ? '\n' : '';

        if (! is_string($value) || preg_match('/^['.self::CHARACTERS.$extra.']*$/u', $value) !== 1) {
            $fail(self::MESSAGE);
        }
    }

    public static function collapse(mixed $value, bool $multiline = false): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        if (! $multiline) {
            $collapsed = preg_replace('/\s+/u', ' ', trim($value));

            return is_string($collapsed) ? $collapsed : trim($value);
        }

        $normalized = str_replace(["\r\n", "\r"], "\n", $value);
        $cleaned = [];
        $blank = false;

        foreach (explode("\n", $normalized) as $line) {
            $collapsed = preg_replace('/[^\S\n]+/u', ' ', trim($line));
            $line = is_string($collapsed) ? $collapsed : trim($line);

            if ($line === '') {
                if ($blank) {
                    continue;
                }

                $blank = true;
                $cleaned[] = '';

                continue;
            }

            $blank = false;
            $cleaned[] = $line;
        }

        return implode("\n", $cleaned);
    }
}
