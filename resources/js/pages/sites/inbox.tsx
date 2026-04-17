import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/layouts/AppLayout';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Mail, MailOpen, Trash2 } from 'lucide-react';
import { cn } from '@/lib/utils';

interface Site { id: string; name: string; }
interface Message {
    id: string; direction: string; from_email: string | null; from_name: string | null;
    subject: string | null; body: string | null; is_read: boolean; created_at: string;
}

export default function SiteInbox({ site, messages }: { site: Site; messages: Message[] }) {
    const [selected, setSelected] = useState<string | null>(null);
    const msg = messages.find((m) => m.id === selected);

    const markRead = (id: string) => router.post(`/dashboard/sites/${site.id}/inbox/${id}/read`);
    const del = (id: string) => { router.delete(`/dashboard/sites/${site.id}/inbox/${id}`); setSelected(null); };

    return (
        <AppLayout title="Inbox">
            <Head title={`Inbox — ${site.name}`} />
            <div className="space-y-4">
                <div>
                    <h1 className="text-xl font-semibold text-zinc-100">Inbox</h1>
                    <p className="text-sm text-zinc-400">{site.name}</p>
                </div>
                <div className="grid grid-cols-1 gap-4 lg:grid-cols-[340px_1fr]">
                    <Card className="border-zinc-800 bg-[#1e1e1e]">
                        <CardContent className="p-0">
                            {messages.length === 0 ? (
                                <p className="px-4 py-8 text-center text-sm text-zinc-500">No messages.</p>
                            ) : messages.map((m) => (
                                <button key={m.id}
                                    onClick={() => { setSelected(m.id); if (!m.is_read) markRead(m.id); }}
                                    className={cn(
                                        'w-full border-b border-zinc-800/60 px-4 py-3 text-left last:border-0 hover:bg-zinc-800/30 transition-colors',
                                        selected === m.id && 'bg-zinc-800/40',
                                    )}>
                                    <div className="flex items-center gap-2">
                                        {m.is_read ? <MailOpen className="h-3.5 w-3.5 shrink-0 text-zinc-600" /> : <Mail className="h-3.5 w-3.5 shrink-0 text-emerald-400" />}
                                        <span className={cn('flex-1 truncate text-sm', !m.is_read && 'font-medium text-zinc-100', m.is_read && 'text-zinc-400')}>
                                            {m.from_name || m.from_email || 'Unknown'}
                                        </span>
                                        <span className="text-[10px] text-zinc-600">{new Date(m.created_at).toLocaleDateString()}</span>
                                    </div>
                                    <p className="mt-0.5 truncate pl-6 text-xs text-zinc-500">{m.subject || '(no subject)'}</p>
                                </button>
                            ))}
                        </CardContent>
                    </Card>

                    <Card className="border-zinc-800 bg-[#1e1e1e]">
                        <CardContent className="pt-5">
                            {!msg ? (
                                <p className="text-sm text-zinc-500">Select a message to read.</p>
                            ) : (
                                <div className="space-y-4">
                                    <div className="flex items-start justify-between gap-3">
                                        <div>
                                            <p className="font-medium text-zinc-100">{msg.subject || '(no subject)'}</p>
                                            <p className="mt-0.5 text-xs text-zinc-500">
                                                From: {msg.from_name ? `${msg.from_name} <${msg.from_email}>` : msg.from_email} · {new Date(msg.created_at).toLocaleString()}
                                            </p>
                                        </div>
                                        <div className="flex gap-1">
                                            <Badge variant="secondary" className="text-[10px]">{msg.direction}</Badge>
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
