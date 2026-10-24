<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\Concerns\NormalizesIdentifiers;
use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
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
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', Password::min(6)],
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'phone_number' => ['nullable', 'string', 'max:32', 'regex:'.PhoneNumber::VALIDATION_REGEX, 'unique:users,phone_number'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.unique' => 'Cet email est déjà utilisé. Veuillez vous connecter.',
            'phone_number.unique' => 'Ce numéro de téléphone est déjà associé à un compte.',
            'phone_number.regex' => PhoneNumber::invalidMessage(),
        ];
    }
}
