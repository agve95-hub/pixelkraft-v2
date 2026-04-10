<div>
    <div class="rounded-xl border border-zinc-800/80 bg-[#1e1e1e] p-5">
        <div class="mb-4 flex items-center gap-2">
            <flux:icon name="calendar" class="size-4 text-zinc-500" />
            <h3 class="text-sm font-semibold text-zinc-100">Site health</h3>
        </div>

        <div class="overflow-x-auto -mx-5">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-zinc-800">
                        <th class="px-5 py-2 text-left text-[11px] font-medium uppercase tracking-[0.12em] text-zinc-500">Site</th>
                        <th class="px-3 py-2 text-left text-[11px] font-medium uppercase tracking-[0.12em] text-zinc-500">Status</th>
                        <th class="px-3 py-2 text-left text-[11px] font-medium uppercase tracking-[0.12em] text-zinc-500">SSL</th>
                        <th class="hidden px-3 py-2 text-left text-[11px] font-medium uppercase tracking-[0.12em] text-zinc-500 sm:table-cell">Uptime</th>
                        <th class="hidden px-3 py-2 text-left text-[11px] font-medium uppercase tracking-[0.12em] text-zinc-500 sm:table-cell">Response</th>
                        <th class="hidden px-3 py-2 text-left text-[11px] font-medium uppercase tracking-[0.12em] text-zinc-500 md:table-cell">Pages</th>
                        <th class="px-3 py-2 text-left text-[11px] font-medium uppercase tracking-[0.12em] text-zinc-500">Errors</th>
                        <th class="px-5 py-2 text-left text-[11px] font-medium uppercase tracking-[0.12em] text-zinc-500">SEO</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-800/80">
                    @forelse ($sites as $data)
                        <tr class="transition hover:bg-zinc-800/30">
                            <td class="px-5 py-2.5">
                                <div class="flex items-center gap-2">
                                    <span @class([
                                        'size-2 rounded-full shrink-0',
                                        'bg-emerald-400' => $data['is_up'] === true,
                                        'bg-red-400' => $data['is_up'] === false,
                                        'bg-zinc-500' => is_null($data['is_up']),
                                    ])></span>
                                    <a href="{{ route('sites.show', $data['site']) }}" class="font-medium text-zinc-100 transition hover:text-teal-300">{{ $data['site']->name }}</a>
                                    @if ($data['site']->project_type)
                                        <span class="inline-flex shrink-0 rounded-md border border-zinc-700/80 bg-zinc-900/80 px-2 py-0.5 text-[10px] font-medium text-zinc-300">{{ $data['site']->project_type_label }}</span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-3 py-2.5">
                                @if ($data['is_up'] === true)
                                    <span class="inline-flex items-center gap-1 rounded-md bg-emerald-500/15 px-2 py-0.5 text-xs font-medium text-emerald-300">
                                        <span class="size-1 rounded-full bg-emerald-400"></span>Online
                                    </span>
                                @elseif ($data['is_up'] === false)
                                    <span class="inline-flex items-center gap-1 rounded-md bg-red-500/15 px-2 py-0.5 text-xs font-medium text-red-300">
                                        <span class="size-1 rounded-full bg-red-400"></span>Down
                                    </span>
                                @else
                                    <span class="text-xs text-zinc-500">—</span>
                                @endif
                            </td>
                            <td class="px-3 py-2.5">
                                @if ($data['ssl_status'] === 'active')
                                    <span class="inline-flex rounded-md bg-emerald-500/15 px-2 py-0.5 text-xs font-medium text-emerald-300">OK</span>
                                @elseif ($data['ssl_status'] === 'pending')
                                    <span class="text-xs font-semibold text-zinc-100">Pending</span>
                                @else
                                    <span class="text-xs text-zinc-500">—</span>
                                @endif
                            </td>
                            <td class="hidden px-3 py-2.5 sm:table-cell">
                                <span class="text-xs tabular-nums text-zinc-300">{{ $data['uptime_percent'] !== null ? $data['uptime_percent'] . '%' : '—' }}</span>
                            </td>
                            <td class="hidden px-3 py-2.5 sm:table-cell">
                                <span class="font-mono text-xs tabular-nums text-zinc-300">{{ $data['response_time'] ?? '—' }}</span>
                            </td>
                            <td class="hidden px-3 py-2.5 md:table-cell">
                                <span class="text-xs tabular-nums text-zinc-300">{{ $data['pages_count'] }}</span>
                            </td>
                            <td class="px-3 py-2.5">
                                @if ($data['error_count'] > 0)
                                    <span class="text-xs font-medium tabular-nums text-red-400">{{ $data['error_count'] }}</span>
                                @else
                                    <span class="text-xs text-zinc-500">0</span>
                                @endif
                            </td>
                            <td class="px-5 py-2.5">
                                @if ($data['seo_issues'] > 0)
                                    <span class="text-xs font-medium tabular-nums text-amber-400">{{ $data['seo_issues'] }}</span>
                                @else
                                    <span class="text-xs text-zinc-500">0</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-5 py-8 text-center text-sm text-zinc-500">No sites yet</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
