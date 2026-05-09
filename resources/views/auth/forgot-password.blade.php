<x-layouts.auth>
    <x-slot:title>Forgot password - Universal Tool</x-slot:title>
    <x-slot:subtitle>Reset your password</x-slot:subtitle>

    <flux:subheading class="mb-4">Enter your email and we'll send you a reset link.</flux:subheading>

    <form method="POST" action="{{ route('password.email') }}" class="space-y-6">
        @csrf

        <flux:field>
            <flux:label>Email</flux:label>
            <flux:input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email" placeholder="you@domain.com" />
            <flux:error name="email" />
        </flux:field>

        <flux:button type="submit" variant="primary" class="w-full">Send reset link</flux:button>
    </form>

    <x-slot:footer>
        <flux:link href="{{ route('login') }}">Back to sign in</flux:link>
    </x-slot:footer>
</x-layouts.auth>
