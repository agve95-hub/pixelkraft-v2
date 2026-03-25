<x-layouts.auth>
    <x-slot:title>Reset password — pixelkraft</x-slot:title>
    <x-slot:subtitle>Set your new password</x-slot:subtitle>

    <form method="POST" action="{{ route('password.update') }}" class="space-y-5">
        @csrf

        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <div>
            <label for="email" class="input-label">Email</label>
            <input
                id="email"
                type="email"
                name="email"
                value="{{ old('email', $request->email) }}"
                required
                autofocus
                autocomplete="email"
                class="input-field"
            >
        </div>

        <div>
            <label for="password" class="input-label">New password</label>
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
            Reset password
        </button>
    </form>
</x-layouts.auth>
