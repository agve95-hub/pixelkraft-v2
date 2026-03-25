<x-layouts.auth>
    <x-slot:title>Create account — pixelkraft</x-slot:title>
    <x-slot:subtitle>Create your account</x-slot:subtitle>

    <form method="POST" action="{{ route('register') }}" class="space-y-5">
        @csrf

        <div>
            <label for="name" class="input-label">Name</label>
            <input
                id="name"
                type="text"
                name="name"
                value="{{ old('name') }}"
                required
                autofocus
                autocomplete="name"
                class="input-field"
                placeholder="Your name"
            >
        </div>

        <div>
            <label for="email" class="input-label">Email</label>
            <input
                id="email"
                type="email"
                name="email"
                value="{{ old('email') }}"
                required
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
                autocomplete="new-password"
                class="input-field"
                placeholder="••••••••"
            >
        </div>

        <div>
            <label for="password_confirmation" class="input-label">Confirm password</label>
            <input
                id="password_confirmation"
                type="password"
                name="password_confirmation"
                required
                autocomplete="new-password"
                class="input-field"
                placeholder="••••••••"
            >
        </div>

        <button type="submit" class="btn-primary w-full">
            Create account
        </button>
    </form>

    <x-slot:footer>
        Already have an account?
        <a href="{{ route('login') }}" class="text-violet-400 hover:text-violet-300 ml-1">Sign in</a>
    </x-slot:footer>
</x-layouts.auth>
