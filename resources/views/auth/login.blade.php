<x-layouts.auth>
    <x-slot:title>Sign in - Universal Tool</x-slot:title>
    <x-slot:subtitle>Sign in to your dashboard</x-slot:subtitle>

    <form method="POST" action="{{ route('login') }}" class="space-y-6">
        @csrf

        <flux:field>
            <flux:label>Email</flux:label>
            <flux:input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email" placeholder="you@domain.com" />
            <flux:error name="email" />
        </flux:field>

        <flux:field>
            <flux:label>Password</flux:label>
            <flux:input type="password" name="password" required autocomplete="current-password" placeholder="••••••••" viewable />
            <flux:error name="password" />
        </flux:field>

        <div class="flex items-center justify-between">
            <flux:checkbox name="remember" label="Remember me" />

            @if (Route::has('password.request'))
                <flux:link href="{{ route('password.request') }}" variant="subtle" size="sm">Forgot password?</flux:link>
            @endif
        </div>

        <x-ui.button type="submit" class="w-full">Sign in</x-ui.button>
    </form>

    @if (Route::has('register'))
    <x-slot:footer>
        Don't have an account? <flux:link href="{{ route('register') }}">Create one</flux:link>
    </x-slot:footer>
    @endif
</x-layouts.auth>
