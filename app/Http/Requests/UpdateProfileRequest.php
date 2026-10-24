<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizesIdentifiers;
use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    use NormalizesIdentifiers;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'username' => ['sometimes', 'string', 'min:3', 'max:30', 'regex:/^[a-zA-Z0-9_]+$/', Rule::unique('users')->ignore($this->user())],
            'first_name' => ['sometimes', 'string', 'max:100'],
            'last_name' => ['sometimes', 'string', 'max:100'],
            'phone_number' => ['sometimes', 'string', 'max:32', 'regex:'.PhoneNumber::VALIDATION_REGEX, Rule::unique('users')->ignore($this->user())],
            'status' => ['sometimes', 'string', 'max:255'],
            'biometrics_enabled' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'username.unique' => "Ce nom d'utilisateur est déjà pris.",
            'phone_number.unique' => 'Ce numéro de téléphone est déjà associé à un compte.',
            'phone_number.regex' => PhoneNumber::invalidMessage(),
        ];
    }
}
