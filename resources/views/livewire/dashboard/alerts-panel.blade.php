<div wire:poll.60s>
    <x-ui.card>
        <x-ui.card-header>
            <x-ui.card-title>
                <flux:icon name="exclamation-triangle" class="size-4 text-amber-400" />
                Action needed
            </x-ui.card-title>
            @php $alertTotal = $alerts->count() + $sslExpiring->count(); @endphp
            @if ($alertTotal > 0)
                <x-ui.badge variant="warning">{{ $alertTotal }}</x-ui.badge>
            @endif
        </x-ui.card-header>

        <div class="max-h-72 overflow-y-auto">
            @foreach ($sslExpiring as $site)
                <div class="issue-item">
                    <span class="issue-icon issue-icon-yellow"><flux:icon name="shield-exclamation" /></span>
                    <div class="min-w-0 flex-1">
                        <p class="issue-text">SSL pending on {{ $site->name }}</p>
                        <p class="issue-meta">Certificate not yet provisioned</p>
                    </div>
                </div>
            @endforeach

            @foreach ($alerts as $alert)
                <div class="issue-item">
                    <span @class([
                        'issue-icon',
                        'issue-icon-red' => in_array($alert->type, ['deploy_failed', 'uptime_down']),
                        'issue-icon-yellow' => $alert->type === 'ssl_expiring',
                        'issue-icon-blue' => !in_array($alert->type, ['deploy_failed', 'uptime_down', 'ssl_expiring']),
                    ])>
                        @switch($alert->type)
                            @case('deploy_failed') <flux:icon name="x-circle" /> @break
                            @case('uptime_down') <flux:icon name="signal-slash" /> @break
                            @default <flux:icon name="information-circle" />
                        @endswitch
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="issue-text">{{ $alert->title }}</p>
                        <p class="issue-meta">
                            {{ $alert->site?->name ?? '' }}
                            @if ($alert->body) &middot; {{ Str::limit($alert->body, 50) }} @endif
                            &middot; {{ $alert->created_at->diffForHumans() }}
                        </p>
                    </div>
                    <flux:button wire:click="dismiss('{{ $alert->id }}')" size="xs" variant="ghost" icon="x-mark" class="shrink-0" />
                </div>
            @endforeach

            @if ($alerts->isEmpty() && $sslExpiring->isEmpty())
                <x-ui.empty icon="check-circle" title="All clear" description="No action needed." />
            @endif
        </div>
    </x-ui.card>
</div>
