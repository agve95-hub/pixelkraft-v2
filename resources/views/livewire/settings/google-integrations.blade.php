<div class="space-y-6">
    <div>
        <h2 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">Google Analytics 4</h2>
        <p class="mt-1 text-sm text-zinc-500">
            Upload a service account JSON key to enable organic traffic sync.
            Create one in <a href="https://console.cloud.google.com/iam-admin/serviceaccounts" target="_blank" rel="noopener noreferrer" class="underline">Google Cloud Console</a>
            → IAM &amp; Admin → Service Accounts → Keys → Add Key → JSON.
            Grant the account <strong>Viewer</strong> access in your GA4 property.
        </p>
    </div>

    {{-- Current status --}}
    @if ($hasCredentials)
        <flux:callout variant="success" icon="check-circle">
            <flux:callout.heading>Credentials configured</flux:callout.heading>
            <flux:callout.text>
                Service account: <code>{{ $credEmail ?? 'unknown' }}</code><br>
                File: <code>{{ $credPath }}</code>
            </flux:callout.text>
        </flux:callout>
    @else
        <flux:callout variant="warning" icon="exclamation-triangle">
            <flux:callout.heading>No credentials configured</flux:callout.heading>
            <flux:callout.text>
                GA4 organic traffic sync is disabled. Sites with a Measurement ID set will show zero traffic until credentials are uploaded.
            </flux:callout.text>
        </flux:callout>
    @endif

    {{-- Result message --}}
    @if ($uploadMessage)
        <flux:callout :variant="$uploadSuccess ? 'success' : 'danger'" :icon="$uploadSuccess ? 'check-circle' : 'x-circle'">
            {{ $uploadMessage }}
        </flux:callout>
    @endif

    {{-- Upload form --}}
    <flux:card class="p-6">
        <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100 mb-4">
            {{ $hasCredentials ? 'Replace credentials' : 'Upload credentials' }}
        </h3>
        <form wire:submit="uploadCredentials" class="space-y-4">
            <div>
                <flux:label>Service account JSON file</flux:label>
                <flux:input type="file" wire:model="credentialsFile" accept=".json,application/json" class="mt-1" />
                @error('credentialsFile')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>
            <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                <span wire:loading.remove>Upload &amp; Save</span>
                <span wire:loading>Saving…</span>
            </flux:button>
        </form>
    </flux:card>

    {{-- Remove button --}}
    @if ($hasCredentials)
        <flux:card class="p-6 border-red-200 dark:border-red-900">
            <h3 class="text-sm font-semibold text-red-700 dark:text-red-400 mb-2">Remove credentials</h3>
            <p class="text-sm text-zinc-500 mb-4">Deletes the service account file and disables GA4 sync for all sites.</p>
            <flux:button variant="danger" wire:click="removeCredentials"
                wire:confirm="Remove GA4 credentials? This will disable organic traffic sync for all sites.">
                Remove
            </flux:button>
        </flux:card>
    @endif
</div>
