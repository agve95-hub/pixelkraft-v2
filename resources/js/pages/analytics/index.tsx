import { Head } from '@inertiajs/react';
import AppLayout from '@/layouts/AppLayout';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

interface DataPoint { day: string; label: string; visitors: number; pageviews: number; }
interface SiteStat { site_id: string; site_name: string; visitors: number; pageviews: number; }
interface PageStat { url_path: string | null; title: string | null; site_name: string; visitors: number; }
interface Totals { visitors: number; pageviews: number; }

function SparkLine({ series, max }: { series: DataPoint[]; max: number }) {
    if (max === 0) return <div className="h-16 flex items-end text-xs text-zinc-600">No data</div>;

    const W = 800;
    const H = 64;
    const n = series.length;
    const pts = series.map((p, i) => {
        const x = (i / (n - 1)) * W;
        const y = H - (p.visitors / max) * H * 0.9;
        return `${x.toFixed(1)},${y.toFixed(1)}`;
    });
    const line = `M ${pts.join(' L ')}`;
    const area = `${line} L ${W},${H} L 0,${H} Z`;

    return (
        <svg viewBox={`0 0 ${W} ${H}`} className="w-full h-16" preserveAspectRatio="none">
            <defs>
                <linearGradient id="ag" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stopColor="#34d399" stopOpacity="0.25" />
                    <stop offset="100%" stopColor="#34d399" stopOpacity="0" />
                </linearGradient>
            </defs>
            <path d={area} fill="url(#ag)" />
            <path d={line} fill="none" stroke="#34d399" strokeWidth="1.5" />
        </svg>
    );
}

function StatCard({ label, value, sub }: { label: string; value: number; sub?: string }) {
    return (
        <div className="rounded-lg border border-zinc-800 bg-[#1e1e1e] px-4 py-3">
            <p className="text-xs text-zinc-500">{label}</p>
            <p className="text-2xl font-semibold tabular-nums text-zinc-100">{value.toLocaleString()}</p>
            {sub && <p className="text-xs text-zinc-600 mt-0.5">{sub}</p>}
        </div>
    );
}

export default function Analytics({
    series = [],
    totals30d = { visitors: 0, pageviews: 0 },
    totals7d = { visitors: 0, pageviews: 0 },
    bySite = [],
    topPages = [],
}: {
    series: DataPoint[];
    totals30d: Totals;
    totals7d: Totals;
    bySite: SiteStat[];
    topPages: PageStat[];
}) {
    const maxVisitors = Math.max(1, ...series.map((p) => p.visitors));
    const hasData = totals30d.visitors > 0;

    return (
        <AppLayout title="Analytics">
            <Head title="Analytics" />
            <div className="space-y-6">
                <h1 className="text-xl font-semibold text-zinc-100">Analytics</h1>

                {/* Summary stats */}
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <StatCard label="Visitors (30d)" value={totals30d.visitors} />
                    <StatCard label="Pageviews (30d)" value={totals30d.pageviews} />
                    <StatCard label="Visitors (7d)" value={totals7d.visitors} />
                    <StatCard label="Pageviews (7d)" value={totals7d.pageviews} />
                </div>

                {/* Traffic chart */}
                <Card className="border-zinc-800 bg-[#1e1e1e]">
                    <CardHeader className="pb-2">
                        <CardTitle className="text-sm font-medium text-zinc-300">Traffic — last 30 days</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {hasData ? (
                            <>
                                <SparkLine series={series} max={maxVisitors} />
                                <div className="mt-2 flex justify-between text-[10px] text-zinc-600">
                                    <span>{series[0]?.label}</span>
                                    <span>{series[series.length - 1]?.label}</span>
                                </div>
                            </>
                        ) : (
                            <p className="py-8 text-center text-sm text-zinc-500">No analytics data yet. Connect Google Analytics to your sites in site settings.</p>
                        )}
                    </CardContent>
                </Card>

                <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    {/* By site */}
                    <Card className="border-zinc-800 bg-[#1e1e1e]">
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-zinc-300">By site (30d)</CardTitle>
                        </CardHeader>
                        <CardContent className="p-0">
                            {bySite.length === 0 ? (
                                <p className="px-4 py-6 text-center text-sm text-zinc-500">No data.</p>
                            ) : (
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="border-b border-zinc-800">
                                            <th className="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-widest text-zinc-500">Site</th>
                                            <th className="px-4 py-2.5 text-right text-xs font-medium uppercase tracking-widest text-zinc-500">Visitors</th>
                                            <th className="px-4 py-2.5 text-right text-xs font-medium uppercase tracking-widest text-zinc-500">Pageviews</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {bySite.map((s) => (
                                            <tr key={s.site_id} className="border-b border-zinc-800/50 hover:bg-zinc-800/20">
                                                <td className="px-4 py-2.5 text-zinc-200">{s.site_name}</td>
                                                <td className="px-4 py-2.5 text-right tabular-nums text-zinc-300">{s.visitors.toLocaleString()}</td>
                                                <td className="px-4 py-2.5 text-right tabular-nums text-zinc-400">{s.pageviews.toLocaleString()}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            )}
                        </CardContent>
                    </Card>

                    {/* Top pages */}
                    <Card className="border-zinc-800 bg-[#1e1e1e]">
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-zinc-300">Top pages (30d)</CardTitle>
                        </CardHeader>
                        <CardContent className="p-0">
                            {topPages.length === 0 ? (
                                <p className="px-4 py-6 text-center text-sm text-zinc-500">No data.</p>
                            ) : (
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="border-b border-zinc-800">
                                            <th className="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-widest text-zinc-500">Page</th>
                                            <th className="px-4 py-2.5 text-right text-xs font-medium uppercase tracking-widest text-zinc-500">Visitors</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {topPages.map((p, i) => (
                                            <tr key={i} className="border-b border-zinc-800/50 hover:bg-zinc-800/20">
                                                <td className="px-4 py-2.5">
                                                    <p className="truncate text-xs text-zinc-200 max-w-xs">{p.url_path || '/'}</p>
                                                    <p className="text-[10px] text-zinc-500">{p.site_name}</p>
                                                </td>
                                                <td className="px-4 py-2.5 text-right tabular-nums text-zinc-300">{p.visitors.toLocaleString()}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
