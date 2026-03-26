<x-layouts.auth>
    <x-slot:title>Two-factor authentication — pixelkraft</x-slot:title>
    <x-slot:subtitle>Confirm your identity</x-slot:subtitle>

    <div x-data="{ recovery: false }">
        <div x-show="!recovery">
            <flux:subheading class="mb-4">Enter the 6-digit code from your authenticator app.</flux:subheading>

            <form method="POST" action="{{ url('/two-factor-challenge') }}" class="space-y-6">
                @csrf

                <flux:field>
                    <flux:label>Authentication code</flux:label>
                    <flux:input name="code" inputmode="numeric" autofocus autocomplete="one-time-code" placeholder="000000" maxlength="6" class="text-center tracking-widest text-lg font-mono" />
                    <flux:error name="code" />
                </flux:field>

                <flux:button type="submit" variant="primary" class="w-full">Verify</flux:button>
            </form>
        </div>

        <div x-show="recovery" x-cloak>
            <flux:subheading class="mb-4">Enter one of your recovery codes.</flux:subheading>

            <form method="POST" action="{{ url('/two-factor-challenge') }}" class="space-y-6">
                @csrf

                <flux:field>
                    <flux:label>Recovery code</flux:label>
                    <flux:input name="recovery_code" autofocus autocomplete="one-time-code" class="font-mono" />
                    <flux:error name="recovery_code" />
                </flux:field>

                <flux:button type="submit" variant="primary" class="w-full">Verify</flux:button>
            </form>
        </div>

        <div class="mt-4 text-center">
            <flux:button variant="ghost" size="sm" x-on:click="recovery = !recovery">
                <span x-show="!recovery">Use a recovery code instead</span>
                <span x-show="recovery" x-cloak>Use authenticator code instead</span>
            </flux:button>
        </div>
    </div>
</x-layouts.auth>
