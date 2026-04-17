import { Head } from '@inertiajs/react';
import AppLayout from '@/layouts/AppLayout';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Server } from 'lucide-react';

interface DiagnosticItem { label: string; value: string; status: 'ok' | 'warn' | 'error'; }

export default function SystemDiagnostics({ diagnostics = [] }: { diagnostics?: DiagnosticItem[] }) {
    return (
        <AppLayout title="System">
            <Head title="System diagnostics" />
            <div className="space-y-6">
                <div className="flex items-center gap-2">
                    <Server className="h-5 w-5 text-zinc-400" />
                    <h1 className="text-xl font-semibold text-zinc-100">System diagnostics</h1>
                </div>

                <Card className="border-zinc-800 bg-[#1e1e1e]">
                    <CardHeader>
                        <CardTitle className="text-sm font-medium text-zinc-300">Environment</CardTitle>
                    </CardHeader>
                    <CardContent className="p-0">
                        {diagnostics.length === 0 ? (
                            <p className="px-4 py-6 text-sm text-zinc-500">No diagnostics available.</p>
                        ) : diagnostics.map((d, i) => (
                            <div key={i} className="flex items-center justify-between border-b border-zinc-800/60 px-4 py-3 last:border-0">
                                <span className="text-sm text-zinc-300">{d.label}</span>
                                <div className="flex items-center gap-2">
                                    <span className="font-mono text-xs text-zinc-400">{d.value}</span>
                                    <Badge variant={d.status === 'ok' ? 'success' : d.status === 'warn' ? 'warning' : 'destructive'}>
                                        {d.status}
                                    </Badge>
                                </div>
                            </div>
                        ))}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
