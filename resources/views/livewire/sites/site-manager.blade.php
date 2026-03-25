<div class="max-w-xl">
    <div class="mb-6">
        <h2 class="text-lg font-semibold text-zinc-100">Add a new site</h2>
        <p class="text-sm text-zinc-500">Connect a GitHub repository to start managing it.</p>
    </div>

    <form wire:submit="create" class="space-y-5">
        <div>
            <label for="name" class="input-label">Site name</label>
            <input
                id="name"
                type="text"
                wire:model="name"
                class="input-field"
                placeholder="My Portfolio"
            >
            @error('name') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="repoUrl" class="input-label">GitHub repository URL</label>
            <input
                id="repoUrl"
                type="url"
                wire:model="repoUrl"
                class="input-field mono text-sm"
                placeholder="https://github.com/user/repo.git"
            >
            @error('repoUrl') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="branch" class="input-label">Branch</label>
            <input
                id="branch"
                type="text"
                wire:model="branch"
                class="input-field mono text-sm"
                placeholder="main"
            >
            @error('branch') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="githubToken" class="input-label">
                GitHub Personal Access Token
                <span class="text-zinc-600 font-normal">(required for private repos)</span>
            </label>
            <input
                id="githubToken"
                type="password"
                wire:model="githubToken"
                class="input-field mono text-sm"
                placeholder="ghp_xxxxxxxxxxxx"
            >
            <p class="mt-1 text-xs text-zinc-600">Token is encrypted at rest. Needs <code class="text-zinc-500">repo</code> scope.</p>
            @error('githubToken') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
        </div>

        <div class="flex items-center gap-3 pt-2">
            <button type="submit" class="btn-primary" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="create">Add site</span>
                <span wire:loading wire:target="create">
                    <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                    Creating...
                </span>
            </button>
            <a href="{{ route('sites.index') }}" class="btn-ghost">Cancel</a>
        </div>
    </form>
</div>
