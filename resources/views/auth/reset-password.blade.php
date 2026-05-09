<x-layouts.auth>
    <x-slot:title>Reset password - Universal Tool</x-slot:title>
    <x-slot:subtitle>Set your new password</x-slot:subtitle>

    <form method="POST" action="{{ route('password.update') }}" class="space-y-6">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <flux:field>
            <flux:label>Email</flux:label>
            <flux:input type="email" name="email" value="{{ old('email', $request->email) }}" required autofocus autocomplete="email" />
            <flux:error name="email" />
        </flux:field>

        <flux:field>
            <flux:label>New password</flux:label>
            <flux:input type="password" name="password" required autocomplete="new-password" placeholder="••••••••" viewable />
            <flux:error name="password" />
        </flux:field>

        <flux:field>
            <flux:label>Confirm password</flux:label>
            <flux:input type="password" name="password_confirmation" required autocomplete="new-password" placeholder="••••••••" viewable />
        </flux:field>

        <flux:button type="submit" variant="primary" class="w-full">Reset password</flux:button>
    </form>
</x-layouts.auth>
