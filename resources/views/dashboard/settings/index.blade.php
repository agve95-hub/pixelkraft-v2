<x-layouts.app>
    <x-slot:title>Settings</x-slot:title>

    <div class="max-w-2xl space-y-8">
        <flux:heading size="xl">Settings</flux:heading>

        {{-- Profile --}}
        <flux:card>
            <flux:heading size="sm" class="mb-4">Profile</flux:heading>
            <form method="POST" action="{{ route('user-profile-information.update') }}" class="space-y-4">
                @csrf @method('PUT')

                <flux:field>
                    <flux:label>Name</flux:label>
                    <flux:input name="name" value="{{ auth()->user()->name }}" required />
                </flux:field>

                <flux:field>
                    <flux:label>Email</flux:label>
                    <flux:input type="email" name="email" value="{{ auth()->user()->email }}" required />
                </flux:field>

                <flux:button type="submit" variant="primary" size="sm">Save profile</flux:button>
            </form>
        </flux:card>

        {{-- Change Password --}}
        <flux:card>
            <flux:heading size="sm" class="mb-4">Change Password</flux:heading>
            <form method="POST" action="{{ route('user-password.update') }}" class="space-y-4">
                @csrf @method('PUT')

                <flux:field>
                    <flux:label>Current password</flux:label>
                    <flux:input type="password" name="current_password" required viewable />
                </flux:field>

                <flux:field>
                    <flux:label>New password</flux:label>
                    <flux:input type="password" name="password" required viewable />
                </flux:field>

                <flux:field>
                    <flux:label>Confirm new password</flux:label>
                    <flux:input type="password" name="password_confirmation" required viewable />
                </flux:field>

                <flux:button type="submit" variant="primary" size="sm">Update password</flux:button>
            </form>
        </flux:card>

        {{-- Two-Factor --}}
        <flux:card>
            <flux:heading size="sm" class="mb-4">Two-Factor Authentication</flux:heading>
            @if (auth()->user()->two_factor_secret)
                <div class="flex items-center gap-3 mb-4">
                    <flux:badge color="lime">Enabled</flux:badge>
                    <flux:text size="sm">2FA is active on your account.</flux:text>
                </div>
                <form method="POST" action="{{ url('/user/two-factor-authentication') }}">
                    @csrf @method('DELETE')
                    <flux:button type="submit" variant="danger" size="sm">Disable 2FA</flux:button>
                </form>
            @else
                <flux:subheading class="mb-4">Add an extra layer of security to your account.</flux:subheading>
                <form method="POST" action="{{ url('/user/two-factor-authentication') }}">
                    @csrf
                    <flux:button type="submit" variant="primary" size="sm">Enable 2FA</flux:button>
                </form>
            @endif
        </flux:card>

        {{-- Discord --}}
        <flux:card>
            <flux:heading size="sm" class="mb-4">Discord Notifications</flux:heading>
            @livewire('settings.discord-webhook')
        </flux:card>

        {{-- API Tokens --}}
        <flux:card>
            <flux:heading size="sm" class="mb-4">API Tokens</flux:heading>
            <flux:subheading class="mb-4">Generate tokens to access the pixelkraft API.</flux:subheading>
            @livewire('settings.api-tokens')
        </flux:card>
    </div>
</x-layouts.app>
