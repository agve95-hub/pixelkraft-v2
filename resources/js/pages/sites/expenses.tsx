import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/layouts/AppLayout';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import { Badge } from '@/components/ui/badge';
import {
    AlertDialog, AlertDialogContent, AlertDialogHeader, AlertDialogTitle,
    AlertDialogDescription, AlertDialogFooter, AlertDialogAction, AlertDialogCancel,
} from '@/components/ui/alert-dialog';
import { Plus, Pencil, Trash2, Download, Check, X } from 'lucide-react';

interface Site { id: string; name: string; }
interface Expense { id: string; label: string; amount: string; currency: string; expense_date: string; }
interface CurrencyTotal { currency: string; total: string; }

function fmt(amount: string, currency: string) {
    try { return new Intl.NumberFormat('en-US', { style: 'currency', currency }).format(Number(amount)); }
    catch { return `${currency} ${Number(amount).toFixed(2)}`; }
}

function AddForm({ siteId, onClose }: { siteId: string; onClose: () => void }) {
    const { data, setData, post, processing, errors, reset } = useForm({ label: '', amount: '', currency: 'EUR', expense_date: new Date().toISOString().slice(0, 10) });
    return (
        <form onSubmit={(e) => { e.preventDefault(); post(`/dashboard/sites/${siteId}/expenses`, { onSuccess: () => { reset(); onClose(); } }); }} className="grid grid-cols-2 gap-3 md:grid-cols-4">
            <div className="space-y-1">
                <Label className="text-xs text-zinc-400">Label</Label>
                <Input value={data.label} onChange={(e) => setData('label', e.target.value)} placeholder="Hosting fee" className="border-zinc-700 bg-zinc-900 text-zinc-100" />
                {errors.label && <p className="text-xs text-red-400">{errors.label}</p>}
            </div>
            <div className="space-y-1">
                <Label className="text-xs text-zinc-400">Amount</Label>
                <Input type="number" step="0.01" value={data.amount} onChange={(e) => setData('amount', e.target.value)} placeholder="0.00" className="border-zinc-700 bg-zinc-900 text-zinc-100" />
            </div>
            <div className="space-y-1">
                <Label className="text-xs text-zinc-400">Currency</Label>
                <Input value={data.currency} onChange={(e) => setData('currency', e.target.value.toUpperCase())} maxLength={3} className="border-zinc-700 bg-zinc-900 text-zinc-100" />
            </div>
            <div className="space-y-1">
                <Label className="text-xs text-zinc-400">Date</Label>
                <Input type="date" value={data.expense_date} onChange={(e) => setData('expense_date', e.target.value)} className="border-zinc-700 bg-zinc-900 text-zinc-100" />
            </div>
            <div className="col-span-full flex gap-2">
                <Button type="submit" size="sm" disabled={processing}><Plus className="mr-1.5 h-3.5 w-3.5" />Add</Button>
                <Button type="button" variant="ghost" size="sm" onClick={onClose}>Cancel</Button>
            </div>
        </form>
    );
}

function EditRow({ expense, siteId, onCancel }: { expense: Expense; siteId: string; onCancel: () => void }) {
    const { data, setData, put, processing } = useForm({ label: expense.label, amount: expense.amount, currency: expense.currency, expense_date: expense.expense_date });
    return (
        <tr className="border-b border-zinc-800 bg-zinc-800/40">
            <td className="px-3 py-2"><Checkbox disabled /></td>
            <td className="px-3 py-2" colSpan={4}>
                <form onSubmit={(e) => { e.preventDefault(); put(`/dashboard/sites/${siteId}/expenses/${expense.id}`, { onSuccess: onCancel }); }} className="flex flex-wrap items-center gap-2">
                    <Input value={data.label} onChange={(e) => setData('label', e.target.value)} className="h-7 w-44 border-zinc-600 bg-zinc-900 text-xs text-zinc-100" />
                    <Input type="number" step="0.01" value={data.amount} onChange={(e) => setData('amount', e.target.value)} className="h-7 w-24 border-zinc-600 bg-zinc-900 text-xs text-zinc-100" />
                    <Input value={data.currency} onChange={(e) => setData('currency', e.target.value.toUpperCase())} maxLength={3} className="h-7 w-16 border-zinc-600 bg-zinc-900 text-xs text-zinc-100" />
                    <Input type="date" value={data.expense_date} onChange={(e) => setData('expense_date', e.target.value)} className="h-7 w-32 border-zinc-600 bg-zinc-900 text-xs text-zinc-100" />
                    <Button type="submit" size="sm" className="h-7 px-2" disabled={processing}><Check className="h-3 w-3" /></Button>
                    <Button type="button" variant="ghost" size="sm" className="h-7 px-2" onClick={onCancel}><X className="h-3 w-3" /></Button>
                </form>
            </td>
        </tr>
    );
}

export default function Expenses({ site, expenses, totals }: { site: Site; expenses: Expense[]; totals: CurrencyTotal[] }) {
    const [selected, setSelected] = useState<Set<string>>(new Set());
    const [editingId, setEditingId] = useState<string | null>(null);
    const [deleteId, setDeleteId] = useState<string | null>(null);
    const [bulkDeleteOpen, setBulkDeleteOpen] = useState(false);
    const [showAdd, setShowAdd] = useState(false);

    const toggleAll = () => selected.size === expenses.length ? setSelected(new Set()) : setSelected(new Set(expenses.map((e) => e.id)));
    const toggleOne = (id: string) => setSelected((prev) => { const s = new Set(prev); s.has(id) ? s.delete(id) : s.add(id); return s; });
    const deleteOne = (id: string) => { router.delete(`/dashboard/sites/${site.id}/expenses/${id}`); setDeleteId(null); };
    const bulkDelete = () => { router.delete(`/dashboard/sites/${site.id}/expenses`, { data: { ids: [...selected] }, onSuccess: () => setSelected(new Set()) }); setBulkDeleteOpen(false); };
    const exportCsv = () => {
        const a = document.createElement('a');
        a.href = URL.createObjectURL(new Blob([`Label,Amount,Currency,Date\n${expenses.map((e) => `"${e.label}",${e.amount},${e.currency},${e.expense_date}`).join('\n')}`], { type: 'text/csv' }));
        a.download = `expenses-${site.name}.csv`;
        a.click();
    };

    return (
        <AppLayout title="Expenses">
            <Head title={`Expenses — ${site.name}`} />
            <div className="space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold text-zinc-100">Expenses</h1>
                        <p className="text-sm text-zinc-400">{site.name}</p>
                    </div>
                    <div className="flex gap-2">
                        <Button variant="outline" size="sm" onClick={exportCsv} className="border-zinc-700 text-zinc-300">
                            <Download className="mr-1.5 h-3.5 w-3.5" />Export CSV
                        </Button>
                        <Button size="sm" onClick={() => setShowAdd((v) => !v)}>
                            <Plus className="mr-1.5 h-3.5 w-3.5" />Add Expense
                        </Button>
                    </div>
                </div>

                {totals.length > 0 && (
                    <div className="flex flex-wrap gap-3">
                        {totals.map((t) => (
                            <div key={t.currency} className="rounded-lg border border-zinc-800 bg-[#1e1e1e] px-4 py-2">
                                <p className="text-xs text-zinc-500">{t.currency}</p>
                                <p className="text-lg font-semibold tabular-nums text-zinc-100">{fmt(t.total, t.currency)}</p>
                            </div>
                        ))}
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
                        <div className="flex items-center justify-between">
                            <CardTitle className="text-sm font-medium text-zinc-300">
                                {expenses.length} record{expenses.length !== 1 ? 's' : ''}
                            </CardTitle>
                            {selected.size > 0 && (
                                <Button variant="destructive" size="sm" onClick={() => setBulkDeleteOpen(true)}>
                                    <Trash2 className="mr-1.5 h-3.5 w-3.5" />Delete {selected.size}
                                </Button>
                            )}
                        </div>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b border-zinc-800">
                                        <th className="w-10 px-3 py-2.5">
                                            <Checkbox checked={selected.size === expenses.length && expenses.length > 0} onCheckedChange={toggleAll} />
                                        </th>
                                        {['Label', 'Amount', 'Currency', 'Date', 'Actions'].map((h) => (
                                            <th key={h} className="px-3 py-2.5 text-left text-xs font-medium uppercase tracking-widest text-zinc-500">{h}</th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {expenses.length === 0 ? (
                                        <tr><td colSpan={6} className="px-3 py-8 text-center text-sm text-zinc-500">No expenses yet.</td></tr>
                                    ) : expenses.map((e) =>
                                        editingId === e.id ? (
                                            <EditRow key={e.id} expense={e} siteId={site.id} onCancel={() => setEditingId(null)} />
                                        ) : (
                                            <tr key={e.id} className="border-b border-zinc-800/60 hover:bg-zinc-800/30">
                                                <td className="px-3 py-2.5">
                                                    <Checkbox checked={selected.has(e.id)} onCheckedChange={() => toggleOne(e.id)} />
                                                </td>
                                                <td className="px-3 py-2.5 text-zinc-200">{e.label}</td>
                                                <td className="px-3 py-2.5 tabular-nums text-zinc-100">{fmt(e.amount, e.currency)}</td>
                                                <td className="px-3 py-2.5"><Badge variant="secondary">{e.currency}</Badge></td>
                                                <td className="px-3 py-2.5 text-zinc-400">{e.expense_date}</td>
                                                <td className="px-3 py-2.5">
                                                    <div className="flex gap-1">
                                                        <Button variant="ghost" size="icon" className="h-7 w-7" onClick={() => setEditingId(e.id)}>
                                                            <Pencil className="h-3.5 w-3.5" />
                                                        </Button>
                                                        <Button variant="ghost" size="icon" className="h-7 w-7 text-red-400 hover:text-red-300" onClick={() => setDeleteId(e.id)}>
                                                            <Trash2 className="h-3.5 w-3.5" />
                                                        </Button>
                                                    </div>
                                                </td>
                                            </tr>
                                        ),
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <AlertDialog open={!!deleteId} onOpenChange={(open) => !open && setDeleteId(null)}>
                <AlertDialogContent className="border-zinc-800 bg-[#1e1e1e]">
                    <AlertDialogHeader>
                        <AlertDialogTitle className="text-zinc-100">Delete expense?</AlertDialogTitle>
                        <AlertDialogDescription>This cannot be undone.</AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel className="border-zinc-700">Cancel</AlertDialogCancel>
                        <AlertDialogAction className="bg-destructive text-destructive-foreground hover:bg-destructive/90" onClick={() => deleteId && deleteOne(deleteId)}>
                            Delete
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>

            <AlertDialog open={bulkDeleteOpen} onOpenChange={setBulkDeleteOpen}>
                <AlertDialogContent className="border-zinc-800 bg-[#1e1e1e]">
                    <AlertDialogHeader>
                        <AlertDialogTitle className="text-zinc-100">Delete {selected.size} expenses?</AlertDialogTitle>
                        <AlertDialogDescription>This cannot be undone.</AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel className="border-zinc-700">Cancel</AlertDialogCancel>
                        <AlertDialogAction className="bg-destructive text-destructive-foreground hover:bg-destructive/90" onClick={bulkDelete}>
                            Delete all
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </AppLayout>
    );
}
