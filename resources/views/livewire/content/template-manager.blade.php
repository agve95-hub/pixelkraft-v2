<div class="max-w-4xl space-y-5">
    <div class="pk-page-head">
        <div>
            <h1 class="pk-page-title">Content Templates</h1>
            <p class="pk-page-sub">Reusable page, section, and component templates with <code class="font-mono text-zinc-400">@{{placeholders}}</code>.</p>
        </div>
        @unless ($showForm)
            <flux:button wire:click="create" variant="primary" icon="plus">New Template</flux:button>
        @endunless
    </div>

    @if ($showForm)
        <x-ui.card>
            <x-ui.card-header>
                <x-ui.card-title>{{ $editingId ? 'Edit Template' : 'New Template' }}</x-ui.card-title>
            </x-ui.card-header>
            <x-ui.card-content>
                <div class="grid grid-cols-2 gap-4">
                    <flux:field>
                        <flux:label>Name</flux:label>
                        <flux:input wire:model="name" placeholder="Blog Post Template" />
                        <flux:error name="name" />
                    </flux:field>
                    <flux:field>
                        <flux:label>Type</flux:label>
                        <flux:select wire:model="type">
                            <flux:select.option value="page">Page</flux:select.option>
                            <flux:select.option value="section">Section</flux:select.option>
                            <flux:select.option value="component">Component (header/footer)</flux:select.option>
                        </flux:select>
                    </flux:field>
                </div>

                <flux:field>
                    <flux:label>HTML Template</flux:label>
                    <flux:description>Use <code class="font-mono text-zinc-400">@{{title}}</code>, <code class="font-mono text-zinc-400">@{{body}}</code> etc. as placeholders.</flux:description>
                    <flux:textarea wire:model="htmlTemplate" rows="16" class="font-mono text-xs" spellcheck="false"
                        placeholder='<!DOCTYPE html>
<html>
<head><title>{{title}}</title></head>
<body>
  <h1>{{title}}</h1>
  <div>{{body}}</div>
</body>
</html>' />
                    <flux:error name="htmlTemplate" />
                </flux:field>

                <flux:field>
                    <flux:label>Fields Schema <span class="font-normal text-zinc-500">(optional JSON)</span></flux:label>
                    <flux:textarea wire:model="fieldsSchema" rows="6" class="font-mono text-xs" spellcheck="false"
                        placeholder='[
  {"name": "title", "type": "text", "required": true},
  {"name": "body", "type": "richtext", "required": true},
  {"name": "image", "type": "image"}
]' />
                    <flux:error name="fieldsSchema" />
                </flux:field>

                <div class="flex items-center gap-3">
                    <flux:button wire:click="save" variant="primary">{{ $editingId ? 'Update' : 'Create' }} Template</flux:button>
                    <flux:button wire:click="cancel" variant="ghost">Cancel</flux:button>
                </div>
            </x-ui.card-content>
        </x-ui.card>
    @else
        <div class="space-y-3">
            @forelse ($templates as $template)
                <x-ui.card>
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <div class="flex items-center gap-2">
                                <p class="text-sm font-medium">{{ $template->name }}</p>
                                @switch($template->type)
                                    @case('page') <x-ui.badge variant="info">page</x-ui.badge> @break
                                    @case('section') <x-ui.badge>section</x-ui.badge> @break
                                    @case('component') <x-ui.badge variant="success">component</x-ui.badge> @break
                                @endswitch
                                @if ($template->isGlobal())
                                    <x-ui.badge variant="warning">global</x-ui.badge>
                                @endif
                            </div>
                            <p class="mt-0.5 font-mono text-[10px] text-zinc-600">{{ Str::limit($template->html_template, 100) }}</p>
                        </div>
                        <x-ui.button-group>
                            <x-ui.button wire:click="edit('{{ $template->id }}')" size="xs" variant="ghost">Edit</x-ui.button>
                            <x-ui.button wire:click="delete('{{ $template->id }}')" wire:confirm="Delete this template?" size="xs" variant="ghost" class="text-red-400">Delete</x-ui.button>
                        </x-ui.button-group>
                    </div>
                </x-ui.card>
            @empty
                <x-ui.empty icon="rectangle-stack" title="No templates yet."
                    description="Create your first template to use with blog posts and pages." />
            @endforelse
        </div>
    @endif
</div>
