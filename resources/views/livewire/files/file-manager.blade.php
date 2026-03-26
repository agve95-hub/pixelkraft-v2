<div class="space-y-4">
    {{-- Breadcrumb + Upload --}}
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-1 text-xs text-zinc-500 mono">
            <button wire:click="navigateTo('')" class="hover:text-violet-400 transition">root</button>
            @foreach (explode('/', $currentPath) as $segment)
                @if ($segment)
                    <span class="text-zinc-700">/</span>
                    @php
                        $partialPath = implode('/', array_slice(explode('/', $currentPath), 0, $loop->index + 1));
                    @endphp
                    <button wire:click="navigateTo('{{ $partialPath }}')" class="hover:text-violet-400 transition">{{ $segment }}</button>
                @endif
            @endforeach
        </div>

        <div class="flex items-center gap-2">
            @if ($currentPath)
                <button wire:click="goUp" class="btn-ghost text-xs !px-2 !py-1">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 15.75 7.5-7.5 7.5 7.5" /></svg>
                    Up
                </button>
            @endif

            <label class="btn-secondary text-xs cursor-pointer">
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" /></svg>
                Upload
                <input type="file" wire:model="uploadFile" class="hidden" x-on:change="$wire.upload()">
            </label>
        </div>
    </div>

    <div class="flex gap-4">
        {{-- File list --}}
        <div @class(['card overflow-hidden !p-0', 'flex-1' => !$viewingFile, 'w-1/3 flex-shrink-0' => $viewingFile])>
            <div class="divide-y divide-zinc-800/50">
                @forelse ($entries as $entry)
                    <div class="flex items-center gap-3 px-4 py-2 hover:bg-zinc-800/30 transition cursor-pointer group"
                        @if ($entry['type'] === 'directory')
                            wire:click="navigateTo('{{ $entry['path'] }}')"
                        @else
                            wire:click="viewFile('{{ $entry['path'] }}')"
                        @endif
                    >
                        {{-- Icon --}}
                        @if ($entry['type'] === 'directory')
                            <svg class="h-4 w-4 text-amber-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 0 1 4.5 9.75h15A2.25 2.25 0 0 1 21.75 12v.75m-8.69-6.44-2.12-2.12a1.5 1.5 0 0 0-1.061-.44H4.5A2.25 2.25 0 0 0 2.25 6v12a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9a2.25 2.25 0 0 0-2.25-2.25h-5.379a1.5 1.5 0 0 1-1.06-.44Z" /></svg>
                        @else
                            <svg class="h-4 w-4 text-zinc-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" /></svg>
                        @endif

                        <div class="flex-1 min-w-0">
                            <span @class([
                                'text-xs truncate',
                                'text-zinc-200 font-medium' => $entry['type'] === 'directory',
                                'text-zinc-400 mono' => $entry['type'] === 'file',
                                'text-violet-400' => $viewingFile === $entry['path'],
                            ])>{{ $entry['name'] }}</span>
                        </div>

                        @if ($entry['type'] === 'file')
                            <span class="mono text-[10px] text-zinc-700 flex-shrink-0">
                                {{ $this->formatSize($entry['size'] ?? 0) }}
                            </span>

                            <button
                                wire:click.stop="deleteFile('{{ $entry['path'] }}')"
                                wire:confirm="Delete {{ $entry['name'] }}?"
                                class="opacity-0 group-hover:opacity-100 text-zinc-600 hover:text-red-400 transition flex-shrink-0"
                            >
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" /></svg>
                            </button>
                        @endif
                    </div>
                @empty
                    <div class="px-4 py-8 text-center text-sm text-zinc-500">Empty directory</div>
                @endforelse
            </div>
        </div>

        {{-- File viewer --}}
        @if ($viewingFile)
            <div class="flex-1 card !p-0 flex flex-col overflow-hidden">
                <div class="flex items-center justify-between px-4 py-2 border-b border-zinc-800">
                    <span class="mono text-xs text-zinc-400 truncate">{{ $viewingFile }}</span>
                    <div class="flex items-center gap-2">
                        <button wire:click="saveFile" class="btn-primary text-[10px] !px-2 !py-1">Save & Push</button>
                        <button wire:click="closeFile" class="text-zinc-600 hover:text-zinc-400">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                        </button>
                    </div>
                </div>
                <div class="flex-1 overflow-auto">
                    <textarea
                        wire:model.live.debounce.500ms="fileContent"
                        class="w-full h-full bg-transparent text-zinc-300 mono text-xs p-4 resize-none border-0 focus:outline-none focus:ring-0 min-h-[400px]"
                        spellcheck="false"
                    ></textarea>
                </div>
            </div>
        @endif
    </div>

    @php
        // Helper method accessible in blade
        if (!method_exists($this, 'formatSize')) {
            // Will use the component method
        }
    @endphp
</div>
