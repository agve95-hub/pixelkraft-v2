import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/layouts/AppLayout';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Badge } from '@/components/ui/badge';
import {
    AlertDialog, AlertDialogContent, AlertDialogHeader, AlertDialogTitle,
    AlertDialogDescription, AlertDialogFooter, AlertDialogAction, AlertDialogCancel,
} from '@/components/ui/alert-dialog';
import { Plus, Pencil, Trash2, Check, X } from 'lucide-react';
import { cn } from '@/lib/utils';

interface Site { id: string; name: string; }
interface Reminder { id: string; title: string; due_at: string | null; notes: string | null; completed_at: string | null; }

function isOverdue(due_at: string | null, completed_at: string | null) {
    if (completed_at || !due_at) return false;
    return new Date(due_at) < new Date();
}

function AddForm({ siteId, onClose }: { siteId: string; onClose: () => void }) {
    const { data, setData, post, processing, errors, reset } = useForm({ title: '', due_at: '', notes: '' });
    return (
        <form onSubmit={(e) => { e.preventDefault(); post(`/dashboard/sites/${siteId}/reminders`, { onSuccess: () => { reset(); onClose(); } }); }} className="space-y-3">
            <div className="space-y-1">
                <Label className="text-xs text-zinc-400">Title</Label>
                <Input value={data.title} onChange={(e) => setData('title', e.target.value)} className="border-zinc-700 bg-zinc-900 text-zinc-100" />
                {errors.title && <p className="text-xs text-red-400">{errors.title}</p>}
            </div>
            <div className="space-y-1">
                <Label className="text-xs text-zinc-400">Due at</Label>
                <Input type="datetime-local" value={data.due_at} onChange={(e) => setData('due_at', e.target.value)} className="border-zinc-700 bg-zinc-900 text-zinc-100" />
            </div>
            <div className="space-y-1">
                <Label className="text-xs text-zinc-400">Notes</Label>
                <Textarea value={data.notes} onChange={(e) => setData('notes', e.target.value)} rows={2} className="border-zinc-700 bg-zinc-900 text-zinc-100" />
            </div>
            <div className="flex gap-2">
                <Button type="submit" size="sm" disabled={processing}><Plus className="mr-1.5 h-3.5 w-3.5" />Add</Button>
                <Button type="button" variant="ghost" size="sm" onClick={onClose}>Cancel</Button>
            </div>
        </form>
    );
}

function EditForm({ reminder, siteId, onCancel }: { reminder: Reminder; siteId: string; onCancel: () => void }) {
    const { data, setData, put, processing } = useForm({ title: reminder.title, due_at: reminder.due_at ?? '', notes: reminder.notes ?? '' });
    return (
        <form onSubmit={(e) => { e.preventDefault(); put(`/dashboard/sites/${siteId}/reminders/${reminder.id}`, { onSuccess: onCancel }); }} className="space-y-2 mt-2">
            <Input value={data.title} onChange={(e) => setData('title', e.target.value)} className="border-zinc-600 bg-zinc-900 text-zinc-100" />
            <Input type="datetime-local" value={data.due_at} onChange={(e) => setData('due_at', e.target.value)} className="border-zinc-600 bg-zinc-900 text-zinc-100" />
            <div className="flex gap-2">
                <Button type="submit" size="sm" className="h-7 px-2" disabled={processing}><Check className="h-3 w-3 mr-1" />Save</Button>
                <Button type="button" variant="ghost" size="sm" className="h-7 px-2" onClick={onCancel}><X className="h-3 w-3" /></Button>
            </div>
        </form>
    );
}

export default function Reminders({ site, reminders }: { site: Site; reminders: Reminder[] }) {
    const [editingId, setEditingId] = useState<string | null>(null);
    const [deleteId, setDeleteId] = useState<string | null>(null);
    const [showAdd, setShowAdd] = useState(false);

    const toggle = (id: string) => router.post(`/dashboard/sites/${site.id}/reminders/${id}/complete`);
    const deleteReminder = (id: string) => { router.delete(`/dashboard/sites/${site.id}/reminders/${id}`); setDeleteId(null); };

    const overdue = reminders.filter((r) => isOverdue(r.due_at, r.completed_at));
    const pending = reminders.filter((r) => !r.completed_at && !isOverdue(r.due_at, r.completed_at));
    const done = reminders.filter((r) => !!r.completed_at);

    const ReminderRow = ({ reminder }: { reminder: Reminder }) => (
        <div className="border-b border-zinc-800/60 px-4 py-3">
            {editingId === reminder.id ? (
                <EditForm reminder={reminder} siteId={site.id} onCancel={() => setEditingId(null)} />
            ) : (
                <div className="flex items-start gap-3">
                    <button
                        onClick={() => toggle(reminder.id)}
                        className={cn(
                            'mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded border transition-colors',
                            reminder.completed_at
                                ? 'border-emerald-500 bg-emerald-500 text-zinc-950'
                                : 'border-zinc-600 hover:border-zinc-400',
                        )}
                    >
                        {reminder.completed_at && <Check className="h-2.5 w-2.5" />}
                    </button>
                    <div className="flex-1 min-w-0">
                        <p className={cn('text-sm', reminder.completed_at && 'line-through text-zinc-500')}>{reminder.title}</p>
                        {reminder.due_at && (
                            <p className={cn('mt-0.5 text-xs', isOverdue(reminder.due_at, reminder.completed_at) ? 'text-amber-400' : 'text-zinc-500')}>
                                {new Date(reminder.due_at).toLocaleString()}
                            </p>
                        )}
                    </div>
                    <div className="flex gap-1">
                        <Button variant="ghost" size="icon" className="h-7 w-7" onClick={() => setEditingId(reminder.id)}>
                            <Pencil className="h-3.5 w-3.5" />
                        </Button>
                        <Button variant="ghost" size="icon" className="h-7 w-7 text-red-400 hover:text-red-300" onClick={() => setDeleteId(reminder.id)}>
                            <Trash2 className="h-3.5 w-3.5" />
                        </Button>
                    </div>
                </div>
            )}
        </div>
    );

    return (
        <AppLayout title="Reminders">
            <Head title={`Reminders — ${site.name}`} />
            <div className="space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold text-zinc-100">Reminders</h1>
                        <p className="text-sm text-zinc-400">{site.name}</p>
                    </div>
                    <Button size="sm" onClick={() => setShowAdd((v) => !v)}>
                        <Plus className="mr-1.5 h-3.5 w-3.5" />Add Reminder
                    </Button>
                </div>

                {showAdd && (
                    <Card className="border-zinc-800 bg-[#1e1e1e]">
                        <CardContent className="pt-5"><AddForm siteId={site.id} onClose={() => setShowAdd(false)} /></CardContent>
                    </Card>
                )}

                {overdue.length > 0 && (
                    <div>
                        <p className="mb-2 text-xs font-semibold uppercase tracking-widest text-amber-400">Overdue</p>
                        <Card className="border-amber-500/20 bg-[#1e1e1e]">
                            <CardContent className="p-0">{overdue.map((r) => <ReminderRow key={r.id} reminder={r} />)}</CardContent>
                        </Card>
                    </div>
                )}

                {pending.length > 0 && (
                    <div>
                        <p className="mb-2 text-xs font-semibold uppercase tracking-widest text-zinc-500">Pending</p>
                        <Card className="border-zinc-800 bg-[#1e1e1e]">
                            <CardContent className="p-0">{pending.map((r) => <ReminderRow key={r.id} reminder={r} />)}</CardContent>
                        </Card>
                    </div>
                )}

                {done.length > 0 && (
                    <div>
                        <p className="mb-2 text-xs font-semibold uppercase tracking-widest text-zinc-600">Completed</p>
                        <Card className="border-zinc-800/50 bg-[#1e1e1e] opacity-70">
                            <CardContent className="p-0">{done.map((r) => <ReminderRow key={r.id} reminder={r} />)}</CardContent>
                        </Card>
                    </div>
                )}

                {reminders.length === 0 && !showAdd && (
                    <Card className="border-zinc-800 bg-[#1e1e1e]">
                        <CardContent className="py-8 text-center">
                            <p className="text-sm text-zinc-500">No reminders yet.</p>
                        </CardContent>
                    </Card>
                )}
            </div>

            <AlertDialog open={!!deleteId} onOpenChange={(open) => !open && setDeleteId(null)}>
                <AlertDialogContent className="border-zinc-800 bg-[#1e1e1e]">
                    <AlertDialogHeader>
                        <AlertDialogTitle className="text-zinc-100">Delete reminder?</AlertDialogTitle>
                        <AlertDialogDescription>This cannot be undone.</AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel className="border-zinc-700">Cancel</AlertDialogCancel>
                        <AlertDialogAction className="bg-destructive text-destructive-foreground hover:bg-destructive/90" onClick={() => deleteId && deleteReminder(deleteId)}>Delete</AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </AppLayout>
    );
}
