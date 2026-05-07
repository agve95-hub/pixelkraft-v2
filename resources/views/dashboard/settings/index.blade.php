<x-layouts.app>
    <x-slot:title>Settings</x-slot:title>

    <div class="max-w-4xl space-y-6">
        <div class="pk-page-head">
            <div>
                <h1 class="pk-page-title">Settings</h1>
                <p class="pk-page-sub">Account, security, notifications, API tokens, and system tools.</p>
            </div>
            <x-ui.button-group align="end">
                <x-ui.button href="{{ route('system.ui') }}" variant="outline" size="sm" icon="swatch">UI system</x-ui.button>
                <x-ui.button href="{{ route('system.diagnostics') }}" variant="outline" size="sm" icon="server-stack">Diagnostics</x-ui.button>
            </x-ui.button-group>
        </div>

        <div class="grid gap-5 xl:grid-cols-2">
            <x-ui.card>
                <x-ui.card-header>
                    <div>
                        <x-ui.card-title>Profile</x-ui.card-title>
                        <x-ui.card-description>Keep the account name and email address current.</x-ui.card-description>
                    </div>
                </x-ui.card-header>
                <form method="POST" action="{{ route('user-profile-information.update') }}" class="pk-form-grid">
                    @csrf
                    @method('PUT')

                    <flux:field>
                        <flux:label>Name</flux:label>
                        <flux:input name="name" value="{{ auth()->user()->name }}" required />
                    </flux:field>

                    <flux:field>
                        <flux:label>Email</flux:label>
                        <flux:input type="email" name="email" value="{{ auth()->user()->email }}" required />
                    </flux:field>

                    <div class="pk-action-row">
                        <x-ui.button type="submit" size="sm">Save profile</x-ui.button>
                    </div>
                </form>
            </x-ui.card>

            <x-ui.card>
                <x-ui.card-header>
                    <div>
                        <x-ui.card-title>Change password</x-ui.card-title>
                        <x-ui.card-description>Use a strong password unique to Pixelkraft.</x-ui.card-description>
                    </div>
                </x-ui.card-header>
                <form method="POST" action="{{ route('user-password.update') }}" class="pk-form-grid">
                    @csrf
                    @method('PUT')

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

                    <div class="pk-action-row">
                        <x-ui.button type="submit" size="sm">Update password</x-ui.button>
                    </div>
                </form>
            </x-ui.card>
        </div>

        <div class="grid gap-5 xl:grid-cols-2">
            <x-ui.card>
                <x-ui.card-header>
                    <div>
                        <x-ui.card-title>Two-factor authentication</x-ui.card-title>
                        <x-ui.card-description>Add an extra layer of security to your account.</x-ui.card-description>
                    </div>
                    @if (auth()->user()->two_factor_secret)
                        <x-ui.badge variant="success" dot>Enabled</x-ui.badge>
                    @else
                        <x-ui.badge variant="warning" dot>Off</x-ui.badge>
                    @endif
                </x-ui.card-header>

                @if (auth()->user()->two_factor_secret)
                    <x-ui.alert variant="success" icon="shield-check" title="2FA is active">
                        New sign-ins will require a verification code.
                    </x-ui.alert>
                    <form method="POST" action="{{ url('/user/two-factor-authentication') }}" class="mt-4">
                        @csrf
                        @method('DELETE')
                        <x-ui.button type="submit" variant="destructive" size="sm">Disable 2FA</x-ui.button>
                    </form>
                @else
                    <form method="POST" action="{{ url('/user/two-factor-authentication') }}">
                        @csrf
                        <x-ui.button type="submit" size="sm">Enable 2FA</x-ui.button>
                    </form>
                @endif
            </x-ui.card>

            <x-ui.card>
                <x-ui.card-header>
                    <div>
                        <x-ui.card-title>System tools</x-ui.card-title>
                        <x-ui.card-description>Inspect health, queue state, and UI consistency.</x-ui.card-description>
                    </div>
                </x-ui.card-header>
                <div class="grid gap-3">
                    <x-ui.item icon="server-stack" title="Diagnostics" meta="Queue, cache, database, deploy workers">
                        <x-ui.button href="{{ route('system.diagnostics') }}" variant="outline" size="sm">Open diagnostics</x-ui.button>
                    </x-ui.item>
                    <x-ui.item icon="swatch" title="UI reference" meta="Components, spacing, and states">
                        <x-ui.button href="{{ route('system.ui') }}" variant="outline" size="sm">Open UI system</x-ui.button>
                    </x-ui.item>
                </div>
            </x-ui.card>
        </div>

        <x-ui.card>
            <x-ui.card-header>
                <div>
                    <x-ui.card-title>Discord notifications</x-ui.card-title>
                    <x-ui.card-description>Send deploy and maintenance notifications to Discord.</x-ui.card-description>
                </div>
            </x-ui.card-header>
            @livewire('settings.discord-webhook')
        </x-ui.card>

        <x-ui.card>
            <x-ui.card-header>
                <div>
                    <x-ui.card-title>API tokens</x-ui.card-title>
                    <x-ui.card-description>Generate scoped tokens for the Pixelkraft API.</x-ui.card-description>
                </div>
            </x-ui.card-header>
            @livewire('settings.api-tokens')
        </x-ui.card>
    </div>
</x-layouts.app>
