@php
    $draftCount = $reports->getCollection()->filter(fn ($report) => $report->status() === 'draft')->count();
    $sentCount = $reports->getCollection()->filter(fn ($report) => $report->status() === 'sent')->count();
@endphp

<div class="max-w-none space-y-6">
    @if ($screen === 'index')
        <div class="pk-page-head">
            <div>
                <a href="{{ route('sites.show', $site) }}" wire:navigate class="mb-2 inline-flex items-center gap-1 text-sm text-zinc-500 transition hover:text-zinc-300">
                    <span aria-hidden="true">&larr;</span>
                    {{ $site->name }}
                </a>
                <h1 class="pk-page-title">Reports</h1>
                <p class="pk-page-sub">{{ $site->clientDisplayName() }} &middot; client and internal updates</p>
            </div>
            <flux:button type="button" wire:click="startCreate" variant="primary" icon="plus">
                New report
            </flux:button>
        </div>

        <div class="stats stats-3">
            <div class="stat">
                <p class="stat-label">Reports</p>
                <p class="stat-val tabular-nums">{{ $reports->total() }}</p>
            </div>
            <div class="stat">
                <p class="stat-label">Sent</p>
                <p class="stat-val tabular-nums text-emerald-400">{{ $sentCount }}</p>
            </div>
            <div class="stat">
                <p class="stat-label">Drafts</p>
                <p class="stat-val tabular-nums {{ $draftCount > 0 ? 'text-amber-400' : '' }}">{{ $draftCount }}</p>
            </div>
        </div>

        <div class="table-wrap">
            @forelse ($reports as $report)
                @php
                    $status = $report->status();
                    $sections = $report->sections();
                    $itemCount = collect($sections)->sum(fn ($section) => count($section['items'] ?? []));
                @endphp
                <button
                    type="button"
                    wire:click="openReport('{{ $report->id }}')"
                    wire:key="report-row-{{ $report->id }}"
                    class="grid w-full grid-cols-1 gap-2 border-b border-zinc-800/80 px-4 py-3 text-left transition last:border-b-0 hover:bg-zinc-800/30 md:grid-cols-[1fr_auto_auto_auto] md:items-center md:gap-4"
                >
                    <span>
                        <span class="block text-sm font-medium text-zinc-100">{{ $report->title }}</span>
                        <span class="mt-0.5 block text-xs text-zinc-500">{{ \Illuminate\Support\Str::limit((string) $report->summary, 120) ?: 'No summary yet.' }}</span>
                    </span>
                    <span class="font-mono text-xs text-zinc-500">{{ $report->report_date?->toDateString() }}</span>
                    <span class="font-mono text-xs text-zinc-500">{{ count($sections) }} sections &middot; {{ $itemCount }} items</span>
                    <span class="inline-flex">
                        <span class="pill {{ $status === 'sent' ? 'pill-green' : 'pill-yellow' }}">{{ ucfirst($status) }}</span>
                    </span>
                </button>
            @empty
                <div class="empty">
                    <div class="empty-icon"><flux:icon name="clipboard-document" class="size-4" /></div>
                    <p>No reports yet</p>
                    <button type="button" wire:click="startCreate" class="text-sm font-medium text-emerald-400 hover:text-emerald-300">Create the first report</button>
                </div>
            @endforelse
        </div>

        <div>
            {{ $reports->links() }}
        </div>
    @endif

    @if ($screen === 'show' && $activeReport)
        @php
            $status = $activeReport->status();
            $sections = $activeReport->sections();
            $nextSteps = $activeReport->nextSteps();
        @endphp

        <button type="button" wire:click="backToList" class="mb-4 inline-flex items-center gap-1 text-sm text-zinc-500 transition hover:text-zinc-300">
            <span aria-hidden="true">&larr;</span>
            Reports
        </button>

        <div class="pk-page-head">
            <div>
                <div class="flex flex-wrap items-center gap-3">
                    <h1 class="pk-page-title">{{ $activeReport->title }}</h1>
                    <span class="pill {{ $status === 'sent' ? 'pill-green' : 'pill-yellow' }}">{{ ucfirst($status) }}</span>
                </div>
                <p class="pk-page-sub">{{ $activeReport->report_date?->format('F j, Y') }} &middot; {{ $site->clientDisplayName() }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                @if ($status !== 'sent')
                    <flux:button type="button" wire:click="markSent('{{ $activeReport->id }}')" size="sm" variant="subtle">Mark sent</flux:button>
                @endif
                <flux:button type="button" wire:click="delete('{{ $activeReport->id }}')" wire:confirm="Delete this report?" size="sm" variant="danger">Delete</flux:button>
            </div>
        </div>

        <x-ui.card>
            <x-ui.card-header>
                <p class="pk-ui-card-title">Summary</p>
                <p class="font-mono text-xs text-zinc-500">{{ count($sections) }} sections</p>
            </div>
            <p class="whitespace-pre-wrap text-sm leading-6 text-zinc-300">{{ $activeReport->summary ?: 'No summary was added.' }}</p>
        </section>

        <div class="grid gap-4 lg:grid-cols-3">
            <div class="space-y-4 lg:col-span-2">
                @forelse ($sections as $section)
                    <x-ui.card>
                        <x-ui.card-header>
                            <p class="pk-ui-card-title">{{ $section['title'] ?? 'Section' }}</p>
                            <span class="tag">{{ $section['type'] ?? 'general' }}</span>
                        </div>
                        <ul class="space-y-2 text-sm text-zinc-300">
                            @forelse (($section['items'] ?? []) as $item)
                                <li class="flex gap-2">
                                    <span class="mt-2 size-1.5 shrink-0 rounded-full bg-emerald-400"></span>
                                    <span>{{ $item }}</span>
                                </li>
                            @empty
                                <li class="text-zinc-500">No items in this section.</li>
                            @endforelse
                        </ul>
                    </section>
                @empty
                    <section class="empty rounded-xl border border-zinc-800">
                        <div class="empty-icon"><flux:icon name="list-bullet" class="size-4" /></div>
                        No report sections were added.
                    </section>
                @endforelse
            </div>

            <x-ui.card>
                <x-ui.card-header>
                    <p class="pk-ui-card-title">Next steps</p>
                </div>
                <ul class="space-y-2 text-sm text-zinc-300">
                    @forelse ($nextSteps as $step)
                        <li class="flex gap-2">
                            <span class="mt-2 size-1.5 shrink-0 rounded-full bg-amber-400"></span>
                            <span>{{ $step }}</span>
                        </li>
                    @empty
                        <li class="text-zinc-500">No next steps recorded.</li>
                    @endforelse
                </ul>
            </section>
        </div>
    @endif

    @if ($screen === 'create')
        <button type="button" wire:click="cancelCreate" class="mb-4 inline-flex items-center gap-1 text-sm text-zinc-500 transition hover:text-zinc-300">
            <span aria-hidden="true">&larr;</span>
            Reports
        </button>

        <div class="pk-page-head">
            <div>
                <h1 class="pk-page-title">New report</h1>
                <p class="pk-page-sub">{{ $site->clientDisplayName() }}</p>
            </div>
        </div>

        <form wire:submit="save" class="max-w-4xl space-y-5">
            <x-ui.card>
                <div class="grid gap-4 md:grid-cols-2">
                    <flux:field>
                        <flux:label>Title</flux:label>
                        <flux:input wire:model="form_title" placeholder="e.g. April 2026 report" />
                        <flux:error name="form_title" />
                    </flux:field>
                    <flux:field>
                        <flux:label>Report date</flux:label>
                        <flux:input type="date" wire:model="form_report_date" />
                        <flux:error name="form_report_date" />
                    </flux:field>
                    <flux:field>
                        <flux:label>Status</flux:label>
                        <flux:select wire:model="form_status">
                            <flux:select.option value="draft">Draft</flux:select.option>
                            <flux:select.option value="sent">Sent</flux:select.option>
                        </flux:select>
                        <flux:error name="form_status" />
                    </flux:field>
                </div>
                <div class="mt-4">
                    <flux:field>
                        <flux:label>Summary</flux:label>
                        <flux:textarea wire:model="form_summary" rows="5" placeholder="Highlights, important context, and overall project status." />
                        <flux:error name="form_summary" />
                    </flux:field>
                </div>
            </section>

            <x-ui.card>
                <x-ui.card-header>
                    <p class="pk-ui-card-title">Sections</p>
                    <flux:button type="button" wire:click="addSection" size="sm" variant="subtle">Add section</flux:button>
                </div>

                <div class="space-y-4">
                    @foreach ($form_sections as $sectionIndex => $section)
                        <div wire:key="report-section-{{ $sectionIndex }}" class="rounded-lg border border-zinc-800/80 p-3">
                            <div class="grid gap-3 md:grid-cols-[140px_1fr_auto] md:items-end">
                                <flux:field>
                                    <flux:label>Type</flux:label>
                                    <flux:input wire:model="form_sections.{{ $sectionIndex }}.type" placeholder="seo" />
                                </flux:field>
                                <flux:field>
                                    <flux:label>Section title</flux:label>
                                    <flux:input wire:model="form_sections.{{ $sectionIndex }}.title" placeholder="Development" />
                                </flux:field>
                                <flux:button type="button" wire:click="removeSection({{ $sectionIndex }})" size="sm" variant="ghost" class="text-red-400">Remove</flux:button>
                            </div>

                            <div class="mt-3 space-y-2">
                                @foreach (($section['items'] ?? []) as $itemIndex => $item)
                                    <div wire:key="report-section-{{ $sectionIndex }}-item-{{ $itemIndex }}" class="grid gap-2 md:grid-cols-[1fr_auto]">
                                        <flux:input wire:model="form_sections.{{ $sectionIndex }}.items.{{ $itemIndex }}" placeholder="Report item" />
                                        <flux:button type="button" wire:click="removeSectionItem({{ $sectionIndex }}, {{ $itemIndex }})" size="sm" variant="ghost">Remove</flux:button>
                                    </div>
                                @endforeach
                                <flux:button type="button" wire:click="addSectionItem({{ $sectionIndex }})" size="sm" variant="subtle">Add item</flux:button>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>

            <x-ui.card>
                <x-ui.card-header>
                    <p class="pk-ui-card-title">Next steps</p>
                    <flux:button type="button" wire:click="addNextStep" size="sm" variant="subtle">Add step</flux:button>
                </div>
                <div class="space-y-2">
                    @foreach ($form_next_steps as $index => $step)
                        <div wire:key="report-step-{{ $index }}" class="grid gap-2 md:grid-cols-[1fr_auto]">
                            <flux:input wire:model="form_next_steps.{{ $index }}" placeholder="Next step" />
                            <flux:button type="button" wire:click="removeNextStep({{ $index }})" size="sm" variant="ghost">Remove</flux:button>
                        </div>
                    @endforeach
                </div>
            </section>

            <div class="flex justify-end gap-2">
                <flux:button type="button" wire:click="cancelCreate" variant="subtle">Cancel</flux:button>
                <flux:button type="submit" variant="primary">Save report</flux:button>
            </div>
        </form>
    @endif
</div>
