<?php

namespace App\Livewire\Settings;

use Illuminate\Contracts\View\View;
use Livewire\Component;

class ApiTokens extends Component
{
    public string $tokenName = '';

    public ?string $newToken = null;

    public function createToken(): void
    {
        $this->validate([
            'tokenName' => 'required|string|max:255',
        ]);

        $token = auth()->user()->createToken($this->tokenName);
        $this->newToken = $token->plainTextToken;
        $this->tokenName = '';
    }

    public function revokeToken(string $tokenId): void
    {
        auth()->user()->tokens()->where('id', $tokenId)->delete();
    }

    public function render(): View
    {
        $tokens = auth()->user()->tokens()->orderBy('created_at', 'desc')->get();

        return view('livewire.settings.api-tokens', [
            'tokens' => $tokens,
        ]);
    }
}
