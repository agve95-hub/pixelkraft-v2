<div>
    <div class="dash-card !p-0">
        <div class="dash-card-head px-[18px] pt-4 pb-3">
            <p class="dash-card-title">
                <flux:icon name="calendar" class="size-4" />
                Site health
            </p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr>
                        <th class="pl-[18px]">Site</th>
                        <th>Status</th>
                        <th>SSL</th>
                        <th class="hidden sm:table-cell">Uptime</th>
                        <th class="hidden sm:table-cell">Response</th>
                        <th class="hidden md:table-cell">Pages</th>
                        <th>Errors</th>
                        <th class="pr-[18px]">SEO</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($sites as $data)
                        <tr class="clickable">
                            <td class="pl-[18px]">
                                <div class="site-name">
                                    <span @class([
                                        'site-dot',
                                        'site-dot-live' => $data['is_up'] === true,
                                        'bg-red-400' => $data['is_up'] === false,
                                        'bg-zinc-500' => is_null($data['is_up']),
                                    ])></span>
                                    <a href="{{ route('sites.show', $data['site']) }}" class="hover:text-emerald-400 transition-colors">{{ $data['site']->name }}</a>
                                    @if ($data['site']->project_type)
                                        <span class="tag">{{ $data['site']->project_type_label }}</span>
                                    @endif
                                </div>
                            </td>
                            <td>
                                @if ($data['is_up'] === true)
                                    <span class="pill pill-green pill-no-dot">Online</span>
                                @elseif ($data['is_up'] === false)
                                    <span class="pill pill-red pill-no-dot">Down</span>
                                @else
                                    <span class="text-zinc-500">—</span>
                                @endif
                            </td>
                            <td>
                                @if ($data['ssl_status'] === 'active')
                                    <span class="pill pill-green pill-no-dot">OK</span>
                                @elseif ($data['ssl_status'] === 'pending')
                                    <span class="pill pill-yellow pill-no-dot">Pending</span>
                                @else
                                    <span class="text-zinc-500">—</span>
                                @endif
                            </td>
                            <td class="hidden sm:table-cell font-mono tabular-nums">{{ $data['uptime_percent'] !== null ? $data['uptime_percent'] . '%' : '—' }}</td>
                            <td class="hidden sm:table-cell font-mono tabular-nums">{{ $data['response_time'] ?? '—' }}</td>
                            <td class="hidden md:table-cell tabular-nums">{{ $data['pages_count'] }}</td>
                            <td class="{{ $data['error_count'] > 0 ? 'text-red-400' : '' }} tabular-nums">{{ $data['error_count'] }}</td>
                            <td class="pr-[18px] {{ $data['seo_issues'] > 0 ? 'text-amber-400' : '' }} tabular-nums">{{ $data['seo_issues'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-8 text-center text-zinc-500">No sites yet</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
