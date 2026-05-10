<div class="space-y-6">
    {{-- R2 not configured warning --}}
    @if (!$isConfigured)
        <flux:callout variant="warning" icon="exclamation-triangle">
            <flux:callout.heading>R2 media storage is not configured</flux:callout.heading>
            <flux:callout.text>
                Add <code>R2_ACCESS_KEY_ID</code>, <code>R2_SECRET_ACCESS_KEY</code>, <code>R2_BUCKET</code>,
                <code>R2_ENDPOINT</code>, and <code>R2_URL</code> to your <code>.env</code> file to enable
                cloud media uploads.
            </flux:callout.text>
        </flux:callout>
    @endif

    {{-- Upload form --}}
    @if ($isConfigured)
        <flux:card class="p-6">
            <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100 mb-4">Upload media</h3>

            <form wire:submit="upload" class="space-y-4">
                <flux:input type="file" wire:model="mediaFile"
                    accept=".jpg,.jpeg,.png,.gif,.webp,.svg,.avif,.mp4,.webm,.pdf,.woff,.woff2" />

                @error('mediaFile')
                    <flux:callout variant="danger" icon="x-circle">{{ $message }}</flux:callout>
                @enderror

                @if ($uploadError)
                    <flux:callout variant="danger" icon="x-circle">{{ $uploadError }}</flux:callout>
                @endif

                @if ($lastUploadedUrl)
                    <div class="flex items-center gap-2 p-3 bg-green-50 dark:bg-green-950 rounded-lg border border-green-200 dark:border-green-800">
                        <code class="flex-1 text-xs text-green-800 dark:text-green-200 break-all">{{ $lastUploadedUrl }}</code>
                        <flux:button size="xs" variant="ghost"
                            x-data x-on:click="navigator.clipboard.writeText('{{ $lastUploadedUrl }}')">
                            Copy
                        </flux:button>
                    </div>
                @endif

                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                    <span wire:loading.remove>Upload</span>
                    <span wire:loading>Uploading…</span>
                </flux:button>
            </form>
        </flux:card>
    @endif

    {{-- Media file list --}}
    @if ($isConfigured && count($mediaFiles) > 0)
        <flux:card class="p-6">
            <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100 mb-4">
                Media files ({{ count($mediaFiles) }})
            </h3>
            <div class="space-y-2">
                @foreach ($mediaFiles as $file)
                    <div class="flex items-center justify-between gap-3 py-2 border-b border-zinc-100 dark:border-zinc-800 last:border-0">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100 truncate">{{ $file['name'] }}</p>
                            <p class="text-xs text-zinc-500">{{ number_format($file['size'] / 1024, 1) }} KB · {{ $file['last_modified'] }}</p>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <flux:button size="xs" variant="ghost"
                                x-data x-on:click="navigator.clipboard.writeText('{{ $file['url'] }}')">
                                Copy URL
                            </flux:button>
                            <flux:button size="xs" variant="ghost"
                                wire:click="deleteMedia('{{ $file['path'] }}')"
                                wire:confirm="Delete {{ $file['name'] }}? This cannot be undone.">
                                Delete
                            </flux:button>
                        </div>
                    </div>
                @endforeach
            </div>
        </flux:card>
    @elseif ($isConfigured)
        <p class="text-sm text-zinc-500">No media files uploaded yet for this site.</p>
    @endif
</div>
