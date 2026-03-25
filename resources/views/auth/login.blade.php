<x-layouts.auth>
    <x-slot:title>Sign in — pixelkraft</x-slot:title>
    <x-slot:subtitle>Sign in to your dashboard</x-slot:subtitle>

    <form method="POST" action="{{ route('login') }}" class="space-y-5">
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

        <div>
            <label for="password" class="input-label">Password</label>
            <input
                id="password"
                type="password"
                name="password"
                required
                autocomplete="current-password"
                class="input-field"
                placeholder="••••••••"
            >
        </div>

        <div class="flex items-center justify-between">
            <label class="flex items-center gap-2 text-sm text-zinc-400 cursor-pointer">
                <input type="checkbox" name="remember" class="rounded border-zinc-600 bg-zinc-800 text-violet-600 focus:ring-violet-500 focus:ring-offset-zinc-950">
                Remember me
            </label>

            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}" class="text-sm text-violet-400 hover:text-violet-300">
                    Forgot password?
                </a>
            @endif
        </div>

        <button type="submit" class="btn-primary w-full">
            Sign in
        </button>
    </form>

    <x-slot:footer>
        Don't have an account?
        <a href="{{ route('register') }}" class="text-violet-400 hover:text-violet-300 ml-1">Create one</a>
    </x-slot:footer>
</x-layouts.auth>
