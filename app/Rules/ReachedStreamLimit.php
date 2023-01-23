<?php

namespace App\Rules;

use App\Models\LiveStreams as mLiveStreams;
use Illuminate\Contracts\Validation\Rule;

class ReachedStreamLimit implements Rule
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
        if (mLiveStreams::where('company_id', '=', $value)->count() >= config('app.stream_limit_per_user')) {
            return false;
        }
        return true;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message(): string
    {
        return 'User exceeded stream limit.';
    }
}
