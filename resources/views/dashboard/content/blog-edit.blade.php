<x-layouts.app>
    <x-slot:title>Edit Post — {{ $site->name }}</x-slot:title>

    @livewire('content.blog-editor', ['siteId' => $site->id, 'postId' => $postId])
</x-layouts.app>
