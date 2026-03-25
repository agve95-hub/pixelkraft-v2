<x-layouts.auth>
    <x-slot:title>Two-factor authentication — pixelkraft</x-slot:title>
    <x-slot:subtitle>Confirm your identity</x-slot:subtitle>

    <div x-data="{ recovery: false }">
        <div x-show="!recovery">
            <p class="text-sm text-zinc-400 mb-5">
                Enter the 6-digit code from your authenticator app.
            </p>

            <form method="POST" action="{{ url('/two-factor-challenge') }}" class="space-y-5">
                @csrf

                <div>
                    <label for="code" class="input-label">Authentication code</label>
                    <input
                        id="code"
                        type="text"
                        name="code"
                        inputmode="numeric"
                        autofocus
                        autocomplete="one-time-code"
                        class="input-field text-center tracking-[0.5em] mono text-lg"
                        placeholder="000000"
                        maxlength="6"
                    >
                </div>

                <button type="submit" class="btn-primary w-full">
                    Verify
                </button>
            </form>
        </div>

        <div x-show="recovery" x-cloak>
            <p class="text-sm text-zinc-400 mb-5">
                Enter one of your recovery codes.
            </p>

            <form method="POST" action="{{ url('/two-factor-challenge') }}" class="space-y-5">
                @csrf

                <div>
                    <label for="recovery_code" class="input-label">Recovery code</label>
                    <input
                        id="recovery_code"
                        type="text"
                        name="recovery_code"
                        autofocus
                        autocomplete="one-time-code"
                        class="input-field mono"
                    >
                </div>

                <button type="submit" class="btn-primary w-full">
                    Verify
                </button>
            </form>
        </div>

        <button
            type="button"
            class="mt-4 w-full text-center text-sm text-zinc-500 hover:text-zinc-300 transition"
            x-on:click="recovery = !recovery"
        >
            <span x-show="!recovery">Use a recovery code instead</span>
            <span x-show="recovery" x-cloak>Use authenticator code instead</span>
        </button>
    </div>
</x-layouts.auth>
