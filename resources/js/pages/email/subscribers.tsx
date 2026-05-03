import { Head, router, useForm } from '@inertiajs/react';
import { useRef, useState } from 'react';
import AppLayout from '@/layouts/AppLayout';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import {
    AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent,
    AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Plus, Trash2, Upload, Download } from 'lucide-react';

interface Site { id: string; name: string; }
interface Subscriber { id: string; email: string; name: string | null; status: string; created_at: string; }

function statusBadge(status: string) {
    const variants: Record<string, string> = {
        active: 'bg-green-900/40 text-green-400 border-green-800',
        unsubscribed: 'bg-zinc-800 text-zinc-500 border-zinc-700',
        bounced: 'bg-red-900/40 text-red-400 border-red-800',
    };
    return (
        <span className={`inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-medium ${variants[status] ?? variants.active}`}>
            {status}
        </span>
    );
}

function AddForm({ siteId, onClose }: { siteId: string; onClose: () => void }) {
    const { data, setData, post, processing, errors, reset } = useForm({ email: '', name: '' });
    return (
        <form
            onSubmit={(e) => { e.preventDefault(); post(`/dashboard/sites/${siteId}/subscribers`, { onSuccess: () => { reset(); onClose(); } }); }}
            className="flex flex-wrap gap-3"
        >
            <div className="space-y-1 flex-1 min-w-48">
                <Label className="text-xs text-zinc-400">Email</Label>
                <Input value={data.email} onChange={(e) => setData('email', e.target.value)} placeholder="subscriber@example.com" className="border-zinc-700 bg-zinc-900 text-zinc-100" />
                {errors.email && <p className="text-xs text-red-400">{errors.email}</p>}
            </div>
            <div className="space-y-1 flex-1 min-w-36">
                <Label className="text-xs text-zinc-400">Name (optional)</Label>
                <Input value={data.name} onChange={(e) => setData('name', e.target.value)} placeholder="Jane Smith" className="border-zinc-700 bg-zinc-900 text-zinc-100" />
            </div>
            <div className="flex items-end gap-2">
                <Button type="submit" size="sm" disabled={processing}><Plus className="mr-1.5 h-3.5 w-3.5" />Add</Button>
                <Button type="button" variant="ghost" size="sm" onClick={onClose}>Cancel</Button>
            </div>
        </form>
    );
}

export default function Subscribers({ site, subscribers = [] }: { site: Site; subscribers: Subscriber[] }) {
    const [showAdd, setShowAdd] = useState(false);
    const [deleteId, setDeleteId] = useState<string | null>(null);
    const csvRef = useRef<HTMLInputElement>(null);
    const [importing, setImporting] = useState(false);

    const deleteSubscriber = (id: string) => {
        router.delete(`/dashboard/sites/${site.id}/subscribers/${id}`);
        setDeleteId(null);
    };

    const exportCsv = () => {
        const rows = [['email', 'name', 'status', 'subscribed_at']];
        subscribers.forEach((s) => rows.push([s.email, s.name ?? '', s.status, s.created_at.slice(0, 10)]));
        const csv = rows.map((r) => r.map((v) => `"${v.replace(/"/g, '""')}"`).join(',')).join('\n');
        const a = document.createElement('a');
        a.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv' }));
        a.download = `subscribers-${site.name}.csv`;
        a.click();
    };

    const handleImport = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (!file) return;
        setImporting(true);
        const form = new FormData();
        form.append('csv', file);
        router.post(`/dashboard/sites/${site.id}/subscribers/import`, form, {
            forceFormData: true,
            onFinish: () => { setImporting(false); if (csvRef.current) csvRef.current.value = ''; },
        });
    };

    const active = subscribers.filter((s) => s.status === 'active').length;

    return (
        <AppLayout title="Subscribers">
            <Head title={`Subscribers — ${site.name}`} />
            <div className="space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold text-zinc-100">Subscribers</h1>
                        <p className="text-sm text-zinc-400">{site.name}</p>
                    </div>
                    <div className="flex gap-2">
                        <input ref={csvRef} type="file" accept=".csv,text/csv" className="hidden" onChange={handleImport} />
                        <Button variant="outline" size="sm" disabled={importing} onClick={() => csvRef.current?.click()} className="border-zinc-700 text-zinc-300">
                            <Upload className="mr-1.5 h-3.5 w-3.5" />{importing ? 'Importing…' : 'Import CSV'}
                        </Button>
                        {subscribers.length > 0 && (
                            <Button variant="outline" size="sm" onClick={exportCsv} className="border-zinc-700 text-zinc-300">
                                <Download className="mr-1.5 h-3.5 w-3.5" />Export
                            </Button>
                        )}
                        <Button size="sm" onClick={() => setShowAdd((v) => !v)}>
                            <Plus className="mr-1.5 h-3.5 w-3.5" />Add
                        </Button>
                    </div>
                </div>

                {subscribers.length > 0 && (
                    <div className="flex gap-4">
                        <div className="rounded-lg border border-zinc-800 bg-[#1e1e1e] px-4 py-2">
                            <p className="text-xs text-zinc-500">Total</p>
                            <p className="text-lg font-semibold tabular-nums text-zinc-100">{subscribers.length}</p>
                        </div>
                        <div className="rounded-lg border border-zinc-800 bg-[#1e1e1e] px-4 py-2">
                            <p className="text-xs text-zinc-500">Active</p>
                            <p className="text-lg font-semibold tabular-nums text-green-400">{active}</p>
                        </div>
                    </div>
                )}

                {showAdd && (
                    <Card className="border-zinc-800 bg-[#1e1e1e]">
                        <CardContent className="pt-5">
                            <AddForm siteId={site.id} onClose={() => setShowAdd(false)} />
                        </CardContent>
                    </Card>
                )}

                <Card className="border-zinc-800 bg-[#1e1e1e]">
                    <CardHeader className="pb-2">
                        <CardTitle className="text-sm font-medium text-zinc-300">
                            {subscribers.length} subscriber{subscribers.length !== 1 ? 's' : ''}
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b border-zinc-800">
                                        {['Email', 'Name', 'Status', 'Subscribed', 'Actions'].map((h) => (
                                            <th key={h} className="px-3 py-2.5 text-left text-xs font-medium uppercase tracking-widest text-zinc-500">{h}</th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {subscribers.length === 0 ? (
                                        <tr><td colSpan={5} className="px-3 py-10 text-center text-sm text-zinc-500">No subscribers yet. Add one or import a CSV.</td></tr>
                                    ) : subscribers.map((s) => (
                                        <tr key={s.id} className="border-b border-zinc-800/60 hover:bg-zinc-800/30">
                                            <td className="px-3 py-2.5 text-zinc-200">{s.email}</td>
                                            <td className="px-3 py-2.5 text-zinc-400">{s.name ?? <span className="text-zinc-600">—</span>}</td>
                                            <td className="px-3 py-2.5">{statusBadge(s.status)}</td>
                                            <td className="px-3 py-2.5 text-zinc-400">{s.created_at.slice(0, 10)}</td>
                                            <td className="px-3 py-2.5">
                                                <Button
                                                    variant="ghost" size="icon"
                                                    className="h-7 w-7 text-red-400 hover:text-red-300"
                                                    onClick={() => setDeleteId(s.id)}
                                                >
                                                    <Trash2 className="h-3.5 w-3.5" />
                                                </Button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <AlertDialog open={!!deleteId} onOpenChange={(open) => !open && setDeleteId(null)}>
                <AlertDialogContent className="border-zinc-800 bg-[#1e1e1e]">
                    <AlertDialogHeader>
                        <AlertDialogTitle className="text-zinc-100">Remove subscriber?</AlertDialogTitle>
                        <AlertDialogDescription>This will permanently delete the subscriber record.</AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel className="border-zinc-700">Cancel</AlertDialogCancel>
                        <AlertDialogAction className="bg-destructive text-destructive-foreground hover:bg-destructive/90" onClick={() => deleteId && deleteSubscriber(deleteId)}>
                            Remove
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </AppLayout>
    );
}
