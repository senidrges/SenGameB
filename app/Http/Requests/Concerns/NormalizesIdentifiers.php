<?php

namespace App\Http\Requests\Concerns;

use App\Support\PhoneNumber;

trait NormalizesIdentifiers
{
    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('phone_number'))) {
            $this->merge(['phone_number' => PhoneNumber::normalize($this->input('phone_number'))]);
        }

        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }
}
