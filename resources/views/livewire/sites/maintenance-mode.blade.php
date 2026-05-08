<div class="space-y-6">
    <x-ui.alert variant="warning" icon="exclamation-triangle" title="Preview only">
        These settings are saved but not yet served to live traffic. Enabling the toggle does not currently block public access.
    </x-ui.alert>

    <div class="pk-page-head">
        <div>
            <x-ui.card-title>Maintenance mode</x-ui.card-title>
            <p class="pk-page-sub mt-1">Design your maintenance page. Enforcement will be wired in a future release.</p>
        </div>
        <x-ui.button-group>
            <flux:button type="submit" form="maintenance-form" variant="primary" icon="check">Save</flux:button>
            <x-ui.button href="{{ route('sites.maintenance.preview', $this->siteId) }}" target="_blank" rel="noopener noreferrer" variant="outline" icon="arrow-top-right-on-square">Preview</x-ui.button>
        </x-ui.button-group>
    </div>

    <form id="maintenance-form" wire:submit="save" class="grid gap-6 lg:grid-cols-2">
        <x-ui.card>
            <x-ui.card-content>
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <p class="text-sm font-medium">Enable maintenance page</p>
                        <p class="text-xs text-zinc-500">When on, visitors see the maintenance screen.</p>
                    </div>
                    <flux:switch wire:model.live="enabled" />
                </div>

                <x-ui.separator />

                <div class="flex items-center justify-between gap-4">
                    <div>
                        <p class="text-sm font-medium">Auto-enable when site is down</p>
                        <p class="text-xs text-zinc-500">Uses uptime checks when available.</p>
                    </div>
                    <flux:switch wire:model.live="autoOnDown" />
                </div>

                <flux:input wire:model="heading" label="Heading" />
                <flux:textarea wire:model="message" label="Message" rows="4" />

                <div class="flex items-center justify-between gap-4">
                    <flux:checkbox wire:model.live="showCountdown" label="Show countdown" />
                </div>
                @if ($showCountdown)
                    <flux:input wire:model="countdownTo" type="datetime-local" label="Countdown target" />
                @endif

                <div class="grid gap-4 sm:grid-cols-3">
                    <flux:field>
                        <flux:label>Background</flux:label>
                        <flux:input wire:model.live="bgColor" type="color" />
                    </flux:field>
                    <flux:field>
                        <flux:label>Text</flux:label>
                        <flux:input wire:model.live="textColor" type="color" />
                    </flux:field>
                    <flux:field>
                        <flux:label>Accent</flux:label>
                        <flux:input wire:model.live="accentColor" type="color" />
                    </flux:field>
                </div>

                <flux:checkbox wire:model.live="showLogo" label="Show logo" />
                <flux:textarea wire:model="customCss" label="Custom CSS" rows="3" class="font-mono text-xs" />
                <flux:textarea wire:model="allowedIps" label="Allowed IPs (one per line)" rows="3" class="font-mono text-xs" />
            </x-ui.card-content>
        </x-ui.card>

        <div class="space-y-3">
            <p class="text-xs font-medium uppercase tracking-[0.12em] text-zinc-500">Live preview</p>
            <div class="overflow-hidden rounded-xl border border-zinc-700/80 shadow-lg"
                 style="background: {{ $bgColor }}; color: {{ $textColor }};">
                <div class="flex min-h-[280px] flex-col items-center justify-center gap-4 px-8 py-12 text-center">
                    @if ($showLogo)
                        <div class="flex size-10 items-center justify-center rounded-lg text-sm font-bold text-black"
                             style="background: linear-gradient(135deg, {{ $accentColor }}, #06b6d4);">P</div>
                    @endif
                    <h2 class="text-xl font-semibold" style="color: {{ $textColor }};">{{ $heading }}</h2>
                    <p class="max-w-md text-sm leading-relaxed opacity-80">{{ $message ?: '—' }}</p>
                    @if ($showCountdown && filled($countdownTo))
                        <p class="font-mono text-xs opacity-60">Ends: {{ $countdownTo }}</p>
                    @endif
                </div>
            </div>
        </div>
    </form>
</div>
