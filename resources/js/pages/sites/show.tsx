import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/layouts/AppLayout';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import {
    AlertTriangle, Globe, Users, Zap, CheckCircle2, XCircle,
    Clock, FileText, ArrowRight, RefreshCw, TrendingUp, TrendingDown, Pencil,
} from 'lucide-react';
import { cn } from '@/lib/utils';

interface Site {
    id: string; name: string; domain: string | null; deploy_status: string | null;
    last_deployed_at: string | null; uptime_percent: string | null;
}
interface Page { id: string; url_path: string | null; title: string | null; visitors_30d: number; }
interface SeoIssue { id: string; severity: string; code: string | null; message: string; }
interface ErrorItem { id: string; message: string; count: number; last_seen_at: string; }

interface Props {
    site: Site;
    seoIssueCount: number;
    seoWarningCount: number;
    visitorsToday: number;
    visitorsTrendPercent: number | null;
    uptimePercent: number | null;
    latestResponseMs: number | null;
    p95ResponseMs: number | null;
    errorCount: number;
    errorItems: ErrorItem[];
    seoIssues: SeoIssue[];
    pages: Page[];
}

function StatCard({ label, value, sub, icon: Icon, href, color = 'zinc' }: {
    label: string; value: string | number; sub?: string; icon: React.ElementType;
    href?: string; color?: 'zinc' | 'emerald' | 'amber' | 'red';
}) {
    const colors = {
        zinc: 'text-zinc-400',
        emerald: 'text-emerald-400',
        amber: 'text-amber-400',
        red: 'text-red-400',
    };
    const inner = (
        <Card className={cn('border-zinc-800 bg-[#1e1e1e]', href && 'hover:border-zinc-700 transition-colors cursor-pointer')}>
            <CardContent className="pt-4 pb-4 px-4">
                <div className="flex items-start justify-between gap-2">
                    <div>
                        <p className="text-xs font-medium uppercase tracking-widest text-zinc-500">{label}</p>
                        <p className="mt-1 text-2xl font-semibold tabular-nums text-zinc-100">{value}</p>
                        {sub && <p className="mt-0.5 text-xs text-zinc-500">{sub}</p>}
                    </div>
                    <Icon className={cn('h-5 w-5 mt-0.5 shrink-0', colors[color])} />
                </div>
            </CardContent>
        </Card>
    );
    return href ? <Link href={href}>{inner}</Link> : inner;
}

function deployStatusLabel(status: string | null) {
    if (status === 'live') return { label: 'Live', color: 'emerald' as const };
    if (status === 'deploying') return { label: 'Deploying', color: 'amber' as const };
    if (status === 'queued') return { label: 'Queued', color: 'amber' as const };
    if (status === 'failed') return { label: 'Failed', color: 'red' as const };
    return { label: 'Not deployed', color: 'zinc' as const };
}

export default function SiteShow({
    site, seoIssueCount, seoWarningCount, visitorsToday, visitorsTrendPercent,
    uptimePercent, latestResponseMs, p95ResponseMs, errorCount, errorItems, seoIssues, pages,
}: Props) {
    const deploy = () => router.post(`/dashboard/sites/${site.id}/deploy`);
    const { label: statusLabel, color: statusColor } = deployStatusLabel(site.deploy_status);
    const TrendIcon = visitorsTrendPercent !== null && visitorsTrendPercent >= 0 ? TrendingUp : TrendingDown;

    return (
        <AppLayout title={site.name}>
            <Head title={site.name} />
            <div className="space-y-6">
                {/* Header */}
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold text-zinc-100">{site.name}</h1>
                        {site.domain && (
                            <a href={`https://${site.domain}`} target="_blank" rel="noopener noreferrer"
                                className="mt-0.5 flex items-center gap-1 text-sm text-zinc-400 hover:text-zinc-200">
                                <Globe className="h-3 w-3" />{site.domain}
                            </a>
                        )}
                    </div>
                    <div className="flex items-center gap-2">
                        <Badge variant={statusColor === 'emerald' ? 'success' : statusColor === 'amber' ? 'warning' : statusColor === 'red' ? 'destructive' : 'secondary'}>
                            {statusLabel}
                        </Badge>
                        {site.last_deployed_at && (
                            <span className="text-xs text-zinc-500">
                                {new Date(site.last_deployed_at).toLocaleString()}
                            </span>
                        )}
                        <Button size="sm" onClick={deploy} disabled={site.deploy_status === 'deploying'}>
                            <RefreshCw className={cn('mr-1.5 h-3.5 w-3.5', site.deploy_status === 'deploying' && 'animate-spin')} />
                            Deploy
                        </Button>
                    </div>
                </div>

                {/* Stats grid */}
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <StatCard
                        label="Visitors today" value={visitorsToday}
                        sub={visitorsTrendPercent !== null ? `${visitorsTrendPercent > 0 ? '+' : ''}${visitorsTrendPercent}% vs last week` : undefined}
                        icon={TrendIcon}
                        color={visitorsTrendPercent !== null && visitorsTrendPercent >= 0 ? 'emerald' : 'amber'}
                    />
                    <StatCard
                        label="Uptime" value={uptimePercent !== null ? `${uptimePercent}%` : '—'}
                        sub={latestResponseMs !== null ? `${latestResponseMs}ms` : undefined}
                        icon={CheckCircle2}
                        color={uptimePercent !== null && uptimePercent < 99 ? 'amber' : 'emerald'}
                    />
                    <StatCard
                        label="Errors" value={errorCount} icon={XCircle}
                        color={errorCount > 0 ? 'red' : 'zinc'}
                    />
                    <StatCard
                        label="SEO issues" value={seoIssueCount}
                        sub={seoWarningCount > 0 ? `${seoWarningCount} warning${seoWarningCount !== 1 ? 's' : ''}` : undefined}
                        icon={AlertTriangle}
                        color={seoIssueCount > 0 ? 'red' : seoWarningCount > 0 ? 'amber' : 'zinc'}
                    />
                </div>

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    {/* Pages */}
                    <Card className="border-zinc-800 bg-[#1e1e1e]">
                        <CardHeader className="pb-2">
                            <div className="flex items-center justify-between">
                                <CardTitle className="text-sm font-medium text-zinc-300">Top pages (30d)</CardTitle>
                                <Link href={`/dashboard/sites/${site.id}/pages`} className="text-xs text-zinc-500 hover:text-zinc-300 flex items-center gap-1">
                                    All pages <ArrowRight className="h-3 w-3" />
                                </Link>
                            </div>
                        </CardHeader>
                        <CardContent className="p-0">
                            {pages.length === 0 ? (
                                <p className="px-4 py-6 text-sm text-zinc-500">No pages tracked yet.</p>
                            ) : pages.slice(0, 8).map((p) => (
                                <div key={p.id} className="flex items-center gap-3 border-b border-zinc-800/60 px-4 py-2.5 last:border-0">
                                    <FileText className="h-3.5 w-3.5 shrink-0 text-zinc-600" />
                                    <span className="flex-1 truncate font-mono text-xs text-zinc-300">{p.url_path || '/'}</span>
                                    <span className="tabular-nums text-xs text-zinc-500">{p.visitors_30d.toLocaleString()}</span>
                                    <Link href={`/dashboard/sites/${site.id}/pages/${p.id}/edit`}
                                        className="flex items-center gap-1 rounded px-1.5 py-0.5 text-[10px] text-zinc-500 hover:bg-zinc-700 hover:text-zinc-200 transition-colors">
                                        <Pencil className="h-3 w-3" />Edit
                                    </Link>
                                </div>
                            ))}
                        </CardContent>
                    </Card>

                    {/* SEO issues */}
                    <Card className="border-zinc-800 bg-[#1e1e1e]">
                        <CardHeader className="pb-2">
                            <div className="flex items-center justify-between">
                                <CardTitle className="text-sm font-medium text-zinc-300">Open SEO issues</CardTitle>
                                {seoIssueCount > 0 && (
                                    <span className="text-xs text-zinc-500">{seoIssueCount} open</span>
                                )}
                            </div>
                        </CardHeader>
                        <CardContent className="p-0">
                            {seoIssues.length === 0 ? (
                                <div className="flex items-center gap-2 px-4 py-6">
                                    <CheckCircle2 className="h-4 w-4 text-emerald-400" />
                                    <p className="text-sm text-zinc-500">No open issues.</p>
                                </div>
                            ) : seoIssues.slice(0, 8).map((issue, i) => (
                                <div key={issue.id ?? i} className="flex items-start gap-3 border-b border-zinc-800/60 px-4 py-2.5 last:border-0">
                                    <AlertTriangle className={cn('mt-0.5 h-3.5 w-3.5 shrink-0', issue.severity === 'error' ? 'text-red-400' : 'text-amber-400')} />
                                    <div className="flex-1 min-w-0">
                                        <p className="text-xs text-zinc-200">{issue.message}</p>
                                        {issue.code && <p className="mt-0.5 font-mono text-[10px] text-zinc-600">{issue.code}</p>}
                                    </div>
                                </div>
                            ))}
                        </CardContent>
                    </Card>

                    {/* Recent errors */}
                    {errorItems.length > 0 && (
                        <Card className="border-red-500/20 bg-[#1e1e1e] lg:col-span-2">
                            <CardHeader className="pb-2">
                                <CardTitle className="text-sm font-medium text-red-400">Recent errors</CardTitle>
                            </CardHeader>
                            <CardContent className="p-0">
                                {errorItems.slice(0, 5).map((err) => (
                                    <div key={err.id} className="flex items-center gap-4 border-b border-zinc-800/60 px-4 py-2.5 last:border-0">
                                        <XCircle className="h-3.5 w-3.5 shrink-0 text-red-400" />
                                        <p className="flex-1 truncate text-xs text-zinc-300">{err.message}</p>
                                        <Tooltip>
                                            <TooltipTrigger asChild>
                                                <span className="tabular-nums text-xs text-zinc-500 flex items-center gap-1">
                                                    <Clock className="h-3 w-3" />{new Date(err.last_seen_at).toLocaleDateString()}
                                                </span>
                                            </TooltipTrigger>
                                            <TooltipContent>{err.count}× occurrence{err.count !== 1 ? 's' : ''}</TooltipContent>
                                        </Tooltip>
                                        <Badge variant="destructive" className="text-[10px] px-1.5 py-0">{err.count}</Badge>
                                    </div>
                                ))}
                            </CardContent>
                        </Card>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
