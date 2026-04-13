<x-layouts.editor-shell :title="($page->title ?? $page->file_path) . ' — Editor'">
    @livewire('editor.visual-editor', ['siteId' => $site->id, 'pageId' => $page->id])
</x-layouts.editor-shell>
