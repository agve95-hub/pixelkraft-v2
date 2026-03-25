<x-layouts.app>
    <x-slot:title>{{ $page->title ?? $page->file_path }} — Editor</x-slot:title>

    @livewire('editor.visual-editor', ['siteId' => $site->id, 'pageId' => $page->id])
</x-layouts.app>
