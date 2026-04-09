<div>
    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-5">
        <div class="flex items-center gap-2 mb-4">
            <flux:icon name="server-stack" class="size-4 text-zinc-400" />
            <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Site health</h3>
        </div>

        <div class="overflow-x-auto -mx-5">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-zinc-200 dark:border-zinc-700">
                        <th class="px-5 py-2 text-left text-xs font-medium text-zinc-500 dark:text-zinc-400">Site</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-zinc-500 dark:text-zinc-400">Status</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-zinc-500 dark:text-zinc-400">SSL</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-zinc-500 dark:text-zinc-400 hidden sm:table-cell">Uptime</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-zinc-500 dark:text-zinc-400 hidden sm:table-cell">Response</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-zinc-500 dark:text-zinc-400 hidden md:table-cell">Pages</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-zinc-500 dark:text-zinc-400">Errors</th>
                        <th class="px-5 py-2 text-left text-xs font-medium text-zinc-500 dark:text-zinc-400">SEO</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($sites as $data)
                        <tr class="hover:bg-zinc-50 dark:hover:bg-white/5 transition">
                            <td class="px-5 py-2.5">
                                <div class="flex items-center gap-2">
                                    <span @class([
                                        'size-2 rounded-full shrink-0',
                                        'bg-lime-500' => $data['is_up'] === true,
                                        'bg-red-500' => $data['is_up'] === false,
                                        'bg-zinc-400' => is_null($data['is_up']),
                                    ])></span>
                                    <a href="{{ route('sites.show', $data['site']) }}" class="font-medium text-zinc-900 dark:text-zinc-100 hover:text-violet-500 dark:hover:text-violet-400 transition">{{ $data['site']->name }}</a>
                                    @if ($data['site']->project_type)
                                        <span class="text-[10px] text-zinc-400 dark:text-zinc-500">{{ ucfirst($data['site']->project_type) }}</span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-3 py-2.5">
                                @if ($data['is_up'] === true)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-500/10 px-2 py-0.5 text-xs font-medium text-emerald-600 dark:text-emerald-400">
                                        <span class="size-1 rounded-full bg-emerald-500"></span> Online
                                    </span>
                                @elseif ($data['is_up'] === false)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-red-500/10 px-2 py-0.5 text-xs font-medium text-red-600 dark:text-red-400">
                                        <span class="size-1 rounded-full bg-red-500"></span> Down
                                    </span>
                                @else
                                    <span class="text-xs text-zinc-400">—</span>
                                @endif
                            </td>
                            <td class="px-3 py-2.5">
                                @if ($data['ssl_status'] === 'active')
                                    <span class="inline-flex rounded-full bg-emerald-500/10 px-2 py-0.5 text-xs font-medium text-emerald-600 dark:text-emerald-400">OK</span>
                                @elseif ($data['ssl_status'] === 'pending')
                                    <span class="inline-flex rounded-full bg-amber-500/10 px-2 py-0.5 text-xs font-medium text-amber-600 dark:text-amber-400">Pending</span>
                                @else
                                    <span class="text-xs text-zinc-400">—</span>
                                @endif
                            </td>
                            <td class="px-3 py-2.5 hidden sm:table-cell">
                                <span class="text-xs tabular-nums text-zinc-700 dark:text-zinc-300">{{ $data['uptime_percent'] !== null ? $data['uptime_percent'] . '%' : '—' }}</span>
                            </td>
                            <td class="px-3 py-2.5 hidden sm:table-cell">
                                <span class="text-xs tabular-nums text-zinc-700 dark:text-zinc-300 font-mono">{{ $data['response_time'] ?? '—' }}</span>
                            </td>
                            <td class="px-3 py-2.5 hidden md:table-cell">
                                <span class="text-xs tabular-nums text-zinc-700 dark:text-zinc-300">{{ $data['pages_count'] }}</span>
                            </td>
                            <td class="px-3 py-2.5">
                                @if ($data['error_count'] > 0)
                                    <span class="text-xs font-medium tabular-nums text-red-500">{{ $data['error_count'] }}</span>
                                @else
                                    <span class="text-xs text-zinc-400">0</span>
                                @endif
                            </td>
                            <td class="px-5 py-2.5">
                                @if ($data['seo_issues'] > 0)
                                    <span class="text-xs font-medium tabular-nums text-amber-500">{{ $data['seo_issues'] }}</span>
                                @else
                                    <span class="text-xs text-zinc-400">0</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-5 py-8 text-center text-sm text-zinc-400">No sites yet</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
