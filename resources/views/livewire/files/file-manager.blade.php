<div class="space-y-4">
    {{-- Breadcrumb + toolbar --}}
    <div class="flex items-center justify-between gap-4">
        <div class="flex items-center gap-1 font-mono text-xs text-zinc-500">
            <button wire:click="navigateTo('')" class="hover:text-zinc-200 transition-colors">root</button>
            @foreach (explode('/', $currentPath) as $segment)
                @if ($segment)
                    <span class="text-zinc-700">/</span>
                    @php $partialPath = implode('/', array_slice(explode('/', $currentPath), 0, $loop->index + 1)); @endphp
                    <button wire:click="navigateTo('{{ $partialPath }}')" class="hover:text-zinc-200 transition-colors">{{ $segment }}</button>
                @endif
            @endforeach
        </div>

        <x-ui.button-group>
            @if ($currentPath)
                <x-ui.button wire:click="goUp" variant="outline" size="sm" icon="chevron-up">Up</x-ui.button>
            @endif
            <label class="inline-flex h-[var(--ui-control-h-sm)] cursor-pointer items-center gap-2 rounded-[var(--radius)] border border-zinc-700 bg-transparent px-3 text-xs font-medium text-zinc-200 transition hover:bg-zinc-800 hover:border-zinc-600">
                <flux:icon name="arrow-up-tray" class="size-3.5" />
                Upload
                <input type="file" wire:model="uploadFile" class="hidden" x-on:change="$wire.upload()">
            </label>
        </x-ui.button-group>
    </div>

    <div class="flex gap-4">
        {{-- File list --}}
        <x-ui.card padding="flush" @class(['flex-1' => !$viewingFile, 'w-1/3 shrink-0' => $viewingFile])>
            @forelse ($entries as $entry)
                <div
                    class="group relative flex cursor-pointer items-center gap-3 border-b border-zinc-800/50 px-4 py-2.5 last:border-b-0 hover:bg-zinc-800/30 transition-colors"
                    @if ($entry['type'] === 'directory')
                        wire:click="navigateTo('{{ $entry['path'] }}')"
                    @else
                        wire:click="viewFile('{{ $entry['path'] }}')"
                    @endif
                >
                    @if ($entry['type'] === 'directory')
                        <flux:icon name="folder" class="size-4 shrink-0 text-amber-400" />
                    @else
                        <flux:icon name="document" class="size-4 shrink-0 text-zinc-500" />
                    @endif

                    <span @class([
                        'flex-1 min-w-0 truncate text-sm',
                        'font-medium text-zinc-200' => $entry['type'] === 'directory',
                        'font-mono text-xs text-zinc-400' => $entry['type'] === 'file',
                        'text-emerald-400' => $viewingFile === $entry['path'],
                    ])>{{ $entry['name'] }}</span>

                    @if ($entry['type'] === 'file')
                        <span class="shrink-0 pr-8 font-mono text-[10px] text-zinc-600 transition-opacity group-hover:opacity-0">{{ $this->formatSize($entry['size'] ?? 0) }}</span>
                        <x-ui.button
                            wire:click.stop="deleteFile('{{ $entry['path'] }}')"
                            wire:confirm="Delete {{ $entry['name'] }}?"
                            variant="ghost"
                            size="xs"
                            class="absolute right-2 top-1/2 -translate-y-1/2 px-1 text-red-400 opacity-0 group-hover:opacity-100"
                            icon="trash"
                        />
                    @endif
                </div>
            @empty
                <x-ui.empty icon="folder-open" title="Empty directory" />
            @endforelse
        </x-ui.card>

        {{-- File viewer / editor --}}
        @if ($viewingFile)
            <x-ui.card padding="flush" class="flex flex-1 flex-col overflow-hidden">
                <div class="flex items-center justify-between border-b border-zinc-800/70 px-4 py-2">
                    <span class="truncate font-mono text-xs text-zinc-400">{{ $viewingFile }}</span>
                    <x-ui.button-group>
                        <flux:button wire:click="saveFile" variant="primary" size="sm">Save &amp; Push</flux:button>
                        <x-ui.button wire:click="closeFile" variant="ghost" size="sm" icon="x-mark" />
                    </x-ui.button-group>
                </div>
                <div class="flex-1 overflow-auto">
                    <textarea
                        wire:model.live.debounce.500ms="fileContent"
                        class="min-h-[400px] h-full w-full resize-none border-0 bg-transparent p-4 font-mono text-xs text-zinc-300 focus:outline-none focus:ring-0"
                        spellcheck="false"
                    ></textarea>
                </div>
            </x-ui.card>
        @endif
    </div>
</div>
