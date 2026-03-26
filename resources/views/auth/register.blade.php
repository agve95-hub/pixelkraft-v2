<x-layouts.auth>
    <x-slot:title>Create account — pixelkraft</x-slot:title>
    <x-slot:subtitle>Create your account</x-slot:subtitle>

    <form method="POST" action="{{ route('register') }}" class="space-y-6">
        @csrf

        <flux:field>
            <flux:label>Name</flux:label>
            <flux:input name="name" value="{{ old('name') }}" required autofocus autocomplete="name" placeholder="Your name" />
            <flux:error name="name" />
        </flux:field>

        <flux:field>
            <flux:label>Email</flux:label>
            <flux:input type="email" name="email" value="{{ old('email') }}" required autocomplete="email" placeholder="you@domain.com" />
            <flux:error name="email" />
        </flux:field>

        <flux:field>
            <flux:label>Password</flux:label>
            <flux:input type="password" name="password" required autocomplete="new-password" placeholder="••••••••" viewable />
            <flux:error name="password" />
        </flux:field>

        <flux:field>
            <flux:label>Confirm password</flux:label>
            <flux:input type="password" name="password_confirmation" required autocomplete="new-password" placeholder="••••••••" viewable />
        </flux:field>

        <flux:button type="submit" variant="primary" class="w-full">Create account</flux:button>
    </form>

    <x-slot:footer>
        Already have an account? <flux:link href="{{ route('login') }}">Sign in</flux:link>
    </x-slot:footer>
</x-layouts.auth>
