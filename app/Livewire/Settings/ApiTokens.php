<?php

namespace App\Livewire\Settings;

use Illuminate\Contracts\View\View;
use Livewire\Component;

class ApiTokens extends Component
{
    public string $tokenName = '';

    /** @var list<string> */
    public array $selectedAbilities = [];

    public string $expiresInDays = '';

    public ?string $newToken = null;

    /** All abilities the UI can grant, keyed by ability string => human label. */
    public const ABILITIES = [
        'platform:sites:read' => 'Read sites, pages, deploys, analytics',
        'platform:sites:sync' => 'Trigger git sync',
        'platform:sites:deploy' => 'Trigger deploys',
        'platform:sites:rollback' => 'Trigger rollbacks',
        'platform:notifications:read' => 'Read notifications',
        'platform:notifications:write' => 'Mark notifications read',
    ];

    public function createToken(): void
    {
        $this->validate([
            'tokenName' => 'required|string|max:255',
            'selectedAbilities' => 'required|array|min:1',
            'selectedAbilities.*' => 'string|in:'.implode(',', array_keys(self::ABILITIES)),
            'expiresInDays' => 'nullable|integer|min:1|max:365',
        ]);

        $expiry = $this->expiresInDays !== ''
            ? now()->addDays((int) $this->expiresInDays)
            : null;

        $token = auth()->user()->createToken(
            $this->tokenName,
            $this->selectedAbilities,
            $expiry,
        );

        $this->newToken = $token->plainTextToken;
        $this->tokenName = '';
        $this->selectedAbilities = [];
        $this->expiresInDays = '';
    }

    public function revokeToken(string $tokenId): void
    {
        auth()->user()->tokens()->where('id', $tokenId)->delete();
    }

    public function render(): View
    {
        $tokens = auth()->user()->tokens()->orderBy('created_at', 'desc')->get()
            ->each(function ($token) {
                // Attach computed expiry flags as dynamic properties so the view
                // can use $token->is_expired and $token->expires_soon without
                // breaking the existing $token->name / $token->abilities access.
                $token->is_expired = $token->expires_at && $token->expires_at->isPast();
                $token->expires_soon = $token->expires_at
                    && ! $token->expires_at->isPast()
                    && $token->expires_at->diffInDays(now()) <= 14;
            });

        return view('livewire.settings.api-tokens', [
            'tokens' => $tokens,
            'abilities' => self::ABILITIES,
            'defaultExpiryDays' => config('sanctum.expiration')
                ? (int) round(config('sanctum.expiration') / 1440)
                : null,
        ]);
    }
}
