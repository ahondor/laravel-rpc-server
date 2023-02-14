<?php

declare(strict_types=1);

namespace Sajya\Server\Rules;

use Illuminate\Contracts\Validation\Rule;

class Identifier implements Rule
{
    /**
     * Determine if the validation rule passes.
     *
     * @param string $attribute
     */
    public function passes($attribute, $value): bool
    {
        return is_numeric($value) || is_string($value) || $value === null;
    }

    /**
     * Get the validation error message.
     */
    public function message(): string
    {
        return 'The :attribute must be a string or integer.';
    }
}
