<div>
    <form wire:submit="save" class="space-y-4">
        <div>
            <label class="input-label">Webhook URL</label>
            <input
                type="url"
                wire:model="webhookUrl"
                class="input-field mono text-sm"
                placeholder="https://discord.com/api/webhooks/..."
            >
            @error('webhookUrl') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
        </div>

        <div class="flex items-center gap-3">
            <button type="submit" class="btn-primary text-sm">Save</button>
            @if ($webhookUrl)
                <button type="button" wire:click="testWebhook" class="btn-secondary text-sm">
                    Send test
                </button>
            @endif
        </div>
    </form>
</div>
