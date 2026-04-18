import { Head, useForm } from '@inertiajs/react';
import AppLayout from '@/layouts/AppLayout';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Save, ExternalLink, AlertTriangle, ShieldOff, ShieldCheck } from 'lucide-react';
import { cn } from '@/lib/utils';

interface Site { id: string; name: string; maintenance_settings: Record<string, any> | null; }

export default function Maintenance({ site, siteIsDown }: { site: Site; siteIsDown: boolean }) {
    const s = site.maintenance_settings ?? {};
    const { data, setData, put, processing } = useForm({
        enabled: Boolean(s.enabled),
        title: String(s.title ?? "We'll be back soon"),
        message: String(s.message ?? "We're performing scheduled maintenance. Please check back later."),
        allowed_ips: String((s.allowed_ips ?? []).join('\n')),
    });

    const submit = (e: React.FormEvent) => { e.preventDefault(); put(`/dashboard/sites/${site.id}/maintenance`); };

    return (
        <AppLayout title="Maintenance">
            <Head title={`Maintenance — ${site.name}`} />
            <form onSubmit={submit}>
                <div className="space-y-6">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h1 className="text-xl font-semibold text-zinc-100">Maintenance mode</h1>
                            <p className="text-sm text-zinc-400">{site.name}</p>
                        </div>
                        <div className="flex items-center gap-2">
                            <a href={`/dashboard/sites/${site.id}/maintenance/preview`} target="_blank"
                                className="flex items-center gap-1 text-xs text-zinc-400 hover:text-zinc-200 transition-colors">
                                <ExternalLink className="h-3.5 w-3.5" />Preview
                            </a>
                            <Button type="submit" size="sm" disabled={processing}>
                                <Save className="mr-1.5 h-3.5 w-3.5" />Save
                            </Button>
                        </div>
                    </div>

                    {siteIsDown && !data.enabled && (
                        <div className="flex items-start gap-3 rounded-lg border border-amber-500/30 bg-amber-500/10 px-4 py-3">
                            <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0 text-amber-400" />
                            <div>
                                <p className="text-sm font-medium text-amber-300">Site is currently down</p>
                                <p className="mt-0.5 text-xs text-amber-400/80">
                                    The uptime monitor detected this site as unreachable. Consider enabling maintenance mode so visitors see a proper message instead of a connection error.
                                </p>
                            </div>
                        </div>
                    )}

                    {/* Big on/off toggle */}
                    <div className={cn(
                        'flex items-center justify-between rounded-xl border px-5 py-4 transition-colors',
                        data.enabled
                            ? 'border-amber-500/40 bg-amber-500/10'
                            : 'border-zinc-700 bg-[#1e1e1e]',
                    )}>
                        <div className="flex items-center gap-3">
                            {data.enabled
                                ? <ShieldOff className="h-5 w-5 text-amber-400" />
                                : <ShieldCheck className="h-5 w-5 text-emerald-400" />}
                            <div>
                                <p className={cn('font-medium', data.enabled ? 'text-amber-300' : 'text-zinc-100')}>
                                    {data.enabled ? 'Maintenance mode is ON' : 'Maintenance mode is OFF'}
                                </p>
                                <p className="text-xs text-zinc-500">
                                    {data.enabled
                                        ? 'Visitors see the maintenance page. Allowed IPs bypass it.'
                                        : 'Your site is live. Visitors see the normal site.'}
                                </p>
                            </div>
                        </div>
                        <button
                            type="button"
                            onClick={() => setData('enabled', !data.enabled)}
                            className={cn(
                                'relative inline-flex h-8 w-16 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-zinc-900',
                                data.enabled ? 'bg-amber-500 focus:ring-amber-500' : 'bg-zinc-600 focus:ring-zinc-500',
                            )}
                        >
                            <span className={cn(
                                'pointer-events-none inline-block h-7 w-7 rounded-full bg-white shadow ring-0 transition-transform duration-200',
                                data.enabled ? 'translate-x-8' : 'translate-x-0',
                            )} />
                        </button>
                    </div>

                    <Card className="border-zinc-800 bg-[#1e1e1e]">
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-zinc-300">Page content</CardTitle>
                            <CardDescription className="text-xs text-zinc-500">This text is shown to visitors when maintenance mode is active.</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="space-y-1">
                                <Label className="text-xs text-zinc-400">Heading</Label>
                                <Input value={data.title} onChange={(e) => setData('title', e.target.value)} className="border-zinc-700 bg-zinc-900 text-zinc-100" />
                            </div>
                            <div className="space-y-1">
                                <Label className="text-xs text-zinc-400">Message</Label>
                                <Textarea value={data.message} onChange={(e) => setData('message', e.target.value)} rows={4} className="border-zinc-700 bg-zinc-900 text-zinc-100" />
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="border-zinc-800 bg-[#1e1e1e]">
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-zinc-300">Allowed IPs</CardTitle>
                            <CardDescription className="text-xs text-zinc-500">These IPs bypass maintenance mode and see the live site. One per line.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Textarea
                                value={data.allowed_ips}
                                onChange={(e) => setData('allowed_ips', e.target.value)}
                                rows={4}
                                placeholder={"127.0.0.1\n192.168.1.1"}
                                className="border-zinc-700 bg-zinc-900 text-zinc-100 font-mono text-xs"
                            />
                        </CardContent>
                    </Card>
                </div>
            </form>
        </AppLayout>
    );
}
