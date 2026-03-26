<div>
    <flux:card>
        <flux:heading size="sm" class="mb-4">Alerts</flux:heading>

        <div class="space-y-2 max-h-96 overflow-y-auto">
            @foreach ($sslExpiring as $site)
                <flux:callout variant="warning" icon="exclamation-triangle" size="sm">
                    <strong>SSL expiring for {{ $site->domain }}</strong>
                    — expires {{ $site->ssl_expires_at->diffForHumans() }}
                </flux:callout>
            @endforeach

            @foreach ($alerts as $alert)
                <div class="flex items-start gap-3 rounded-lg border border-zinc-200 dark:border-zinc-700 px-3 py-2.5 {{ in_array($alert->type, ['deploy_failed', 'uptime_down']) ? 'bg-red-50 dark:bg-red-500/5 border-red-200 dark:border-red-500/20' : '' }}">
                    @switch($alert->type)
                        @case('deploy_failed')
                            <flux:icon name="exclamation-triangle" variant="solid" class="size-5 text-red-500 mt-0.5 shrink-0" />
                            @break
                        @case('uptime_down')
                            <flux:icon name="signal-slash" variant="solid" class="size-5 text-red-500 mt-0.5 shrink-0" />
                            @break
                        @default
                            <flux:icon name="information-circle" variant="solid" class="size-5 text-blue-500 mt-0.5 shrink-0" />
                    @endswitch

                    <div class="min-w-0 flex-1">
                        <flux:text size="sm" class="font-medium">{{ $alert->title }}</flux:text>
                        @if ($alert->body)
                            <flux:text size="xs">{{ Str::limit($alert->body, 80) }}</flux:text>
                        @endif
                        <flux:text size="xs" class="font-mono mt-0.5">{{ $alert->created_at->diffForHumans() }}</flux:text>
                    </div>

                    <flux:button wire:click="dismiss('{{ $alert->id }}')" size="xs" variant="ghost" icon="x-mark" />
                </div>
            @endforeach

            @if ($alerts->isEmpty() && $sslExpiring->isEmpty())
                <div class="py-8 text-center">
                    <flux:icon name="check-circle" variant="outline" class="size-8 text-lime-500 mx-auto mb-2" />
                    <flux:subheading>All clear — no alerts</flux:subheading>
                </div>
            @endif
        </div>
    </flux:card>
</div>
