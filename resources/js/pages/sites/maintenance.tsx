import { Head, useForm } from '@inertiajs/react';
import AppLayout from '@/layouts/AppLayout';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Switch } from '@/components/ui/switch';
import { Save, ExternalLink } from 'lucide-react';

interface Site { id: string; name: string; maintenance_settings: Record<string, any> | null; }

export default function Maintenance({ site }: { site: Site }) {
    const s = site.maintenance_settings ?? {};
    const { data, setData, put, processing } = useForm({
        enabled: Boolean(s.enabled),
        title: String(s.title ?? 'We\'ll be back soon'),
        message: String(s.message ?? 'We\'re performing scheduled maintenance. Please check back later.'),
        allowed_ips: String((s.allowed_ips ?? []).join('\n')),
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        put(`/dashboard/sites/${site.id}/maintenance`);
    };

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
                                className="flex items-center gap-1 text-xs text-zinc-400 hover:text-zinc-200">
                                <ExternalLink className="h-3.5 w-3.5" />Preview
                            </a>
                            <Button type="submit" size="sm" disabled={processing}>
                                <Save className="mr-1.5 h-3.5 w-3.5" />Save
                            </Button>
                        </div>
                    </div>

                    <Card className="border-zinc-800 bg-[#1e1e1e]">
                        <CardHeader>
                            <div className="flex items-center justify-between">
                                <div>
                                    <CardTitle className="text-sm font-medium text-zinc-300">Enable maintenance mode</CardTitle>
                                    <CardDescription className="text-xs text-zinc-500">Shows a maintenance page to all visitors except allowed IPs.</CardDescription>
                                </div>
                                <Switch checked={data.enabled} onCheckedChange={(v) => setData('enabled', v)} />
                            </div>
                        </CardHeader>
                    </Card>

                    <Card className="border-zinc-800 bg-[#1e1e1e]">
                        <CardContent className="pt-5 space-y-4">
                            <div className="space-y-1">
                                <Label className="text-xs text-zinc-400">Page title</Label>
                                <Input value={data.title} onChange={(e) => setData('title', e.target.value)} className="border-zinc-700 bg-zinc-900 text-zinc-100" />
                            </div>
                            <div className="space-y-1">
                                <Label className="text-xs text-zinc-400">Message</Label>
                                <Textarea value={data.message} onChange={(e) => setData('message', e.target.value)} rows={4} className="border-zinc-700 bg-zinc-900 text-zinc-100" />
                            </div>
                            <div className="space-y-1">
                                <Label className="text-xs text-zinc-400">Allowed IPs (one per line)</Label>
                                <Textarea value={data.allowed_ips} onChange={(e) => setData('allowed_ips', e.target.value)}
                                    rows={4} placeholder="127.0.0.1&#10;192.168.1.1" className="border-zinc-700 bg-zinc-900 text-zinc-100 font-mono text-xs" />
                                <p className="text-xs text-zinc-600">These IPs bypass maintenance mode and see the live site.</p>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </form>
        </AppLayout>
    );
}
