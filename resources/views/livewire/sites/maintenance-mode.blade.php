<div class="space-y-6 text-zinc-100">
    <div class="rounded-lg border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm text-amber-300">
        <span class="font-semibold">Preview only.</span> These settings are saved but not yet served to live traffic. The maintenance page shown here is a design preview — enabling the toggle does not currently block public access to your site.
    </div>

    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <flux:heading size="lg">Maintenance mode</flux:heading>
            <flux:text class="mt-1 !text-zinc-500">Design your maintenance page. Enforcement will be wired in a future release.</flux:text>
        </div>
        <div class="flex flex-wrap gap-2">
            <flux:button type="submit" form="maintenance-form" variant="primary" icon="check" class="!bg-emerald-500 hover:!bg-emerald-400 !text-zinc-950">
                Save
            </flux:button>
            <flux:button href="{{ route('sites.maintenance.preview', $this->siteId) }}" target="_blank" variant="subtle" icon="arrow-top-right-on-square">
                Open preview
            </flux:button>
        </div>
    </div>

    <form id="maintenance-form" wire:submit="save" class="grid gap-6 lg:grid-cols-2">
        <div class="space-y-5 rounded-xl border border-zinc-800/80 bg-[#1e1e1e] p-5">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <p class="text-sm font-medium text-zinc-200">Enable maintenance page</p>
                    <p class="text-xs text-zinc-500">When on, visitors see the maintenance screen.</p>
                </div>
                <flux:switch wire:model.live="enabled" />
            </div>

            <flux:separator variant="subtle" />

            <div class="flex items-center justify-between gap-4">
                <div>
                    <p class="text-sm font-medium text-zinc-200">Auto-enable when site is down</p>
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
                <flux:input wire:model.live="bgColor" type="color" label="Background" />
                <flux:input wire:model.live="textColor" type="color" label="Text" />
                <flux:input wire:model.live="accentColor" type="color" label="Accent" />
            </div>

            <flux:checkbox wire:model.live="showLogo" label="Show logo" />

            <flux:textarea wire:model="customCss" label="Custom CSS" rows="3" class="font-mono text-xs" />
            <flux:textarea wire:model="allowedIps" label="Allowed IPs (one per line)" rows="3" class="font-mono text-xs" />
        </div>

        <div class="space-y-3">
            <p class="text-xs font-medium uppercase tracking-[0.12em] text-zinc-500">Live preview</p>
            <div
                class="overflow-hidden rounded-xl border border-zinc-700/80 shadow-lg"
                style="background: {{ $bgColor }}; color: {{ $textColor }};"
            >
                <div class="flex min-h-[280px] flex-col items-center justify-center gap-4 px-8 py-12 text-center">
                    @if ($showLogo)
                        <div class="flex size-10 items-center justify-center rounded-lg text-sm font-bold text-black" style="background: linear-gradient(135deg, {{ $accentColor }}, #06b6d4);">P</div>
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
