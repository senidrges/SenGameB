<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Token pair returned after login, registration or refresh, along with the
 * authenticated account.
 */
class AuthSessionResource extends JsonResource
{
    /**
     * @param  array{access_token: string, refresh_token: string, expires_at: string, token_type: string}  $tokens
     */
    public function __construct(User $user, private array $tokens)
    {
        parent::__construct($user);
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...$this->tokens,
            ...MeResource::make($this->resource)->toArray($request),
        ];
    }
}
