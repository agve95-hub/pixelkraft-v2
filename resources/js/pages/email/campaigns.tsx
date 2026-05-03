import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/layouts/AppLayout';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent,
    AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Plus, Send, Trash2, Pencil, X, Check } from 'lucide-react';

interface Site { id: string; name: string; }
interface Campaign {
    id: string;
    subject: string;
    status: string;
    scheduled_at: string | null;
    sent_at: string | null;
    stats: { sent?: number; failed?: number; opened?: number; clicked?: number } | null;
    created_at: string;
}

const STATUS_STYLES: Record<string, string> = {
    draft: 'bg-zinc-800 text-zinc-400 border-zinc-700',
    scheduled: 'bg-blue-900/40 text-blue-400 border-blue-800',
    sending: 'bg-yellow-900/40 text-yellow-400 border-yellow-800',
    sent: 'bg-green-900/40 text-green-400 border-green-800',
};

function StatusBadge({ status }: { status: string }) {
    return (
        <span className={`inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-medium ${STATUS_STYLES[status] ?? STATUS_STYLES.draft}`}>
            {status}
        </span>
    );
}

function CreateForm({ siteId, onClose }: { siteId: string; onClose: () => void }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        subject: '',
        body_html: '',
        scheduled_at: '',
    });
    return (
        <form
            onSubmit={(e) => { e.preventDefault(); post(`/dashboard/sites/${siteId}/newsletters`, { onSuccess: () => { reset(); onClose(); } }); }}
            className="space-y-4"
        >
            <div className="space-y-1">
                <Label className="text-xs text-zinc-400">Subject</Label>
                <Input value={data.subject} onChange={(e) => setData('subject', e.target.value)} placeholder="Your newsletter subject" className="border-zinc-700 bg-zinc-900 text-zinc-100" />
                {errors.subject && <p className="text-xs text-red-400">{errors.subject}</p>}
            </div>
            <div className="space-y-1">
                <Label className="text-xs text-zinc-400">Body HTML</Label>
                <textarea
                    value={data.body_html}
                    onChange={(e) => setData('body_html', e.target.value)}
                    rows={8}
                    placeholder="<p>Hello {{name}},</p><p>...</p><p><a href='{{unsubscribe_url}}'>Unsubscribe</a></p>"
                    className="w-full rounded-md border border-zinc-700 bg-zinc-900 px-3 py-2 font-mono text-xs text-zinc-100 placeholder:text-zinc-600 focus:outline-none focus:ring-1 focus:ring-zinc-500"
                />
            </div>
            <div className="space-y-1">
                <Label className="text-xs text-zinc-400">Schedule (optional — leave empty to save as draft)</Label>
                <Input type="datetime-local" value={data.scheduled_at} onChange={(e) => setData('scheduled_at', e.target.value)} className="border-zinc-700 bg-zinc-900 text-zinc-100" />
            </div>
            <div className="flex gap-2">
                <Button type="submit" size="sm" disabled={processing}><Plus className="mr-1.5 h-3.5 w-3.5" />Create</Button>
                <Button type="button" variant="ghost" size="sm" onClick={onClose}>Cancel</Button>
            </div>
        </form>
    );
}

function EditForm({ campaign, siteId, onClose }: { campaign: Campaign; siteId: string; onClose: () => void }) {
    const { data, setData, put, processing, errors } = useForm({
        subject: campaign.subject,
        body_html: '',
        scheduled_at: campaign.scheduled_at ? campaign.scheduled_at.slice(0, 16) : '',
    });
    return (
        <form
            onSubmit={(e) => { e.preventDefault(); put(`/dashboard/sites/${siteId}/newsletters/${campaign.id}`, { onSuccess: onClose }); }}
            className="space-y-3"
        >
            <Input value={data.subject} onChange={(e) => setData('subject', e.target.value)} className="border-zinc-700 bg-zinc-900 text-zinc-100" />
            {errors.subject && <p className="text-xs text-red-400">{errors.subject}</p>}
            <Input type="datetime-local" value={data.scheduled_at} onChange={(e) => setData('scheduled_at', e.target.value)} className="border-zinc-700 bg-zinc-900 text-zinc-100" />
            <div className="flex gap-2">
                <Button type="submit" size="sm" className="h-7 px-2" disabled={processing}><Check className="h-3 w-3" /></Button>
                <Button type="button" variant="ghost" size="sm" className="h-7 px-2" onClick={onClose}><X className="h-3 w-3" /></Button>
            </div>
        </form>
    );
}

export default function EmailCampaigns({
    site,
    campaigns = [],
    subscriberCount = 0,
}: {
    site: Site;
    campaigns: Campaign[];
    subscriberCount: number;
}) {
    const [showCreate, setShowCreate] = useState(false);
    const [editingId, setEditingId] = useState<string | null>(null);
    const [sendTarget, setSendTarget] = useState<Campaign | null>(null);
    const [deleteTarget, setDeleteTarget] = useState<Campaign | null>(null);

    const sendCampaign = (c: Campaign) => {
        router.post(`/dashboard/sites/${site.id}/newsletters/${c.id}/send`);
        setSendTarget(null);
    };

    const deleteCampaign = (c: Campaign) => {
        router.delete(`/dashboard/sites/${site.id}/newsletters/${c.id}`);
        setDeleteTarget(null);
    };

    return (
        <AppLayout title="Newsletters">
            <Head title={`Newsletters — ${site.name}`} />
            <div className="space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold text-zinc-100">Newsletters</h1>
                        <p className="text-sm text-zinc-400">{site.name}</p>
                    </div>
                    <Button size="sm" onClick={() => setShowCreate((v) => !v)}>
                        <Plus className="mr-1.5 h-3.5 w-3.5" />New Campaign
                    </Button>
                </div>

                <div className="flex gap-4">
                    <div className="rounded-lg border border-zinc-800 bg-[#1e1e1e] px-4 py-2">
                        <p className="text-xs text-zinc-500">Active subscribers</p>
                        <p className="text-lg font-semibold tabular-nums text-zinc-100">{subscriberCount}</p>
                    </div>
                    <div className="rounded-lg border border-zinc-800 bg-[#1e1e1e] px-4 py-2">
                        <p className="text-xs text-zinc-500">Campaigns</p>
                        <p className="text-lg font-semibold tabular-nums text-zinc-100">{campaigns.length}</p>
                    </div>
                </div>

                {showCreate && (
                    <Card className="border-zinc-800 bg-[#1e1e1e]">
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-zinc-300">New campaign</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <CreateForm siteId={site.id} onClose={() => setShowCreate(false)} />
                        </CardContent>
                    </Card>
                )}

                <Card className="border-zinc-800 bg-[#1e1e1e]">
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b border-zinc-800">
                                        {['Subject', 'Status', 'Sent', 'Scheduled', 'Actions'].map((h) => (
                                            <th key={h} className="px-3 py-2.5 text-left text-xs font-medium uppercase tracking-widest text-zinc-500">{h}</th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {campaigns.length === 0 ? (
                                        <tr><td colSpan={5} className="px-3 py-10 text-center text-sm text-zinc-500">No campaigns yet. Create one above.</td></tr>
                                    ) : campaigns.map((c) => (
                                        <tr key={c.id} className="border-b border-zinc-800/60 hover:bg-zinc-800/30">
                                            <td className="px-3 py-2.5">
                                                {editingId === c.id ? (
                                                    <EditForm campaign={c} siteId={site.id} onClose={() => setEditingId(null)} />
                                                ) : (
                                                    <span className="text-zinc-200">{c.subject}</span>
                                                )}
                                            </td>
                                            <td className="px-3 py-2.5"><StatusBadge status={c.status} /></td>
                                            <td className="px-3 py-2.5 tabular-nums text-zinc-400">
                                                {c.stats?.sent != null ? c.stats.sent.toLocaleString() : <span className="text-zinc-600">—</span>}
                                            </td>
                                            <td className="px-3 py-2.5 text-zinc-400">
                                                {c.scheduled_at ? c.scheduled_at.slice(0, 16).replace('T', ' ') : <span className="text-zinc-600">—</span>}
                                            </td>
                                            <td className="px-3 py-2.5">
                                                {!c.isSent && editingId !== c.id && (
                                                    <div className="flex gap-1">
                                                        {(c.status === 'draft' || c.status === 'scheduled') && (
                                                            <>
                                                                <Button variant="ghost" size="icon" className="h-7 w-7" onClick={() => setEditingId(c.id)} title="Edit">
                                                                    <Pencil className="h-3.5 w-3.5" />
                                                                </Button>
                                                                <Button variant="ghost" size="icon" className="h-7 w-7 text-blue-400 hover:text-blue-300" onClick={() => setSendTarget(c)} title="Send now">
                                                                    <Send className="h-3.5 w-3.5" />
                                                                </Button>
                                                                <Button variant="ghost" size="icon" className="h-7 w-7 text-red-400 hover:text-red-300" onClick={() => setDeleteTarget(c)} title="Delete">
                                                                    <Trash2 className="h-3.5 w-3.5" />
                                                                </Button>
                                                            </>
                                                        )}
                                                    </div>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <AlertDialog open={!!sendTarget} onOpenChange={(open) => !open && setSendTarget(null)}>
                <AlertDialogContent className="border-zinc-800 bg-[#1e1e1e]">
                    <AlertDialogHeader>
                        <AlertDialogTitle className="text-zinc-100">Send campaign now?</AlertDialogTitle>
                        <AlertDialogDescription>
                            This will immediately send <span className="font-medium text-zinc-300">"{sendTarget?.subject}"</span> to {subscriberCount.toLocaleString()} active subscriber{subscriberCount !== 1 ? 's' : ''}. This cannot be undone.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel className="border-zinc-700">Cancel</AlertDialogCancel>
                        <AlertDialogAction onClick={() => sendTarget && sendCampaign(sendTarget)}>
                            Send
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>

            <AlertDialog open={!!deleteTarget} onOpenChange={(open) => !open && setDeleteTarget(null)}>
                <AlertDialogContent className="border-zinc-800 bg-[#1e1e1e]">
                    <AlertDialogHeader>
                        <AlertDialogTitle className="text-zinc-100">Delete campaign?</AlertDialogTitle>
                        <AlertDialogDescription>This cannot be undone.</AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel className="border-zinc-700">Cancel</AlertDialogCancel>
                        <AlertDialogAction className="bg-destructive text-destructive-foreground hover:bg-destructive/90" onClick={() => deleteTarget && deleteCampaign(deleteTarget)}>
                            Delete
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </AppLayout>
    );
}
