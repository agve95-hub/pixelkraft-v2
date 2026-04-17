import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/layouts/AppLayout';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
    DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    AlertDialog, AlertDialogContent, AlertDialogHeader, AlertDialogTitle,
    AlertDialogDescription, AlertDialogFooter, AlertDialogAction, AlertDialogCancel,
} from '@/components/ui/alert-dialog';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { Plus, MoreHorizontal, Check, Copy, Download, Trash2 } from 'lucide-react';

interface Site { id: string; name: string; }
interface Invoice {
    id: string;
    number: string | null;
    status: string;
    total: string | null;
    currency_code: string;
    invoice_date: string | null;
    due_date: string | null;
    paid_at: string | null;
    bill_to: string | null;
}

function statusBadge(status: string) {
    if (status === 'paid') return <Badge variant="success">Paid</Badge>;
    if (status === 'unpaid') return <Badge variant="warning">Outstanding</Badge>;
    return <Badge variant="secondary">{status}</Badge>;
}

export default function Invoices({ site, invoices }: { site: Site; invoices: Invoice[] }) {
    const [deleteId, setDeleteId] = useState<string | null>(null);

    const markPaid = (id: string) => router.post(`/dashboard/sites/${site.id}/invoices/${id}/mark-paid`);
    const duplicate = (id: string) => router.post(`/dashboard/sites/${site.id}/invoices/${id}/duplicate`);
    const deleteInvoice = (id: string) => { router.delete(`/dashboard/sites/${site.id}/invoices/${id}`); setDeleteId(null); };

    return (
        <AppLayout title="Invoices">
            <Head title={`Invoices — ${site.name}`} />
            <div className="space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold text-zinc-100">Invoices</h1>
                        <p className="text-sm text-zinc-400">{site.name}</p>
                    </div>
                </div>

                <Card className="border-zinc-800 bg-[#1e1e1e]">
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b border-zinc-800">
                                        {['Number', 'Bill To', 'Date', 'Due', 'Total', 'Status', ''].map((h) => (
                                            <th key={h} className="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-widest text-zinc-500">{h}</th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {invoices.length === 0 ? (
                                        <tr><td colSpan={7} className="px-4 py-8 text-center text-sm text-zinc-500">No invoices yet.</td></tr>
                                    ) : invoices.map((inv) => (
                                        <tr key={inv.id} className="border-b border-zinc-800/60 hover:bg-zinc-800/30">
                                            <td className="px-4 py-3 font-mono text-xs text-zinc-300">{inv.number ?? '—'}</td>
                                            <td className="px-4 py-3 text-zinc-200">{inv.bill_to ?? '—'}</td>
                                            <td className="px-4 py-3 text-zinc-400">{inv.invoice_date ?? '—'}</td>
                                            <td className="px-4 py-3 text-zinc-400">{inv.due_date ?? '—'}</td>
                                            <td className="px-4 py-3 tabular-nums text-zinc-100">
                                                {inv.total ? `${inv.currency_code} ${Number(inv.total).toFixed(2)}` : '—'}
                                            </td>
                                            <td className="px-4 py-3">{statusBadge(inv.status)}</td>
                                            <td className="px-4 py-3">
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger asChild>
                                                        <Button variant="ghost" size="icon" className="h-7 w-7">
                                                            <MoreHorizontal className="h-4 w-4" />
                                                        </Button>
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent align="end" className="border-zinc-700 bg-zinc-900">
                                                        {inv.status !== 'paid' && (
                                                            <DropdownMenuItem onClick={() => markPaid(inv.id)} className="gap-2 text-emerald-400 focus:text-emerald-300">
                                                                <Check className="h-4 w-4" />Mark as paid
                                                            </DropdownMenuItem>
                                                        )}
                                                        <DropdownMenuItem onClick={() => duplicate(inv.id)} className="gap-2">
                                                            <Copy className="h-4 w-4" />Duplicate
                                                        </DropdownMenuItem>
                                                        <DropdownMenuItem asChild className="gap-2">
                                                            <a href={`/dashboard/sites/${site.id}/invoices/${inv.id}/pdf`} target="_blank">
                                                                <Download className="h-4 w-4" />Download PDF
                                                            </a>
                                                        </DropdownMenuItem>
                                                        <DropdownMenuSeparator className="bg-zinc-700" />
                                                        <DropdownMenuItem
                                                            onClick={() => setDeleteId(inv.id)}
                                                            className="gap-2 text-red-400 focus:text-red-300"
                                                        >
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

            <AlertDialog open={!!deleteId} onOpenChange={(open) => !open && setDeleteId(null)}>
                <AlertDialogContent className="border-zinc-800 bg-[#1e1e1e]">
                    <AlertDialogHeader>
                        <AlertDialogTitle className="text-zinc-100">Delete invoice?</AlertDialogTitle>
                        <AlertDialogDescription>This action cannot be undone.</AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel className="border-zinc-700">Cancel</AlertDialogCancel>
                        <AlertDialogAction
                            className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                            onClick={() => deleteId && deleteInvoice(deleteId)}
                        >
                            Delete
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </AppLayout>
    );
}
