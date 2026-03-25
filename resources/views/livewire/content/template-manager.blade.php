<div class="max-w-4xl">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h2 class="text-lg font-semibold text-zinc-100">Content Templates</h2>
            <p class="text-sm text-zinc-500">Reusable page, section, and component templates with <code class="mono text-zinc-400">@{{placeholders}}</code>.</p>
        </div>
        @unless ($showForm)
            <button wire:click="create" class="btn-primary text-sm">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                New Template
            </button>
        @endunless
    </div>

    @if ($showForm)
        {{-- Editor --}}
        <div class="card space-y-4">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="input-label">Name</label>
                    <input type="text" wire:model="name" class="input-field" placeholder="Blog Post Template">
                    @error('name') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="input-label">Type</label>
                    <select wire:model="type" class="input-field">
                        <option value="page">Page</option>
                        <option value="section">Section</option>
                        <option value="component">Component (header/footer)</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="input-label">HTML Template</label>
                <p class="text-[10px] text-zinc-600 mb-2">Use <code class="text-zinc-400">@{{title}}</code>, <code class="text-zinc-400">@{{body}}</code>, <code class="text-zinc-400">@{{image}}</code> etc. as placeholders.</p>
                <textarea
                    wire:model="htmlTemplate"
                    rows="16"
                    class="input-field mono text-xs resize-y"
                    spellcheck="false"
                    placeholder="<!DOCTYPE html>
<html>
<head><title>{{title}}</title></head>
<body>
  <h1>{{title}}</h1>
  <div>{{body}}</div>
</body>
</html>"
                ></textarea>
                @error('htmlTemplate') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="input-label">Fields Schema <span class="text-zinc-600 font-normal">(optional JSON)</span></label>
                <textarea
                    wire:model="fieldsSchema"
                    rows="6"
                    class="input-field mono text-xs resize-y"
                    spellcheck="false"
                    placeholder='[
  {"name": "title", "type": "text", "required": true},
  {"name": "body", "type": "richtext", "required": true},
  {"name": "image", "type": "image"}
]'
                ></textarea>
                @error('fieldsSchema') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
            </div>

            <div class="flex items-center gap-3">
                <button wire:click="save" class="btn-primary text-sm">{{ $editingId ? 'Update' : 'Create' }} Template</button>
                <button wire:click="cancel" class="btn-ghost text-sm">Cancel</button>
            </div>
        </div>
    @else
        {{-- Template list --}}
        <div class="space-y-3">
            @forelse ($templates as $template)
                <div class="card-hover flex items-center justify-between">
                    <div>
                        <div class="flex items-center gap-2">
                            <h4 class="text-sm font-medium text-zinc-200">{{ $template->name }}</h4>
                            @switch($template->type)
                                @case('page')
                                    <span class="badge-blue !text-[10px]">page</span>
                                    @break
                                @case('section')
                                    <span class="badge-purple !text-[10px]">section</span>
                                    @break
                                @case('component')
                                    <span class="badge-green !text-[10px]">component</span>
                                    @break
                            @endswitch
                            @if ($template->isGlobal())
                                <span class="badge-amber !text-[10px]">global</span>
                            @endif
                        </div>
                        <p class="mono text-[10px] text-zinc-600 mt-0.5">
                            {{ Str::limit($template->html_template, 100) }}
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button wire:click="edit('{{ $template->id }}')" class="btn-ghost text-xs !px-2 !py-1">Edit</button>
                        <button
                            wire:click="delete('{{ $template->id }}')"
                            wire:confirm="Delete this template?"
                            class="text-xs text-red-400 hover:text-red-300 px-2 py-1"
                        >Delete</button>
                    </div>
                </div>
            @empty
                <div class="card py-12 text-center text-sm text-zinc-500">
                    No templates yet. Create your first template to use with blog posts and pages.
                </div>
            @endforelse
        </div>
    @endif
</div>
