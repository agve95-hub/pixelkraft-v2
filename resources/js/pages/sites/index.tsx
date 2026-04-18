import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/layouts/AppLayout';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import {
    Plus, Search, ExternalLink, Settings, Rocket, Layers, RefreshCw,
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
    deploying: boolean;
    onDeploy: (siteId: string) => void;
}

function SiteRow({ site, deploying, onDeploy }: SiteRowProps) {
    const openSite = () => router.visit(`/dashboard/sites/${site.id}`);

    return (
        <tr
            onClick={openSite}
            onKeyDown={(event) => {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    openSite();
                }
            }}
            tabIndex={0}
            role="link"
            className="cursor-pointer border-b border-zinc-800 transition-colors hover:bg-zinc-800/50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400/60"
        >
            <td className="px-4 py-3">
                <div className="flex items-center gap-2.5">
                    <span className={`size-2 shrink-0 rounded-full ${deployStatusDot(site.deploy_status)}`} />
                    <Link
                        href={`/dashboard/sites/${site.id}`}
                        onClick={(event) => event.stopPropagation()}
                        className="font-medium text-zinc-100 hover:text-emerald-300"
                    >
                        {site.name}
                    </Link>
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
                    : '-'}
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
                    : '-'}
            </td>
            <td className="px-4 py-3">
                <div className="flex items-center gap-1" onClick={(event) => event.stopPropagation()}>
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
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <Button
                                variant="ghost"
                                size="icon"
                                className="h-7 w-7"
                                disabled={deploying || site.deploy_status === 'deploying'}
                                onClick={() => onDeploy(site.id)}
                            >
                                {deploying ? <RefreshCw className="h-3.5 w-3.5 animate-spin" /> : <Rocket className="h-3.5 w-3.5" />}
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent>Deploy now</TooltipContent>
                    </Tooltip>
                </div>
            </td>
        </tr>
    );
}

export default function SitesIndex({ sites }: { sites: Site[] }) {
    const [search, setSearch] = useState('');
    const [deployingSiteId, setDeployingSiteId] = useState<string | null>(null);

    const filtered = sites.filter((site) =>
        site.name.toLowerCase().includes(search.toLowerCase()) ||
        (site.domain ?? '').toLowerCase().includes(search.toLowerCase()),
    );

    function handleDeploy(siteId: string) {
        setDeployingSiteId(siteId);
        router.post(`/dashboard/sites/${siteId}/deploy`, {}, {
            onFinish: () => setDeployingSiteId(null),
        });
    }

    return (
        <AppLayout title="Sites">
            <Head title="Sites" />

            <div className="space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-semibold text-zinc-100">Sites</h1>
                        <p className="text-sm text-zinc-400">Add, connect, and deploy from one workspace.</p>
                    </div>
                    <Button asChild className="shrink-0 bg-emerald-500 text-zinc-950 hover:bg-emerald-400">
                        <Link href="/dashboard/sites/create"><Plus className="mr-2 h-4 w-4" />Add new site</Link>
                    </Button>
                </div>

                <Card className="border-zinc-800 bg-[#1e1e1e]">
                    <CardHeader className="pb-3">
                        <CardTitle className="text-base text-zinc-100">Your sites</CardTitle>
                        <CardDescription>Click a site row to open its dashboard. Deploy and settings stay available in the actions column.</CardDescription>
                        <div className="relative mt-2">
                            <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-500" />
                            <Input
                                value={search}
                                onChange={(event) => setSearch(event.target.value)}
                                placeholder="Filter sites..."
                                className="border-zinc-700 bg-zinc-900 pl-9 text-zinc-100 placeholder:text-zinc-600"
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
                                                {sites.length === 0 ? 'No sites yet - create one above.' : 'No sites match your search.'}
                                            </td>
                                        </tr>
                                    ) : (
                                        filtered.map((site) => (
                                            <SiteRow
                                                key={site.id}
                                                site={site}
                                                deploying={deployingSiteId === site.id}
                                                onDeploy={handleDeploy}
                                            />
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
