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
        'pixelkraft:sites:read'           => 'Read sites, pages, deploys, analytics',
        'pixelkraft:sites:sync'           => 'Trigger git sync',
        'pixelkraft:sites:deploy'         => 'Trigger deploys',
        'pixelkraft:sites:rollback'       => 'Trigger rollbacks',
        'pixelkraft:notifications:read'   => 'Read notifications',
        'pixelkraft:notifications:write'  => 'Mark notifications read',
    ];

    public function createToken(): void
    {
        $this->validate([
            'tokenName'        => 'required|string|max:255',
            'selectedAbilities' => 'required|array|min:1',
            'selectedAbilities.*' => 'string|in:' . implode(',', array_keys(self::ABILITIES)),
            'expiresInDays'    => 'nullable|integer|min:1|max:365',
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
        $tokens = auth()->user()->tokens()->orderBy('created_at', 'desc')->get();

        return view('livewire.settings.api-tokens', [
            'tokens'    => $tokens,
            'abilities' => self::ABILITIES,
        ]);
    }
}
