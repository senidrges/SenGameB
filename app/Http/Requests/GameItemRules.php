<?php

namespace App\Http\Requests;

/**
 * Validation of the numbered items of a game or template.
 */
class GameItemRules
{
    /**
     * @return array<string, list<string>>
     */
    public static function rules(string $presence = 'required'): array
    {
        return [
            'items' => [$presence, 'array', 'min:1', 'max:100'],
            'items.*.number' => ['required', 'integer', 'min:1', 'max:999', 'distinct'],
            'items.*.content' => ['required', 'string', 'max:2000'],
        ];
    }
}
