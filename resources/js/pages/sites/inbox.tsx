import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/layouts/AppLayout';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { Mail, MailOpen, Trash2, Archive, ArchiveRestore, Send, Pencil } from 'lucide-react';
import { cn } from '@/lib/utils';

interface Site { id: string; name: string; }
interface Message {
    id: string; direction: string; from_email: string | null; from_name: string | null;
    to_email: string | null; subject: string | null; body: string | null;
    is_read: boolean; is_archived: boolean; created_at: string;
}

function ComposeDialog({ siteId, open, onClose }: { siteId: string; open: boolean; onClose: () => void }) {
    const { data, setData, post, processing, errors, reset } = useForm({ to_email: '', subject: '', body: '' });
    const handleClose = () => { reset(); onClose(); };
    return (
        <Dialog open={open} onOpenChange={(o) => !o && handleClose()}>
            <DialogContent className="border-zinc-800 bg-[#1e1e1e] max-w-lg">
                <DialogHeader><DialogTitle className="text-zinc-100">New message</DialogTitle></DialogHeader>
                <form onSubmit={(e) => { e.preventDefault(); post(`/dashboard/sites/${siteId}/inbox`, { onSuccess: handleClose }); }} className="space-y-3 pt-1">
                    <div className="space-y-1">
                        <Label className="text-xs text-zinc-400">To</Label>
                        <Input type="email" value={data.to_email} onChange={(e) => setData('to_email', e.target.value)} placeholder="client@example.com" className="border-zinc-700 bg-zinc-900 text-zinc-100" />
                        {errors.to_email && <p className="text-xs text-red-400">{errors.to_email}</p>}
                    </div>
                    <div className="space-y-1">
                        <Label className="text-xs text-zinc-400">Subject</Label>
                        <Input value={data.subject} onChange={(e) => setData('subject', e.target.value)} className="border-zinc-700 bg-zinc-900 text-zinc-100" />
                        {errors.subject && <p className="text-xs text-red-400">{errors.subject}</p>}
                    </div>
                    <div className="space-y-1">
                        <Label className="text-xs text-zinc-400">Message</Label>
                        <Textarea value={data.body} onChange={(e) => setData('body', e.target.value)} rows={6} className="border-zinc-700 bg-zinc-900 text-zinc-100" />
                        {errors.body && <p className="text-xs text-red-400">{errors.body}</p>}
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="ghost" onClick={handleClose}>Cancel</Button>
                        <Button type="submit" disabled={processing}><Send className="mr-1.5 h-3.5 w-3.5" />Send</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

const TABS = [
    { key: 'inbox', label: 'Inbox' },
    { key: 'sent', label: 'Sent' },
    { key: 'archived', label: 'Archived' },
];

export default function SiteInbox({ site, messages, tab }: { site: Site; messages: Message[]; tab: string }) {
    const [selected, setSelected] = useState<string | null>(null);
    const [showCompose, setShowCompose] = useState(false);
    const msg = messages.find((m) => m.id === selected);

    const goTab = (t: string) => { setSelected(null); router.get(`/dashboard/sites/${site.id}/inbox`, { tab: t }, { preserveState: false }); };
    const markRead = (id: string) => router.post(`/dashboard/sites/${site.id}/inbox/${id}/read`, {}, { preserveScroll: true });
    const archive = (id: string) => { router.post(`/dashboard/sites/${site.id}/inbox/${id}/archive`, {}, { onSuccess: () => setSelected(null) }); };
    const del = (id: string) => { router.delete(`/dashboard/sites/${site.id}/inbox/${id}`, { onSuccess: () => setSelected(null) }); };

    return (
        <AppLayout title="Inbox">
            <Head title={`Inbox — ${site.name}`} />
            <ComposeDialog siteId={site.id} open={showCompose} onClose={() => setShowCompose(false)} />
            <div className="space-y-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold text-zinc-100">Inbox</h1>
                        <p className="text-sm text-zinc-400">{site.name}</p>
                    </div>
                    <Button size="sm" onClick={() => setShowCompose(true)}>
                        <Pencil className="mr-1.5 h-3.5 w-3.5" />Compose
                    </Button>
                </div>

                {/* Tabs */}
                <div className="flex gap-1 border-b border-zinc-800">
                    {TABS.map((t) => (
                        <button
                            key={t.key}
                            onClick={() => goTab(t.key)}
                            className={cn(
                                'px-4 py-2 text-sm transition-colors border-b-2 -mb-px',
                                tab === t.key
                                    ? 'border-violet-500 text-zinc-100'
                                    : 'border-transparent text-zinc-500 hover:text-zinc-300',
                            )}
                        >{t.label}</button>
                    ))}
                </div>

                <div className="grid grid-cols-1 gap-4 lg:grid-cols-[340px_1fr]">
                    {/* Message list */}
                    <Card className="border-zinc-800 bg-[#1e1e1e]">
                        <CardContent className="p-0">
                            {messages.length === 0 ? (
                                <p className="px-4 py-8 text-center text-sm text-zinc-500">No messages.</p>
                            ) : messages.map((m) => (
                                <button key={m.id}
                                    onClick={() => { setSelected(m.id); if (!m.is_read && m.direction === 'inbound') markRead(m.id); }}
                                    className={cn(
                                        'w-full border-b border-zinc-800/60 px-4 py-3 text-left last:border-0 hover:bg-zinc-800/30 transition-colors',
                                        selected === m.id && 'bg-zinc-800/40',
                                    )}>
                                    <div className="flex items-center gap-2">
                                        {m.direction === 'outbound'
                                            ? <Send className="h-3.5 w-3.5 shrink-0 text-violet-400" />
                                            : m.is_read
                                                ? <MailOpen className="h-3.5 w-3.5 shrink-0 text-zinc-600" />
                                                : <Mail className="h-3.5 w-3.5 shrink-0 text-emerald-400" />}
                                        <span className={cn('flex-1 truncate text-sm', !m.is_read && m.direction === 'inbound' && 'font-medium text-zinc-100', (m.is_read || m.direction === 'outbound') && 'text-zinc-400')}>
                                            {m.direction === 'outbound' ? (m.to_email || 'Unknown') : (m.from_name || m.from_email || 'Unknown')}
                                        </span>
                                        <span className="text-[10px] text-zinc-600">{new Date(m.created_at).toLocaleDateString()}</span>
                                    </div>
                                    <p className="mt-0.5 truncate pl-6 text-xs text-zinc-500">{m.subject || '(no subject)'}</p>
                                </button>
                            ))}
                        </CardContent>
                    </Card>

                    {/* Message detail */}
                    <Card className="border-zinc-800 bg-[#1e1e1e]">
                        <CardContent className="pt-5">
                            {!msg ? (
                                <p className="text-sm text-zinc-500">Select a message to read.</p>
                            ) : (
                                <div className="space-y-4">
                                    <div className="flex items-start justify-between gap-3">
                                        <div className="min-w-0 flex-1">
                                            <p className="font-medium text-zinc-100">{msg.subject || '(no subject)'}</p>
                                            <p className="mt-0.5 text-xs text-zinc-500">
                                                {msg.direction === 'outbound'
                                                    ? `To: ${msg.to_email}`
                                                    : `From: ${msg.from_name ? `${msg.from_name} <${msg.from_email}>` : msg.from_email}`}
                                                {' · '}{new Date(msg.created_at).toLocaleString()}
                                            </p>
                                        </div>
                                        <div className="flex shrink-0 gap-1">
                                            <Button
                                                variant="ghost" size="icon"
                                                className="h-7 w-7 text-zinc-500 hover:text-zinc-200"
                                                title={msg.is_archived ? 'Unarchive' : 'Archive'}
                                                onClick={() => archive(msg.id)}
                                            >
                                                {msg.is_archived ? <ArchiveRestore className="h-3.5 w-3.5" /> : <Archive className="h-3.5 w-3.5" />}
                                            </Button>
                                            <Button variant="ghost" size="icon" className="h-7 w-7 text-red-400 hover:text-red-300" onClick={() => del(msg.id)}>
                                                <Trash2 className="h-3.5 w-3.5" />
                                            </Button>
                                        </div>
                                    </div>
                                    <div className="rounded-md border border-zinc-800 bg-zinc-900/50 p-4">
                                        <pre className="whitespace-pre-wrap text-sm text-zinc-300 font-sans">{msg.body || '(empty)'}</pre>
                                    </div>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
