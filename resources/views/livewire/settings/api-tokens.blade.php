<div class="space-y-4">
    @if ($newToken)
        <flux:callout variant="success" icon="check-circle">
            <p class="mb-2">Token created — copy it now, it won't be shown again:</p>
            <code class="block rounded bg-zinc-100 dark:bg-zinc-800 px-3 py-2 font-mono text-sm break-all select-all">{{ $newToken }}</code>
        </flux:callout>
    @endif

    <form wire:submit="createToken" class="flex items-end gap-3">
        <div class="flex-1">
            <flux:field>
                <flux:label>Token name</flux:label>
                <flux:input wire:model="tokenName" placeholder="e.g. deploy-script" size="sm" />
                <flux:error name="tokenName" />
            </flux:field>
        </div>
        <flux:button type="submit" variant="primary" size="sm">Create token</flux:button>
    </form>

    @if ($tokens->isNotEmpty())
        <div class="space-y-2 pt-2">
            @foreach ($tokens as $token)
                <div class="flex items-center justify-between rounded-lg border border-zinc-200 dark:border-zinc-700 px-4 py-2.5">
                    <div>
                        <flux:text size="sm" class="font-medium">{{ $token->name }}</flux:text>
                        <flux:text size="xs" class="font-mono">Created {{ $token->created_at->diffForHumans() }}</flux:text>
                    </div>
                    <flux:button wire:click="revokeToken('{{ $token->id }}')" wire:confirm="Revoke this token?" size="xs" variant="ghost" class="text-red-500">Revoke</flux:button>
                </div>
            @endforeach
        </div>
    @endif
</div>
