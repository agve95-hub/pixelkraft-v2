<x-layouts.app>
    <x-slot:title>Files — {{ $site->name }}</x-slot:title>

    <div class="space-y-5">
        <div class="ui-page-head">
            <div>
                <a href="{{ route('sites.show', $site) }}" class="back-link">
                    <flux:icon name="chevron-left" class="size-3.5" /> {{ $site->name }}
                </a>
                <h1 class="ui-page-title">File Manager</h1>
                <p class="ui-page-sub">Browse, edit, and upload files in the repository.</p>
            </div>
        </div>

        @livewire('files.file-manager', ['siteId' => $site->id])
    </div>
</x-layouts.app>
