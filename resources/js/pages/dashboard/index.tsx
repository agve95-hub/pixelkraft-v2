import { Head, Link, usePage } from '@inertiajs/react';
import AppLayout from '@/layouts/AppLayout';
import { Button } from '@/components/ui/button';
import type { PageProps } from '@/types';

interface TrafficPoint {
    day: string;
    label: string;
    visitors: number;
}

interface SiteInsight {
    site: { id: number; name: string };
    uptime_percent: number;
    daily_bars: ('up' | 'degraded' | 'down' | 'unknown')[];
    response_series: number[];
    avg_response: number;
    p95_response: number;
}

interface DashboardProps {
    totalSites: number;
    totalPages: number;
    uptimePercent: number;
    unreadMessages: number;
    errorCount: number;
    seoIssueCount: number;
    sitesDown: number;
    trafficVisitors: number;
    trafficSeries: TrafficPoint[];
    lineD: string;
    areaD: string;
    vbW: number;
    vbH: number;
    pad: number;
    siteInsights: SiteInsight[];
}

function StatCard({ label, value, sub, subColor }: { label: string; value: string | number; sub?: string; subColor?: string }) {
    return (
        <div className="rounded-xl border border-zinc-800/80 bg-[#1e1e1e] px-4 py-3">
            <p className="text-[11px] font-medium uppercase tracking-[0.14em] text-zinc-500">{label}</p>
            <p className="mt-1 text-2xl font-semibold tabular-nums text-zinc-100">{value}</p>
            {sub && <p className={`mt-0.5 text-xs ${subColor ?? 'text-zinc-500'}`}>{sub}</p>}
        </div>
    );
}

function UptimeBars({ bars }: { bars: SiteInsight['daily_bars'] }) {
    const colors = {
        up: 'bg-emerald-400',
        degraded: 'bg-amber-400',
        down: 'bg-red-400',
        unknown: 'bg-zinc-700',
    };
    return (
        <div className="flex h-12 items-end gap-px">
            {bars.map((bar, i) => (
                <span key={i} className={`min-h-[6px] flex-1 rounded-sm ${colors[bar]}`} />
            ))}
        </div>
    );
}

export default function Dashboard({
    totalSites, totalPages, uptimePercent, unreadMessages, errorCount,
    seoIssueCount, sitesDown, trafficVisitors, trafficSeries,
    lineD, areaD, vbW, vbH, pad, siteInsights,
}: DashboardProps) {
    const { auth } = usePage<PageProps>().props;
    const user = auth.user;

    const hour = new Date().getHours();
    const greeting = hour < 12 ? 'Good morning' : hour < 17 ? 'Good afternoon' : 'Good evening';

    const today = new Date().toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });

    const firstLabel = trafficSeries[0]?.label ?? '';
    const lastLabel = trafficSeries[trafficSeries.length - 1]?.label ?? '';

    return (
        <AppLayout title="Dashboard">
            <Head title="Dashboard" />

            <div className="space-y-5 text-zinc-100">
                {/* Header */}
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight text-zinc-100">
                            {greeting}{user ? `, ${user.name}` : ''}
                        </h1>
                        <p className="mt-1 text-sm text-zinc-400">{today}</p>
                    </div>
                    <Button variant="outline" size="sm" asChild className="border-zinc-700 bg-zinc-900/70 text-zinc-200 hover:bg-zinc-800">
                        <Link href="/dashboard/sites">View all sites</Link>
                    </Button>
                </div>

                {/* Stats grid */}
                <div className="grid grid-cols-2 gap-3 md:grid-cols-3">
                    <StatCard
                        label="Sites"
                        value={totalSites}
                        sub={sitesDown > 0 ? `${sitesDown} down` : undefined}
                        subColor={sitesDown > 0 ? 'text-red-400' : 'text-emerald-400'}
                    />
                    <StatCard
                        label="Uptime"
                        value={`${uptimePercent.toFixed(1)}%`}
                    />
                    <StatCard label="Pages" value={totalPages} />
                    <StatCard
                        label="Messages"
                        value={unreadMessages}
                        sub={unreadMessages > 0 ? 'Unread' : undefined}
                        subColor="text-sky-400"
                    />
                    <StatCard
                        label="Errors"
                        value={errorCount}
                        sub={errorCount > 0 ? 'Needs attention' : undefined}
                        subColor="text-red-400"
                    />
                    <StatCard
                        label="SEO Issues"
                        value={seoIssueCount}
                        sub={seoIssueCount > 0 ? 'Needs attention' : undefined}
                        subColor="text-amber-400"
                    />
                </div>

                {/* Traffic chart */}
                <section className="rounded-xl border border-zinc-800/80 bg-[#1e1e1e] p-4 md:p-5">
                    <div className="mb-3 flex items-start justify-between gap-4">
                        <div>
                            <p className="text-sm text-zinc-400">Traffic — All sites</p>
                            <p className="text-[11px] text-zinc-500">Last 30 days</p>
                        </div>
                        <div className="text-right">
                            <p className="text-xl font-semibold tabular-nums text-zinc-100">
                                {trafficVisitors.toLocaleString()}{' '}
                                <span className="text-sm font-normal text-zinc-500">visitors</span>
                            </p>
                            <p className="text-[11px] text-zinc-500">{firstLabel} - {lastLabel}</p>
                        </div>
                    </div>
                    <div className="rounded-lg border border-zinc-800/90 bg-[#141414] p-3">
                        <svg className="h-52 w-full" viewBox={`0 0 ${vbW} ${vbH}`} preserveAspectRatio="none" role="img" aria-label="Traffic trend">
                            {[0, 1, 2, 3].map((line) => (
                                <line key={line} x1={pad} y1={pad + (line / 3) * (vbH - pad * 2)} x2={vbW - pad} y2={pad + (line / 3) * (vbH - pad * 2)} stroke="rgb(39 39 42)" strokeWidth="1" vectorEffect="non-scaling-stroke" />
                            ))}
                            <defs>
                                <linearGradient id="trafficFill" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0%" stopColor="rgb(45 212 191)" stopOpacity="0.42" />
                                    <stop offset="100%" stopColor="rgb(45 212 191)" stopOpacity="0" />
                                </linearGradient>
                            </defs>
                            <path d={areaD} fill="url(#trafficFill)" />
                            <path d={lineD} fill="none" stroke="rgb(45 212 191)" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round" vectorEffect="non-scaling-stroke" />
                        </svg>
                        <div className="mt-2 flex justify-between px-1 text-[10px] text-zinc-500">
                            <span>{firstLabel}</span>
                            <span>{lastLabel}</span>
                        </div>
                    </div>
                </section>

                {/* Site insights */}
                {siteInsights.length > 0 && (
                    <div className="space-y-5">
                        {siteInsights.map((insight) => (
                            <div key={insight.site.id} className="space-y-4">
                                <section className="rounded-xl border border-zinc-800/80 bg-[#1e1e1e] p-4">
                                    <div className="mb-3 flex items-start justify-between gap-3">
                                        <p className="text-sm text-zinc-300">{insight.site.name} — Uptime</p>
                                        <p className={`text-sm font-semibold tabular-nums ${insight.uptime_percent >= 99.8 ? 'text-emerald-400' : insight.uptime_percent >= 99 ? 'text-amber-400' : 'text-red-400'}`}>
                                            {insight.uptime_percent.toFixed(1)}%
                                        </p>
                                    </div>
                                    <UptimeBars bars={insight.daily_bars} />
                                    <div className="mt-2 flex gap-4 text-[10px] text-zinc-500">
                                        <span className="inline-flex items-center gap-1"><span className="size-2 rounded-[2px] bg-emerald-400" />Up</span>
                                        <span className="inline-flex items-center gap-1"><span className="size-2 rounded-[2px] bg-amber-400" />Degraded</span>
                                        <span className="inline-flex items-center gap-1"><span className="size-2 rounded-[2px] bg-red-400" />Down</span>
                                    </div>
                                </section>
                                <section className="rounded-xl border border-zinc-800/80 bg-[#1e1e1e] p-4">
                                    <div className="mb-3 flex items-start justify-between gap-3">
                                        <p className="text-sm text-zinc-300">{insight.site.name} — Response time</p>
                                        <p className="text-[11px] text-zinc-500 tabular-nums">
                                            avg {insight.avg_response}ms &nbsp; p95 {insight.p95_response}ms
                                        </p>
                                    </div>
                                </section>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
