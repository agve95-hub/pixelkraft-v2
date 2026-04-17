import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/layouts/AppLayout';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Separator } from '@/components/ui/separator';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import {
    Plus, Search, Globe, ExternalLink, Settings, Rocket,
    ChevronRight, Clock, Layers, RefreshCw,
} from 'lucide-react';

interface UptimeCheck {
    is_up: boolean;
    response_time_ms: number | null;
}

interface DeployLog {
    id: string;
    status: string;
    created_at: string;
}

interface Site {
    id: string;
    name: string;
    slug: string;
    domain: string | null;
    repo_url: string | null;
    project_type: string | null;
    deploy_status: string | null;
    last_deployed_at: string | null;
    last_synced_at: string | null;
    pages_count: number;
    latest_deploy: DeployLog | null;
    latest_uptime_check: UptimeCheck | null;
}

function deployStatusBadge(status: string | null) {
    switch (status) {
        case 'live': return <Badge variant="success">Live</Badge>;
        case 'deploying': return <Badge variant="warning">Deploying</Badge>;
        case 'queued': return <Badge variant="warning">Queued</Badge>;
        case 'failed': return <Badge variant="destructive">Failed</Badge>;
        default: return <Badge variant="secondary">{status ?? 'Unknown'}</Badge>;
    }
}

function deployStatusDot(status: string | null) {
    switch (status) {
        case 'live': return 'bg-emerald-400';
        case 'deploying':
        case 'queued': return 'bg-amber-400 animate-pulse';
        case 'failed': return 'bg-red-500';
        default: return 'bg-zinc-500';
    }
}

function uptimeStatusBadge(check: UptimeCheck | null) {
    if (!check) return <Badge variant="secondary">No data</Badge>;
    if (check.is_up) return <Badge variant="success">Up</Badge>;
    return <Badge variant="destructive">Down</Badge>;
}

interface SiteRowProps {
    site: Site;
    selected: boolean;
    onSelect: () => void;
}

function SiteRow({ site, selected, onSelect }: SiteRowProps) {
    return (
        <tr
            onClick={onSelect}
            className={`cursor-pointer border-b border-zinc-800 transition-colors hover:bg-zinc-800/50 ${selected ? 'bg-zinc-800/70' : ''}`}
        >
            <td className="px-4 py-3">
                <div className="flex items-center gap-2.5">
                    <span className={`size-2 rounded-full shrink-0 ${deployStatusDot(site.deploy_status)}`} />
                    <span className="font-medium text-zinc-100">{site.name}</span>
                </div>
                {site.domain && (
                    <p className="ml-4.5 mt-0.5 text-xs text-zinc-500">{site.domain}</p>
                )}
            </td>
            <td className="px-4 py-3 text-sm">{deployStatusBadge(site.deploy_status)}</td>
            <td className="px-4 py-3 text-sm">{uptimeStatusBadge(site.latest_uptime_check)}</td>
            <td className="px-4 py-3 text-sm text-zinc-400">
                {site.latest_uptime_check?.response_time_ms
                    ? `${site.latest_uptime_check.response_time_ms}ms`
                    : '—'}
            </td>
            <td className="px-4 py-3 text-sm text-zinc-400">
                <span className="inline-flex items-center gap-1">
                    <Layers className="h-3 w-3" />
                    {site.pages_count}
                </span>
            </td>
            <td className="px-4 py-3 text-sm text-zinc-500">
                {site.last_deployed_at
                    ? new Date(site.last_deployed_at).toLocaleDateString()
                    : '—'}
            </td>
            <td className="px-4 py-3">
                <div className="flex items-center gap-1" onClick={(e) => e.stopPropagation()}>
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <Button variant="ghost" size="icon" className="h-7 w-7" asChild>
                                <Link href={`/dashboard/sites/${site.id}`}><ExternalLink className="h-3.5 w-3.5" /></Link>
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent>View site</TooltipContent>
                    </Tooltip>
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <Button variant="ghost" size="icon" className="h-7 w-7" asChild>
                                <Link href={`/dashboard/sites/${site.id}/settings`}><Settings className="h-3.5 w-3.5" /></Link>
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent>Settings</TooltipContent>
                    </Tooltip>
                </div>
            </td>
        </tr>
    );
}

export default function SitesIndex({ sites }: { sites: Site[] }) {
    const [search, setSearch] = useState('');
    const [selectedSiteId, setSelectedSiteId] = useState<string | null>(sites[0]?.id ?? null);
    const [deploying, setDeploying] = useState(false);

    const filtered = sites.filter((s) =>
        s.name.toLowerCase().includes(search.toLowerCase()) ||
        (s.domain ?? '').toLowerCase().includes(search.toLowerCase()),
    );

    const selectedSite = sites.find((s) => s.id === selectedSiteId) ?? null;

    function handleDeploy() {
        if (!selectedSite) return;
        setDeploying(true);
        router.post(`/dashboard/sites/${selectedSite.id}/deploy`, {}, {
            onFinish: () => setDeploying(false),
        });
    }

    return (
        <AppLayout title="Sites">
            <Head title="Sites" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-semibold text-zinc-100">Sites</h1>
                        <p className="text-sm text-zinc-400">Add, connect, and deploy from one workspace.</p>
                    </div>
                    <Button asChild className="bg-emerald-500 text-zinc-950 hover:bg-emerald-400 shrink-0">
                        <Link href="/dashboard/sites/create"><Plus className="mr-2 h-4 w-4" />Add new site</Link>
                    </Button>
                </div>

                {/* Sites table */}
                <Card className="border-zinc-800 bg-[#1e1e1e]">
                    <CardHeader className="pb-3">
                        <CardTitle className="text-base text-zinc-100">Your sites</CardTitle>
                        <CardDescription>Click a site row to see deploy controls below.</CardDescription>
                        <div className="mt-2 relative">
                            <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-500" />
                            <Input
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Filter sites…"
                                className="pl-9 border-zinc-700 bg-zinc-900 text-zinc-100 placeholder:text-zinc-600"
                            />
                        </div>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b border-zinc-800 text-left">
                                        <th className="px-4 py-2.5 text-xs font-medium uppercase tracking-widest text-zinc-500">Site</th>
                                        <th className="px-4 py-2.5 text-xs font-medium uppercase tracking-widest text-zinc-500">Status</th>
                                        <th className="px-4 py-2.5 text-xs font-medium uppercase tracking-widest text-zinc-500">Uptime</th>
                                        <th className="px-4 py-2.5 text-xs font-medium uppercase tracking-widest text-zinc-500">Response</th>
                                        <th className="px-4 py-2.5 text-xs font-medium uppercase tracking-widest text-zinc-500">Pages</th>
                                        <th className="px-4 py-2.5 text-xs font-medium uppercase tracking-widest text-zinc-500">Last deploy</th>
                                        <th className="px-4 py-2.5 text-xs font-medium uppercase tracking-widest text-zinc-500">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {filtered.length === 0 ? (
                                        <tr>
                                            <td colSpan={7} className="px-4 py-8 text-center text-sm text-zinc-500">
                                                {sites.length === 0 ? 'No sites yet — create one above.' : 'No sites match your search.'}
                                            </td>
                                        </tr>
                                    ) : (
                                        filtered.map((site) => (
                                            <SiteRow
                                                key={site.id}
                                                site={site}
                                                selected={selectedSiteId === site.id}
                                                onSelect={() => setSelectedSiteId(site.id)}
                                            />
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>

                {/* Selected site deploy controls */}
                {selectedSite ? (
                    <Card className="border-zinc-800 bg-[#1e1e1e]">
                        <CardHeader className="pb-3">
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <CardTitle className="text-base text-zinc-100">{selectedSite.name}</CardTitle>
                                    {selectedSite.repo_url && (
                                        <p className="mt-1 font-mono text-xs text-zinc-500">{selectedSite.repo_url}</p>
                                    )}
                                </div>
                                <div className="flex items-center gap-2">
                                    <Button variant="outline" size="sm" className="border-zinc-700 text-zinc-200" asChild>
                                        <Link href={`/dashboard/sites/${selectedSite.id}`}>Full details</Link>
                                    </Button>
                                    <Button variant="outline" size="sm" className="border-zinc-700 text-zinc-200" asChild>
                                        <Link href={`/dashboard/sites/${selectedSite.id}/settings`}>
                                            <Settings className="mr-1.5 h-3.5 w-3.5" />Settings
                                        </Link>
                                    </Button>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
                                <div className="rounded-lg border border-zinc-800 bg-zinc-900/50 px-3 py-2.5">
                                    <p className="text-xs text-zinc-500">Pages</p>
                                    <p className="mt-1 font-mono text-lg font-semibold text-zinc-100">{selectedSite.pages_count}</p>
                                </div>
                                <div className="rounded-lg border border-zinc-800 bg-zinc-900/50 px-3 py-2.5">
                                    <p className="text-xs text-zinc-500">Type</p>
                                    <Badge variant="secondary" className="mt-1.5">{selectedSite.project_type ?? 'unknown'}</Badge>
                                </div>
                                <div className="rounded-lg border border-zinc-800 bg-zinc-900/50 px-3 py-2.5">
                                    <p className="text-xs text-zinc-500">Last deploy</p>
                                    <p className="mt-1 text-sm text-zinc-300">
                                        {selectedSite.last_deployed_at
                                            ? new Date(selectedSite.last_deployed_at).toLocaleString()
                                            : 'Never'}
                                    </p>
                                </div>
                                <div className="rounded-lg border border-zinc-800 bg-zinc-900/50 px-3 py-2.5">
                                    <p className="text-xs text-zinc-500">Last sync</p>
                                    <p className="mt-1 text-sm text-zinc-300">
                                        {selectedSite.last_synced_at
                                            ? new Date(selectedSite.last_synced_at).toLocaleString()
                                            : 'Never'}
                                    </p>
                                </div>
                            </div>

                            <Separator className="my-4 bg-zinc-800" />

                            <div className="flex flex-wrap gap-2">
                                <Button
                                    onClick={handleDeploy}
                                    disabled={deploying || selectedSite.deploy_status === 'deploying'}
                                    className="bg-emerald-500 text-zinc-950 hover:bg-emerald-400"
                                >
                                    {deploying ? (
                                        <><RefreshCw className="mr-2 h-4 w-4 animate-spin" />Deploying…</>
                                    ) : (
                                        <><Rocket className="mr-2 h-4 w-4" />Deploy now</>
                                    )}
                                </Button>
                                <Button variant="outline" className="border-zinc-700 text-zinc-200" asChild>
                                    <Link href={`/dashboard/sites/${selectedSite.id}`}>
                                        <ChevronRight className="mr-1.5 h-4 w-4" />View dashboard
                                    </Link>
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                ) : (
                    <Card className="border-zinc-800 bg-[#1e1e1e]">
                        <CardContent className="pt-6">
                            <p className="text-sm text-zinc-500">No sites yet — use <strong className="text-zinc-300">Add new site</strong> above to create one.</p>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
