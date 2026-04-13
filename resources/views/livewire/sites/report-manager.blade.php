<div class="space-y-6">
    <div>
        <flux:heading size="xl">Reports</flux:heading>
        <flux:subheading>{{ $site->name }} — client or internal report log.</flux:subheading>
    </div>

    <flux:card>
        <flux:heading size="lg" class="mb-4">New report</flux:heading>
        <form wire:submit="save" class="grid gap-4">
            <flux:field>
                <flux:label>Title</flux:label>
                <flux:input wire:model="form_title" placeholder="e.g. March 2026 summary" />
                <flux:error name="form_title" />
            </flux:field>
            <flux:field>
                <flux:label>Report date</flux:label>
                <flux:input type="date" wire:model="form_report_date" />
                <flux:error name="form_report_date" />
            </flux:field>
            <flux:field>
                <flux:label>Summary</flux:label>
                <flux:textarea wire:model="form_summary" rows="5" placeholder="Optional notes or highlights" />
                <flux:error name="form_summary" />
            </flux:field>
            <flux:button type="submit" variant="primary" icon="plus" class="!bg-emerald-500 hover:!bg-emerald-400 !text-zinc-950 dark:!text-zinc-950 w-fit">
                Save report
            </flux:button>
        </form>
    </flux:card>

    <flux:card>
        <flux:heading size="lg" class="mb-4">History</flux:heading>
        <div class="space-y-3">
            @forelse ($reports as $report)
                <div class="rounded-lg border border-zinc-800/80 bg-zinc-950/40 px-3 py-3">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div>
                            <p class="font-medium text-zinc-100">{{ $report->title }}</p>
                            <p class="text-xs text-zinc-500">{{ $report->report_date->format('Y-m-d') }}</p>
                        </div>
                        <flux:button type="button" wire:click="delete('{{ $report->id }}')" wire:confirm="Delete this report?" size="xs" variant="ghost" class="text-red-400 shrink-0">
                            Delete
                        </flux:button>
                    </div>
                    @if ($report->summary)
                        <p class="mt-2 whitespace-pre-wrap text-sm text-zinc-400">{{ \Illuminate\Support\Str::limit($report->summary, 500) }}</p>
                    @endif
                </div>
            @empty
                <p class="py-6 text-center text-sm text-zinc-500">No reports yet.</p>
            @endforelse
        </div>
        <div class="mt-4">
            {{ $reports->links() }}
        </div>
    </flux:card>
</div>
