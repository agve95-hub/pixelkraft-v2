<div>
    <form wire:submit="save" class="space-y-4">
        <flux:field>
            <flux:label>Webhook URL</flux:label>
            <flux:input wire:model="webhookUrl" placeholder="https://discord.com/api/webhooks/..." class="font-mono" />
            <flux:error name="webhookUrl" />
        </flux:field>

        <div class="flex items-center gap-3">
            <flux:button type="submit" variant="primary" size="sm">Save</flux:button>
            @if ($webhookUrl)
                <flux:button wire:click="testWebhook" variant="subtle" size="sm">Send test</flux:button>
            @endif
        </div>
    </form>
</div>
