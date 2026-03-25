<x-layouts.app>
    <x-slot:title>New Post — {{ $site->name }}</x-slot:title>

    @livewire('content.blog-editor', ['siteId' => $site->id])
</x-layouts.app>
