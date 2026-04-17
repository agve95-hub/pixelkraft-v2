import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/layouts/AppLayout';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
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
interface Report { id: string; title: string; report_date: string; summary: string | null; }

type FormData = { title: string; report_date: string; summary: string; };

function ReportFields({ data, setData, errors }: { data: FormData; setData: (k: keyof FormData, v: string) => void; errors: Partial<Record<keyof FormData, string>>; }) {
    return (
        <div className="space-y-3">
            <div className="space-y-1">
                <Label className="text-xs text-zinc-400">Title *</Label>
                <Input value={data.title} onChange={(e) => setData('title', e.target.value)} className="border-zinc-700 bg-zinc-900 text-zinc-100" />
                {errors.title && <p className="text-xs text-red-400">{errors.title}</p>}
            </div>
            <div className="space-y-1">
                <Label className="text-xs text-zinc-400">Report date *</Label>
                <Input type="date" value={data.report_date} onChange={(e) => setData('report_date', e.target.value)} className="border-zinc-700 bg-zinc-900 text-zinc-100" />
                {errors.report_date && <p className="text-xs text-red-400">{errors.report_date}</p>}
            </div>
            <div className="space-y-1">
                <Label className="text-xs text-zinc-400">Summary</Label>
                <Textarea value={data.summary} onChange={(e) => setData('summary', e.target.value)} rows={4} className="border-zinc-700 bg-zinc-900 text-zinc-100" />
            </div>
        </div>
    );
}

const today = new Date().toISOString().slice(0, 10);

function AddDialog({ siteId, open, onClose }: { siteId: string; open: boolean; onClose: () => void }) {
    const { data, setData, post, processing, errors, reset } = useForm<FormData>({ title: '', report_date: today, summary: '' });
    const handleClose = () => { reset(); onClose(); };
    return (
        <Dialog open={open} onOpenChange={(o) => !o && handleClose()}>
            <DialogContent className="border-zinc-800 bg-[#1e1e1e] max-w-lg">
                <DialogHeader><DialogTitle className="text-zinc-100">New Report</DialogTitle></DialogHeader>
                <form onSubmit={(e) => { e.preventDefault(); post(`/dashboard/sites/${siteId}/reports`, { onSuccess: handleClose }); }}>
                    <ReportFields data={data} setData={setData} errors={errors} />
                    <DialogFooter className="mt-4">
                        <Button type="button" variant="ghost" onClick={handleClose}>Cancel</Button>
                        <Button type="submit" disabled={processing}><Plus className="mr-1.5 h-3.5 w-3.5" />Create</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function EditDialog({ report, siteId, onClose }: { report: Report; siteId: string; onClose: () => void }) {
    const { data, setData, put, processing, errors } = useForm<FormData>({
        title: report.title,
        report_date: report.report_date,
        summary: report.summary ?? '',
    });
    return (
        <Dialog open onOpenChange={(o) => !o && onClose()}>
            <DialogContent className="border-zinc-800 bg-[#1e1e1e] max-w-lg">
                <DialogHeader><DialogTitle className="text-zinc-100">Edit Report</DialogTitle></DialogHeader>
                <form onSubmit={(e) => { e.preventDefault(); put(`/dashboard/sites/${siteId}/reports/${report.id}`, { onSuccess: onClose }); }}>
                    <ReportFields data={data} setData={setData} errors={errors} />
                    <DialogFooter className="mt-4">
                        <Button type="button" variant="ghost" onClick={onClose}>Cancel</Button>
                        <Button type="submit" disabled={processing}>Save changes</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function Reports({ site, reports }: { site: Site; reports: Report[] }) {
    const [showAdd, setShowAdd] = useState(false);
    const [editReport, setEditReport] = useState<Report | null>(null);
    const [deleteId, setDeleteId] = useState<string | null>(null);

    const duplicate = (id: string) => router.post(`/dashboard/sites/${site.id}/reports/${id}/duplicate`);
    const deleteReport = (id: string) => { router.delete(`/dashboard/sites/${site.id}/reports/${id}`); setDeleteId(null); };

    return (
        <AppLayout title="Reports">
            <Head title={`Reports — ${site.name}`} />
            <div className="space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold text-zinc-100">Reports</h1>
                        <p className="text-sm text-zinc-400">{site.name}</p>
                    </div>
                    <Button size="sm" onClick={() => setShowAdd(true)}>
                        <Plus className="mr-1.5 h-3.5 w-3.5" />New Report
                    </Button>
                </div>

                <Card className="border-zinc-800 bg-[#1e1e1e]">
                    <CardContent className="p-0">
                        {reports.length === 0 ? (
                            <div className="py-8 text-center text-sm text-zinc-500">No reports yet.</div>
                        ) : (
                            <div className="divide-y divide-zinc-800/60">
                                {reports.map((r) => (
                                    <div key={r.id} className="flex items-start gap-4 px-4 py-3 hover:bg-zinc-800/20">
                                        <div className="flex-1 min-w-0">
                                            <p className="font-medium text-zinc-100">{r.title}</p>
                                            <p className="mt-0.5 text-xs text-zinc-500">{new Date(r.report_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}</p>
                                            {r.summary && (
                                                <p className="mt-1.5 text-sm text-zinc-400 line-clamp-2">{r.summary}</p>
                                            )}
                                        </div>
                                        <DropdownMenu>
                                            <DropdownMenuTrigger asChild>
                                                <Button variant="ghost" size="icon" className="h-7 w-7 shrink-0">
                                                    <MoreHorizontal className="h-4 w-4" />
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end" className="border-zinc-700 bg-zinc-900">
                                                <DropdownMenuItem onClick={() => setEditReport(r)} className="gap-2">
                                                    <Pencil className="h-4 w-4" />Edit
                                                </DropdownMenuItem>
                                                <DropdownMenuItem onClick={() => duplicate(r.id)} className="gap-2">
                                                    <Copy className="h-4 w-4" />Duplicate
                                                </DropdownMenuItem>
                                                <DropdownMenuSeparator className="bg-zinc-700" />
                                                <DropdownMenuItem onClick={() => setDeleteId(r.id)} className="gap-2 text-red-400 focus:text-red-300">
                                                    <Trash2 className="h-4 w-4" />Delete
                                                </DropdownMenuItem>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>

            <AddDialog siteId={site.id} open={showAdd} onClose={() => setShowAdd(false)} />
            {editReport && <EditDialog report={editReport} siteId={site.id} onClose={() => setEditReport(null)} />}

            <AlertDialog open={!!deleteId} onOpenChange={(open) => !open && setDeleteId(null)}>
                <AlertDialogContent className="border-zinc-800 bg-[#1e1e1e]">
                    <AlertDialogHeader>
                        <AlertDialogTitle className="text-zinc-100">Delete report?</AlertDialogTitle>
                        <AlertDialogDescription>This cannot be undone.</AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel className="border-zinc-700">Cancel</AlertDialogCancel>
                        <AlertDialogAction
                            className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                            onClick={() => deleteId && deleteReport(deleteId)}
                        >Delete</AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </AppLayout>
    );
}
