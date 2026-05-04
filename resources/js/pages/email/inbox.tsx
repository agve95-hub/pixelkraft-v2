import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { Link } from '@inertiajs/react';
import AppLayout from '@/layouts/AppLayout';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import { Mail, MailOpen, Archive, Trash2 } from 'lucide-react';

interface Site { id: string; name: string; }
interface Message {
    id: string;
    site_id: string;
    site: Site | null;
    direction: 'inbound' | 'outbound';
    from_email: string | null;
    from_name: string | null;
    to_email: string | null;
    subject: string | null;
    body: string | null;
    is_read: boolean;
    is_archived: boolean;
    source: string | null;
    created_at: string;
}

const TABS = [
    { key: 'inbox', label: 'Inbox' },
    { key: 'sent', label: 'Sent' },
    { key: 'archived', label: 'Archived' },
];

function formatDate(dt: string) {
    const d = new Date(dt);
    const now = new Date();
    const isToday = d.toDateString() === now.toDateString();
    return isToday
        ? d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
        : d.toLocaleDateString([], { month: 'short', day: 'numeric' });
}

export default function EmailInbox({
    messages = [],
    tab = 'inbox',
    unreadCount = 0,
}: {
    messages: Message[];
    tab: string;
    unreadCount: number;
}) {
    const [selected, setSelected] = useState<Message | null>(null);

    const markRead = (m: Message) => {
        if (!m.is_read) {
            router.post(`/dashboard/sites/${m.site_id}/inbox/${m.id}/read`, {}, { preserveScroll: true });
        }
        setSelected(m);
    };

    const archive = (m: Message) => {
        router.post(`/dashboard/sites/${m.site_id}/inbox/${m.id}/archive`, {}, { preserveScroll: true });
        if (selected?.id === m.id) setSelected(null);
    };

    const destroy = (m: Message) => {
        router.delete(`/dashboard/sites/${m.site_id}/inbox/${m.id}`, { preserveScroll: true });
        if (selected?.id === m.id) setSelected(null);
    };

    return (
        <AppLayout title="Inbox">
            <Head title="Inbox" />
            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <h1 className="text-xl font-semibold text-zinc-100">Inbox</h1>
                        {unreadCount > 0 && (
                            <Badge className="bg-blue-600 text-white">{unreadCount} unread</Badge>
                        )}
                    </div>
                </div>

                {/* Tabs */}
                <div className="flex gap-1 border-b border-zinc-800 pb-0">
                    {TABS.map((t) => (
                        <Link
                            key={t.key}
                            href={`/dashboard/inbox?tab=${t.key}`}
                            className={cn(
                                'px-4 py-2 text-sm font-medium transition-colors',
                                tab === t.key
                                    ? 'border-b-2 border-zinc-100 text-zinc-100'
                                    : 'text-zinc-500 hover:text-zinc-300',
                            )}
                        >
                            {t.label}
                        </Link>
                    ))}
                </div>

                <div className="grid grid-cols-1 gap-4 lg:grid-cols-[360px_1fr]">
                    {/* Message list */}
                    <Card className="border-zinc-800 bg-[#1e1e1e] h-fit">
                        <CardContent className="p-0">
                            {messages.length === 0 ? (
                                <p className="px-4 py-10 text-center text-sm text-zinc-500">No messages.</p>
                            ) : (
                                <div className="divide-y divide-zinc-800">
                                    {messages.map((m) => (
                                        <button
                                            key={m.id}
                                            onClick={() => markRead(m)}
                                            className={cn(
                                                'w-full text-left px-4 py-3 transition-colors hover:bg-zinc-800/40',
                                                selected?.id === m.id && 'bg-zinc-800/60',
                                            )}
                                        >
                                            <div className="flex items-start justify-between gap-2">
                                                <div className="flex items-center gap-2 min-w-0">
                                                    {!m.is_read && m.direction === 'inbound' && (
                                                        <span className="h-2 w-2 shrink-0 rounded-full bg-blue-500" />
                                                    )}
                                                    <span className={cn('truncate text-sm', !m.is_read ? 'font-medium text-zinc-100' : 'text-zinc-300')}>
                                                        {m.direction === 'inbound' ? (m.from_name || m.from_email || 'Unknown') : (m.to_email || 'Unknown')}
                                                    </span>
                                                </div>
                                                <span className="shrink-0 text-xs text-zinc-500">{formatDate(m.created_at)}</span>
                                            </div>
                                            <p className="mt-0.5 truncate text-xs text-zinc-500">{m.subject || '(no subject)'}</p>
                                            {m.site && (
                                                <Badge variant="secondary" className="mt-1 text-[10px]">{m.site.name}</Badge>
                                            )}
                                        </button>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Message detail */}
                    {selected ? (
                        <Card className="border-zinc-800 bg-[#1e1e1e]">
                            <CardContent className="p-5 space-y-4">
                                <div className="flex items-start justify-between gap-4">
                                    <div className="space-y-0.5">
                                        <h2 className="text-base font-semibold text-zinc-100">{selected.subject || '(no subject)'}</h2>
                                        <p className="text-xs text-zinc-400">
                                            {selected.direction === 'inbound'
                                                ? `From: ${selected.from_name ? `${selected.from_name} <${selected.from_email}>` : selected.from_email}`
                                                : `To: ${selected.to_email}`}
                                        </p>
                                        <p className="text-xs text-zinc-500">{new Date(selected.created_at).toLocaleString()}</p>
                                    </div>
                                    <div className="flex shrink-0 gap-1">
                                        {selected.direction === 'inbound' && !selected.is_read && (
                                            <Button variant="ghost" size="icon" className="h-7 w-7" title="Mark read" onClick={() => router.post(`/dashboard/sites/${selected.site_id}/inbox/${selected.id}/read`, {}, { preserveScroll: true })}>
                                                <MailOpen className="h-3.5 w-3.5" />
                                            </Button>
                                        )}
                                        <Button variant="ghost" size="icon" className="h-7 w-7" title="Archive" onClick={() => archive(selected)}>
                                            <Archive className="h-3.5 w-3.5" />
                                        </Button>
                                        <Button variant="ghost" size="icon" className="h-7 w-7 text-red-400 hover:text-red-300" title="Delete" onClick={() => destroy(selected)}>
                                            <Trash2 className="h-3.5 w-3.5" />
                                        </Button>
                                    </div>
                                </div>
                                <div className="rounded-lg border border-zinc-800 bg-zinc-900 p-4">
                                    <pre className="whitespace-pre-wrap text-sm text-zinc-300 font-sans leading-relaxed">{selected.body || '(empty message)'}</pre>
                                </div>
                                {selected.site && (
                                    <p className="text-xs text-zinc-500">
                                        Site: <Link href={`/dashboard/sites/${selected.site_id}/inbox`} className="text-zinc-400 underline hover:text-zinc-200">{selected.site.name}</Link>
                                    </p>
                                )}
                            </CardContent>
                        </Card>
                    ) : (
                        <div className="hidden lg:flex items-center justify-center rounded-xl border border-zinc-800 bg-[#1e1e1e] text-zinc-600">
                            <div className="text-center space-y-2">
                                <Mail className="mx-auto h-8 w-8" />
                                <p className="text-sm">Select a message</p>
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
