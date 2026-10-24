<?php

namespace App\Http\Requests;

use App\Enums\ReportableType;
use App\Enums\ReportReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReportRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(ReportableType::class)],
            'id' => ['required', 'uuid'],
            'reason' => ['required', Rule::enum(ReportReason::class)],
            'details' => ['nullable', 'string', 'max:1000', 'required_if:reason,other'],
            // Block the author at the same time, as social networks offer.
            'also_block' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'details.required_if' => 'Décrivez le problème en quelques mots.',
        ];
    }
}
