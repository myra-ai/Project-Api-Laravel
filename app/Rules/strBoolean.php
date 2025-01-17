<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class strBoolean implements Rule
{
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value): bool
    {
        return in_array($value, ['true', 'false', '1', '0', 1, 0, true, false]);
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message(): string
    {
        return 'The :attribute field must be true or false.';
    }
}
