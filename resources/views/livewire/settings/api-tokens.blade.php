<div class="space-y-4">
    @if ($newToken)
        <flux:callout variant="success" icon="check-circle">
            <p class="mb-2">Token created — copy it now, it won't be shown again:</p>
            <code class="block rounded bg-zinc-100 dark:bg-zinc-800 px-3 py-2 font-mono text-sm break-all select-all">{{ $newToken }}</code>
        </flux:callout>
    @endif

    <form wire:submit="createToken" class="space-y-4">
        <div class="grid gap-4 sm:grid-cols-2">
            <flux:field>
                <flux:label>Token name</flux:label>
                <flux:input wire:model="tokenName" placeholder="e.g. deploy-script" size="sm" />
                <flux:error name="tokenName" />
            </flux:field>

            <flux:field>
                <flux:label>Expires in (days)</flux:label>
                <flux:input wire:model="expiresInDays" type="number" min="1" max="365" placeholder="Never" size="sm" />
                <flux:description>Leave blank for a non-expiring token.</flux:description>
                <flux:error name="expiresInDays" />
            </flux:field>
        </div>

        <flux:field>
            <flux:label>Permissions <span class="text-red-400">*</span></flux:label>
            <div class="mt-1.5 grid gap-2 sm:grid-cols-2">
                @foreach ($abilities as $ability => $label)
                    <label class="flex items-start gap-2.5 rounded-lg border border-zinc-200 dark:border-zinc-700 px-3 py-2 cursor-pointer hover:bg-zinc-50 dark:hover:bg-zinc-800/60">
                        <input
                            type="checkbox"
                            wire:model="selectedAbilities"
                            value="{{ $ability }}"
                            class="mt-0.5 shrink-0 accent-violet-500"
                        >
                        <div class="min-w-0">
                            <p class="text-xs font-medium leading-snug text-zinc-200">{{ $label }}</p>
                            <p class="truncate text-[10px] font-mono text-zinc-500">{{ $ability }}</p>
                        </div>
                    </label>
                @endforeach
            </div>
            <flux:error name="selectedAbilities" />
        </flux:field>

        <flux:button type="submit" variant="primary" size="sm">Create token</flux:button>
    </form>

    @if ($tokens->isNotEmpty())
        <div class="space-y-2 pt-2">
            @foreach ($tokens as $token)
                <div class="flex items-center justify-between rounded-lg border border-zinc-200 dark:border-zinc-700 px-4 py-2.5">
                    <div>
                        <flux:text size="sm" class="font-medium">{{ $token->name }}</flux:text>
                        <div class="flex flex-wrap gap-1 mt-1">
                            @foreach ($token->abilities as $ability)
                                <span class="rounded px-1.5 py-0.5 text-[10px] font-mono bg-zinc-800 text-zinc-400">{{ $ability }}</span>
                            @endforeach
                        </div>
                        <flux:text size="xs" class="font-mono mt-1">
                            Created {{ $token->created_at->diffForHumans() }}
                            @if ($token->expires_at)
                                · Expires {{ $token->expires_at->diffForHumans() }}
                            @endif
                        </flux:text>
                    </div>
                    <flux:button wire:click="revokeToken('{{ $token->id }}')" wire:confirm="Revoke this token?" size="xs" variant="ghost" class="text-red-500">Revoke</flux:button>
                </div>
            @endforeach
        </div>
    @endif
</div>
