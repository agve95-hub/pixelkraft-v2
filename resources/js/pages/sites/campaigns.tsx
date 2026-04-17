import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/layouts/AppLayout';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Badge } from '@/components/ui/badge';
import { Switch } from '@/components/ui/switch';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import {
    Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter,
} from '@/components/ui/dialog';
import {
    AlertDialog, AlertDialogContent, AlertDialogHeader, AlertDialogTitle,
    AlertDialogDescription, AlertDialogFooter, AlertDialogAction, AlertDialogCancel,
} from '@/components/ui/alert-dialog';
import {
    DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Plus, MoreHorizontal, Pencil, Copy, Trash2 } from 'lucide-react';

interface Site { id: string; name: string; }
interface Campaign {
    id: string; name: string; headline: string | null; body: string | null;
    cta_text: string | null; cta_url: string | null; trigger: string | null;
    starts_at: string | null; ends_at: string | null; priority: number;
    is_dismissible: boolean; locale: string | null; is_enabled: boolean;
}

type FormData = {
    name: string; headline: string; body: string; cta_text: string; cta_url: string;
    trigger: string; starts_at: string; ends_at: string; priority: string;
    is_dismissible: boolean; locale: string;
};

const BLANK: FormData = {
    name: '', headline: '', body: '', cta_text: '', cta_url: '',
    trigger: '', starts_at: '', ends_at: '', priority: '0', is_dismissible: true, locale: '',
};

function fromCampaign(c: Campaign): FormData {
    return {
        name: c.name, headline: c.headline ?? '', body: c.body ?? '',
        cta_text: c.cta_text ?? '', cta_url: c.cta_url ?? '', trigger: c.trigger ?? '',
        starts_at: c.starts_at ? c.starts_at.slice(0, 16) : '',
        ends_at: c.ends_at ? c.ends_at.slice(0, 16) : '',
        priority: String(c.priority), is_dismissible: c.is_dismissible, locale: c.locale ?? '',
    };
}

function CampaignFields({ data, setData, errors }: { data: FormData; setData: (k: keyof FormData, v: any) => void; errors: Partial<Record<keyof FormData, string>>; }) {
    return (
        <div className="space-y-3">
            <div className="space-y-1">
                <Label className="text-xs text-zinc-400">Name *</Label>
                <Input value={data.name} onChange={(e) => setData('name', e.target.value)} className="border-zinc-700 bg-zinc-900 text-zinc-100" />
                {errors.name && <p className="text-xs text-red-400">{errors.name}</p>}
            </div>
            <div className="space-y-1">
                <Label className="text-xs text-zinc-400">Headline</Label>
                <Input value={data.headline} onChange={(e) => setData('headline', e.target.value)} className="border-zinc-700 bg-zinc-900 text-zinc-100" />
            </div>
            <div className="space-y-1">
                <Label className="text-xs text-zinc-400">Body</Label>
                <Textarea value={data.body} onChange={(e) => setData('body', e.target.value)} rows={3} className="border-zinc-700 bg-zinc-900 text-zinc-100" />
            </div>
            <div className="grid grid-cols-2 gap-3">
                <div className="space-y-1">
                    <Label className="text-xs text-zinc-400">CTA Text</Label>
                    <Input value={data.cta_text} onChange={(e) => setData('cta_text', e.target.value)} className="border-zinc-700 bg-zinc-900 text-zinc-100" />
                </div>
                <div className="space-y-1">
                    <Label className="text-xs text-zinc-400">CTA URL</Label>
                    <Input value={data.cta_url} onChange={(e) => setData('cta_url', e.target.value)} placeholder="https://" className="border-zinc-700 bg-zinc-900 text-zinc-100" />
                </div>
            </div>
            <div className="grid grid-cols-2 gap-3">
                <div className="space-y-1">
                    <Label className="text-xs text-zinc-400">Starts at</Label>
                    <Input type="datetime-local" value={data.starts_at} onChange={(e) => setData('starts_at', e.target.value)} className="border-zinc-700 bg-zinc-900 text-zinc-100" />
                </div>
                <div className="space-y-1">
                    <Label className="text-xs text-zinc-400">Ends at</Label>
                    <Input type="datetime-local" value={data.ends_at} onChange={(e) => setData('ends_at', e.target.value)} className="border-zinc-700 bg-zinc-900 text-zinc-100" />
                </div>
            </div>
            <div className="grid grid-cols-2 gap-3">
                <div className="space-y-1">
                    <Label className="text-xs text-zinc-400">Trigger</Label>
                    <Select value={data.trigger || 'on_load'} onValueChange={(v) => setData('trigger', v)}>
                        <SelectTrigger className="border-zinc-700 bg-zinc-900 text-zinc-100"><SelectValue /></SelectTrigger>
                        <SelectContent className="border-zinc-700 bg-zinc-900">
                            <SelectItem value="on_load">On page load</SelectItem>
                            <SelectItem value="on_scroll">On scroll</SelectItem>
                            <SelectItem value="on_exit">On exit intent</SelectItem>
                            <SelectItem value="on_delay">After delay</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div className="space-y-1">
                    <Label className="text-xs text-zinc-400">Priority</Label>
                    <Input type="number" value={data.priority} onChange={(e) => setData('priority', e.target.value)} className="border-zinc-700 bg-zinc-900 text-zinc-100" />
                </div>
            </div>
            <div className="flex items-center gap-2 pt-1">
                <Switch checked={data.is_dismissible} onCheckedChange={(v) => setData('is_dismissible', v)} id="dismissible" />
                <Label htmlFor="dismissible" className="text-xs text-zinc-400 cursor-pointer">Dismissible by user</Label>
            </div>
        </div>
    );
}

function AddDialog({ siteId, open, onClose }: { siteId: string; open: boolean; onClose: () => void }) {
    const { data, setData, post, processing, errors, reset } = useForm<FormData>(BLANK);
    const handleClose = () => { reset(); onClose(); };
    return (
        <Dialog open={open} onOpenChange={(o) => !o && handleClose()}>
            <DialogContent className="border-zinc-800 bg-[#1e1e1e] max-w-lg max-h-[90vh] overflow-y-auto">
                <DialogHeader><DialogTitle className="text-zinc-100">New Campaign</DialogTitle></DialogHeader>
                <form onSubmit={(e) => { e.preventDefault(); post(`/dashboard/sites/${siteId}/campaigns`, { onSuccess: handleClose }); }}>
                    <CampaignFields data={data} setData={setData} errors={errors} />
                    <DialogFooter className="mt-4">
                        <Button type="button" variant="ghost" onClick={handleClose}>Cancel</Button>
                        <Button type="submit" disabled={processing}><Plus className="mr-1.5 h-3.5 w-3.5" />Create</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function EditDialog({ campaign, siteId, onClose }: { campaign: Campaign; siteId: string; onClose: () => void }) {
    const { data, setData, put, processing, errors } = useForm<FormData>(fromCampaign(campaign));
    return (
        <Dialog open onOpenChange={(o) => !o && onClose()}>
            <DialogContent className="border-zinc-800 bg-[#1e1e1e] max-w-lg max-h-[90vh] overflow-y-auto">
                <DialogHeader><DialogTitle className="text-zinc-100">Edit Campaign</DialogTitle></DialogHeader>
                <form onSubmit={(e) => { e.preventDefault(); put(`/dashboard/sites/${siteId}/campaigns/${campaign.id}`, { onSuccess: onClose }); }}>
                    <CampaignFields data={data} setData={setData} errors={errors} />
                    <DialogFooter className="mt-4">
                        <Button type="button" variant="ghost" onClick={onClose}>Cancel</Button>
                        <Button type="submit" disabled={processing}>Save changes</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function Campaigns({ site, campaigns }: { site: Site; campaigns: Campaign[] }) {
    const [showAdd, setShowAdd] = useState(false);
    const [editCampaign, setEditCampaign] = useState<Campaign | null>(null);
    const [deleteId, setDeleteId] = useState<string | null>(null);

    const toggle = (id: string) => router.post(`/dashboard/sites/${site.id}/campaigns/${id}/toggle`);
    const duplicate = (id: string) => router.post(`/dashboard/sites/${site.id}/campaigns/${id}/duplicate`);
    const deleteCampaign = (id: string) => { router.delete(`/dashboard/sites/${site.id}/campaigns/${id}`); setDeleteId(null); };

    return (
        <AppLayout title="Campaigns">
            <Head title={`Campaigns — ${site.name}`} />
            <div className="space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold text-zinc-100">Campaigns</h1>
                        <p className="text-sm text-zinc-400">{site.name}</p>
                    </div>
                    <Button size="sm" onClick={() => setShowAdd(true)}>
                        <Plus className="mr-1.5 h-3.5 w-3.5" />New Campaign
                    </Button>
                </div>

                <Card className="border-zinc-800 bg-[#1e1e1e]">
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b border-zinc-800">
                                        {['Name', 'Trigger', 'Schedule', 'Priority', 'Type', 'Enabled', ''].map((h) => (
                                            <th key={h} className="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-widest text-zinc-500">{h}</th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {campaigns.length === 0 ? (
                                        <tr><td colSpan={7} className="px-4 py-8 text-center text-sm text-zinc-500">No campaigns yet.</td></tr>
                                    ) : campaigns.map((c) => (
                                        <tr key={c.id} className="border-b border-zinc-800/60 hover:bg-zinc-800/30">
                                            <td className="px-4 py-3">
                                                <p className="font-medium text-zinc-100">{c.name}</p>
                                                {c.headline && <p className="mt-0.5 text-xs text-zinc-500 truncate max-w-[200px]">{c.headline}</p>}
                                            </td>
                                            <td className="px-4 py-3 text-zinc-400">{c.trigger ?? '—'}</td>
                                            <td className="px-4 py-3 text-xs text-zinc-400">
                                                {c.starts_at
                                                    ? <>{new Date(c.starts_at).toLocaleDateString()}<br />→ {c.ends_at ? new Date(c.ends_at).toLocaleDateString() : '∞'}</>
                                                    : '—'}
                                            </td>
                                            <td className="px-4 py-3 tabular-nums text-zinc-300">{c.priority}</td>
                                            <td className="px-4 py-3">
                                                {c.is_dismissible
                                                    ? <Badge variant="secondary">Dismissible</Badge>
                                                    : <Badge variant="outline">Sticky</Badge>}
                                            </td>
                                            <td className="px-4 py-3">
                                                <Switch checked={c.is_enabled} onCheckedChange={() => toggle(c.id)} />
                                            </td>
                                            <td className="px-4 py-3">
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger asChild>
                                                        <Button variant="ghost" size="icon" className="h-7 w-7">
                                                            <MoreHorizontal className="h-4 w-4" />
                                                        </Button>
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent align="end" className="border-zinc-700 bg-zinc-900">
                                                        <DropdownMenuItem onClick={() => setEditCampaign(c)} className="gap-2">
                                                            <Pencil className="h-4 w-4" />Edit
                                                        </DropdownMenuItem>
                                                        <DropdownMenuItem onClick={() => duplicate(c.id)} className="gap-2">
                                                            <Copy className="h-4 w-4" />Duplicate
                                                        </DropdownMenuItem>
                                                        <DropdownMenuSeparator className="bg-zinc-700" />
                                                        <DropdownMenuItem onClick={() => setDeleteId(c.id)} className="gap-2 text-red-400 focus:text-red-300">
                                                            <Trash2 className="h-4 w-4" />Delete
                                                        </DropdownMenuItem>
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <AddDialog siteId={site.id} open={showAdd} onClose={() => setShowAdd(false)} />
            {editCampaign && <EditDialog campaign={editCampaign} siteId={site.id} onClose={() => setEditCampaign(null)} />}

            <AlertDialog open={!!deleteId} onOpenChange={(open) => !open && setDeleteId(null)}>
                <AlertDialogContent className="border-zinc-800 bg-[#1e1e1e]">
                    <AlertDialogHeader>
                        <AlertDialogTitle className="text-zinc-100">Delete campaign?</AlertDialogTitle>
                        <AlertDialogDescription>This cannot be undone.</AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel className="border-zinc-700">Cancel</AlertDialogCancel>
                        <AlertDialogAction
                            className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                            onClick={() => deleteId && deleteCampaign(deleteId)}
                        >Delete</AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </AppLayout>
    );
}
