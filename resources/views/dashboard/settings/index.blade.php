<x-layouts.app>
    <x-slot:title>Settings</x-slot:title>

    <div class="max-w-2xl space-y-8">
        {{-- Profile --}}
        <div class="card">
            <h3 class="text-sm font-semibold text-zinc-200 mb-4">Profile</h3>
            <form method="POST" action="{{ route('user-profile-information.update') }}" class="space-y-4">
                @csrf
                @method('PUT')

                <div>
                    <label for="name" class="input-label">Name</label>
                    <input id="name" type="text" name="name" value="{{ auth()->user()->name }}" class="input-field" required>
                </div>

                <div>
                    <label for="email" class="input-label">Email</label>
                    <input id="email" type="email" name="email" value="{{ auth()->user()->email }}" class="input-field" required>
                </div>

                <button type="submit" class="btn-primary">Save profile</button>
            </form>
        </div>

        {{-- Change Password --}}
        <div class="card">
            <h3 class="text-sm font-semibold text-zinc-200 mb-4">Change Password</h3>
            <form method="POST" action="{{ route('user-password.update') }}" class="space-y-4">
                @csrf
                @method('PUT')

                <div>
                    <label for="current_password" class="input-label">Current password</label>
                    <input id="current_password" type="password" name="current_password" class="input-field" required>
                </div>

                <div>
                    <label for="password" class="input-label">New password</label>
                    <input id="password" type="password" name="password" class="input-field" required>
                </div>

                <div>
                    <label for="password_confirmation" class="input-label">Confirm new password</label>
                    <input id="password_confirmation" type="password" name="password_confirmation" class="input-field" required>
                </div>

                <button type="submit" class="btn-primary">Update password</button>
            </form>
        </div>

        {{-- Two-Factor --}}
        <div class="card">
            <h3 class="text-sm font-semibold text-zinc-200 mb-4">Two-Factor Authentication</h3>
            @if (auth()->user()->two_factor_secret)
                <div class="flex items-center gap-3 mb-4">
                    <span class="badge-green">Enabled</span>
                    <p class="text-sm text-zinc-400">2FA is active on your account.</p>
                </div>
                <form method="POST" action="{{ url('/user/two-factor-authentication') }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn-danger text-sm">Disable 2FA</button>
                </form>
            @else
                <p class="text-sm text-zinc-400 mb-4">Add an extra layer of security to your account.</p>
                <form method="POST" action="{{ url('/user/two-factor-authentication') }}">
                    @csrf
                    <button type="submit" class="btn-primary text-sm">Enable 2FA</button>
                </form>
            @endif
        </div>

        {{-- Discord Webhook --}}
        <div class="card">
            <h3 class="text-sm font-semibold text-zinc-200 mb-4">Discord Notifications</h3>
            @livewire('settings.discord-webhook')
        </div>

        {{-- API Tokens --}}
        <div class="card">
            <h3 class="text-sm font-semibold text-zinc-200 mb-4">API Tokens</h3>
            <p class="text-sm text-zinc-500 mb-4">Generate tokens to access the pixelkraft API.</p>
            @livewire('settings.api-tokens')
        </div>
    </div>
</x-layouts.app>
