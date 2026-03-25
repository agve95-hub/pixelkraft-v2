<div class="space-y-4">
    {{-- Show newly created token --}}
    @if ($newToken)
        <div class="rounded-lg border border-emerald-500/20 bg-emerald-500/5 px-4 py-3">
            <p class="text-sm text-emerald-400 mb-2">Token created — copy it now, it won't be shown again:</p>
            <code class="block rounded bg-zinc-800 px-3 py-2 mono text-sm text-zinc-200 break-all select-all">{{ $newToken }}</code>
        </div>
    @endif

    {{-- Create token --}}
    <form wire:submit="createToken" class="flex items-end gap-3">
        <div class="flex-1">
            <label class="input-label">Token name</label>
            <input type="text" wire:model="tokenName" class="input-field text-sm" placeholder="e.g. deploy-script">
            @error('tokenName') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
        </div>
        <button type="submit" class="btn-primary text-sm whitespace-nowrap">Create token</button>
    </form>

    {{-- Existing tokens --}}
    @if ($tokens->isNotEmpty())
        <div class="space-y-2 pt-2">
            @foreach ($tokens as $token)
                <div class="flex items-center justify-between rounded-lg border border-zinc-800 bg-zinc-800/30 px-4 py-2.5">
                    <div>
                        <p class="text-sm text-zinc-200">{{ $token->name }}</p>
                        <p class="mono text-xs text-zinc-600">Created {{ $token->created_at->diffForHumans() }}</p>
                    </div>
                    <button
                        wire:click="revokeToken('{{ $token->id }}')"
                        wire:confirm="Revoke this token? Any scripts using it will stop working."
                        class="text-xs text-red-400 hover:text-red-300 transition"
                    >
                        Revoke
                    </button>
                </div>
            @endforeach
        </div>
    @endif
</div>
