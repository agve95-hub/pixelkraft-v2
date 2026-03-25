<div class="max-w-2xl space-y-8">
    <div>
        <h2 class="text-lg font-semibold text-zinc-100">Site Settings</h2>
        <p class="text-sm text-zinc-500">Configure build, domain, and project settings.</p>
    </div>

    {{-- General --}}
    <div class="card">
        <h3 class="text-sm font-semibold text-zinc-200 mb-4">General</h3>
        <form wire:submit="save" class="space-y-4">
            <div>
                <label class="input-label">Site name</label>
                <input type="text" wire:model="name" class="input-field">
                @error('name') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="input-label">Domain</label>
                <input type="text" wire:model="domain" class="input-field mono text-sm" placeholder="example.com">
                @error('domain') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="input-label">Branch</label>
                <input type="text" wire:model="branch" class="input-field mono text-sm">
            </div>

            <div>
                <label class="input-label">Project type</label>
                <select wire:model="projectType" class="input-field">
                    @foreach (config('pixelkraft.project_types') as $type)
                        <option value="{{ $type }}">{{ $type }}</option>
                    @endforeach
                </select>
            </div>

            <button type="submit" class="btn-primary">Save</button>
        </form>
    </div>

    {{-- Build Configuration --}}
    <div class="card">
        <h3 class="text-sm font-semibold text-zinc-200 mb-4">Build Configuration</h3>
        <form wire:submit="save" class="space-y-4">
            <div>
                <label class="input-label">Build command</label>
                <input type="text" wire:model="buildCommand" class="input-field mono text-sm" placeholder="npm run build">
                <p class="mt-1 text-xs text-zinc-600">Leave empty for static HTML sites with no build step.</p>
            </div>

            <div>
                <label class="input-label">Build output directory</label>
                <input type="text" wire:model="buildOutputDir" class="input-field mono text-sm" placeholder="dist">
                <p class="mt-1 text-xs text-zinc-600">Relative to repo root. Common values: dist, build, public, _site</p>
            </div>

            <button type="submit" class="btn-primary">Save</button>
        </form>
    </div>

    {{-- Danger Zone --}}
    <div class="card border-red-500/20">
        <h3 class="text-sm font-semibold text-red-400 mb-4">Danger Zone</h3>
        <p class="text-sm text-zinc-400 mb-4">Permanently delete this site and all associated data (pages, regions, deploy logs, analytics).</p>
        <div x-data="{ confirm: false }">
            <button
                x-show="!confirm"
                x-on:click="confirm = true"
                class="btn-danger text-sm"
            >
                Delete site
            </button>
            <div x-show="confirm" x-cloak class="flex items-center gap-3">
                <p class="text-sm text-red-400">Are you sure? This cannot be undone.</p>
                <button wire:click="deleteSite" class="btn-danger text-sm">Yes, delete</button>
                <button x-on:click="confirm = false" class="btn-ghost text-sm">Cancel</button>
            </div>
        </div>
    </div>
</div>
