<x-layouts.auth>
    <x-slot:title>Forgot password — pixelkraft</x-slot:title>
    <x-slot:subtitle>Reset your password</x-slot:subtitle>

    <p class="text-sm text-zinc-400 mb-5">
        Enter your email and we'll send you a reset link.
    </p>

    <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
        @csrf

        <div>
            <label for="email" class="input-label">Email</label>
            <input
                id="email"
                type="email"
                name="email"
                value="{{ old('email') }}"
                required
                autofocus
                autocomplete="email"
                class="input-field"
                placeholder="you@domain.com"
            >
        </div>

        <button type="submit" class="btn-primary w-full">
            Send reset link
        </button>
    </form>

    <x-slot:footer>
        <a href="{{ route('login') }}" class="text-violet-400 hover:text-violet-300">Back to sign in</a>
    </x-slot:footer>
</x-layouts.auth>
