import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/layouts/AppLayout';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Switch } from '@/components/ui/switch';
import {
    AlertDialog, AlertDialogContent, AlertDialogHeader, AlertDialogTitle,
    AlertDialogDescription, AlertDialogFooter, AlertDialogAction, AlertDialogCancel,
} from '@/components/ui/alert-dialog';
import { Plus, Trash2, ArrowRight } from 'lucide-react';

interface Site { id: string; name: string; }
interface Redirect { id: string; from_path: string; to_path: string; status_code: number; is_active: boolean; }

export default function Redirects({ site, redirects }: { site: Site; redirects: Redirect[] }) {
    const [deleteId, setDeleteId] = useState<string | null>(null);
    const [showAdd, setShowAdd] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({ from_path: '', to_path: '', status_code: '301' });

    const toggle = (id: string) => router.post(`/dashboard/sites/${site.id}/redirects/${id}/toggle`);
    const del = (id: string) => { router.delete(`/dashboard/sites/${site.id}/redirects/${id}`); setDeleteId(null); };
    const submit = (e: React.FormEvent) => { e.preventDefault(); post(`/dashboard/sites/${site.id}/redirects`, { onSuccess: () => { reset(); setShowAdd(false); } }); };

    return (
        <AppLayout title="Redirects">
            <Head title={`Redirects — ${site.name}`} />
            <div className="space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold text-zinc-100">Redirects</h1>
                        <p className="text-sm text-zinc-400">{site.name}</p>
                    </div>
                    <Button size="sm" onClick={() => setShowAdd((v) => !v)}>
                        <Plus className="mr-1.5 h-3.5 w-3.5" />Add redirect
                    </Button>
                </div>

                {showAdd && (
                    <Card className="border-zinc-800 bg-[#1e1e1e]">
                        <CardContent className="pt-5">
                            <form onSubmit={submit} className="flex flex-wrap items-end gap-3">
                                <div className="space-y-1">
                                    <Label className="text-xs text-zinc-400">From path</Label>
                                    <Input value={data.from_path} onChange={(e) => setData('from_path', e.target.value)}
                                        placeholder="/old-page" className="border-zinc-700 bg-zinc-900 text-zinc-100 w-52" />
                                    {errors.from_path && <p className="text-xs text-red-400">{errors.from_path}</p>}
                                </div>
                                <ArrowRight className="mb-2 h-4 w-4 shrink-0 text-zinc-600" />
                                <div className="space-y-1">
                                    <Label className="text-xs text-zinc-400">To path / URL</Label>
                                    <Input value={data.to_path} onChange={(e) => setData('to_path', e.target.value)}
                                        placeholder="/new-page" className="border-zinc-700 bg-zinc-900 text-zinc-100 w-52" />
                                </div>
                                <div className="space-y-1">
                                    <Label className="text-xs text-zinc-400">Code</Label>
                                    <Input value={data.status_code} onChange={(e) => setData('status_code', e.target.value)}
                                        className="border-zinc-700 bg-zinc-900 text-zinc-100 w-20" />
                                </div>
                                <div className="flex gap-2">
                                    <Button type="submit" size="sm" disabled={processing}><Plus className="mr-1 h-3.5 w-3.5" />Add</Button>
                                    <Button type="button" variant="ghost" size="sm" onClick={() => setShowAdd(false)}>Cancel</Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                )}

                <Card className="border-zinc-800 bg-[#1e1e1e]">
                    <CardContent className="p-0">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b border-zinc-800">
                                    {['From', '', 'To', 'Code', 'Active', ''].map((h, i) => (
                                        <th key={i} className="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-widest text-zinc-500">{h}</th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {redirects.length === 0 ? (
                                    <tr><td colSpan={6} className="px-4 py-8 text-center text-sm text-zinc-500">No redirects yet.</td></tr>
                                ) : redirects.map((r) => (
                                    <tr key={r.id} className="border-b border-zinc-800/60 hover:bg-zinc-800/20">
                                        <td className="px-4 py-3 font-mono text-xs text-zinc-300">{r.from_path}</td>
                                        <td className="px-2 py-3"><ArrowRight className="h-3.5 w-3.5 text-zinc-600" /></td>
                                        <td className="px-4 py-3 font-mono text-xs text-zinc-300">{r.to_path}</td>
                                        <td className="px-4 py-3"><Badge variant="secondary">{r.status_code}</Badge></td>
                                        <td className="px-4 py-3"><Switch checked={r.is_active} onCheckedChange={() => toggle(r.id)} /></td>
                                        <td className="px-4 py-3">
                                            <Button variant="ghost" size="icon" className="h-7 w-7 text-red-400 hover:text-red-300" onClick={() => setDeleteId(r.id)}>
                                                <Trash2 className="h-3.5 w-3.5" />
                                            </Button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </CardContent>
                </Card>
            </div>

            <AlertDialog open={!!deleteId} onOpenChange={(open) => !open && setDeleteId(null)}>
                <AlertDialogContent className="border-zinc-800 bg-[#1e1e1e]">
                    <AlertDialogHeader>
                        <AlertDialogTitle className="text-zinc-100">Delete redirect?</AlertDialogTitle>
                        <AlertDialogDescription>This cannot be undone.</AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel className="border-zinc-700">Cancel</AlertDialogCancel>
                        <AlertDialogAction className="bg-destructive text-destructive-foreground hover:bg-destructive/90" onClick={() => deleteId && del(deleteId)}>Delete</AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </AppLayout>
    );
}
