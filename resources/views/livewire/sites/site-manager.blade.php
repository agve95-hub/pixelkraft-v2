<div class="max-w-xl">
    <div class="mb-6">
        <flux:heading size="lg">Add a new site</flux:heading>
        <flux:subheading>Connect a GitHub repository to start managing it.</flux:subheading>
    </div>

    <flux:card>
        <form wire:submit="create" class="space-y-6">
            <flux:field>
                <flux:label>Site name</flux:label>
                <flux:input wire:model="name" placeholder="My Portfolio" />
                <flux:error name="name" />
            </flux:field>

            <flux:field>
                <flux:label>GitHub repository URL</flux:label>
                <flux:input wire:model="repoUrl" placeholder="https://github.com/user/repo.git" class="font-mono" />
                <flux:error name="repoUrl" />
            </flux:field>

            <flux:field>
                <flux:label>Branch</flux:label>
                <flux:input wire:model="branch" placeholder="main" class="font-mono" />
                <flux:error name="branch" />
            </flux:field>

            <flux:field>
                <flux:label badge="Required for private repos">GitHub Personal Access Token</flux:label>
                <flux:input type="password" wire:model="githubToken" placeholder="ghp_xxxxxxxxxxxx" class="font-mono" viewable />
                <flux:description>Token is encrypted at rest. Needs <code>repo</code> scope.</flux:description>
                <flux:error name="githubToken" />
            </flux:field>

            <div class="flex items-center gap-3">
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="create">Add site</span>
                    <span wire:loading wire:target="create">Creating...</span>
                </flux:button>
                <flux:button href="{{ route('sites.index') }}" variant="ghost">Cancel</flux:button>
            </div>
        </form>
    </flux:card>
</div>
