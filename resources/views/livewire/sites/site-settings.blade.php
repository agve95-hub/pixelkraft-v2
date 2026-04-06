<div class="max-w-2xl space-y-8">
    <div>
        <flux:heading size="lg">Site Settings</flux:heading>
        <flux:subheading>Configure build, domain, and project settings.</flux:subheading>
    </div>

    <flux:card>
        <flux:heading size="sm" class="mb-4">General</flux:heading>
        <form wire:submit="save" class="space-y-4">
            <flux:field>
                <flux:label>Site name</flux:label>
                <flux:input wire:model="name" />
                <flux:error name="name" />
            </flux:field>

            <flux:field>
                <flux:label>Domain</flux:label>
                <flux:input wire:model="domain" placeholder="example.com" class="font-mono" />
                <flux:error name="domain" />
            </flux:field>

            <flux:field>
                <flux:label>Branch</flux:label>
                <flux:input wire:model="branch" class="font-mono" />
            </flux:field>

            <flux:field>
                <flux:label>Project type</flux:label>
                <flux:select wire:model="projectType">
                    @foreach (config('pixelkraft.project_types') as $type)
                        <flux:select.option value="{{ $type }}">{{ $type }}</flux:select.option>
                    @endforeach
                </flux:select>
            </flux:field>

            <flux:button type="submit" variant="primary" size="sm">Save</flux:button>
        </form>
    </flux:card>

    <flux:card>
        <flux:heading size="sm" class="mb-4">Build Configuration</flux:heading>
        <form wire:submit="save" class="space-y-4">
            <flux:field>
                <flux:label>Build command</flux:label>
                <flux:input wire:model="buildCommand" placeholder="npm run build" class="font-mono" />
                <flux:description>Leave empty for static HTML sites with no build step.</flux:description>
            </flux:field>

            <flux:field>
                <flux:label>Build output directory</flux:label>
                <flux:input wire:model="buildOutputDir" placeholder="dist" class="font-mono" />
                <flux:description>Relative to repo root. Common values: dist, build, public, _site, out (Next.js static export)</flux:description>
            </flux:field>

            <flux:button type="submit" variant="primary" size="sm">Save</flux:button>
        </form>
    </flux:card>

    <flux:card class="border-red-200 dark:border-red-500/20">
        <flux:heading size="sm" class="text-red-600 dark:text-red-400 mb-4">Danger Zone</flux:heading>
        <flux:subheading class="mb-4">Permanently delete this site and all associated data.</flux:subheading>

        <div x-data="{ confirm: false }">
            <flux:button x-show="!confirm" x-on:click="confirm = true" variant="danger" size="sm">Delete site</flux:button>
            <div x-show="confirm" x-cloak class="flex items-center gap-3">
                <flux:text size="sm" class="text-red-500">Are you sure? This cannot be undone.</flux:text>
                <flux:button wire:click="deleteSite" variant="danger" size="sm">Yes, delete</flux:button>
                <flux:button x-on:click="confirm = false" variant="ghost" size="sm">Cancel</flux:button>
            </div>
        </div>
    </flux:card>
</div>
