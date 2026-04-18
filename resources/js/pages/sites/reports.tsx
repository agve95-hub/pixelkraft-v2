import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/layouts/AppLayout';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
    Sheet, SheetContent, SheetHeader, SheetTitle, SheetFooter,
} from '@/components/ui/sheet';
import {
    AlertDialog, AlertDialogContent, AlertDialogHeader, AlertDialogTitle,
    AlertDialogDescription, AlertDialogFooter, AlertDialogAction, AlertDialogCancel,
} from '@/components/ui/alert-dialog';
import {
    DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Plus, MoreHorizontal, Pencil, Copy, Trash2, TrendingUp, ShieldCheck, FileText, Users } from 'lucide-react';

interface Site { id: string; name: string; }
interface ReportMeta {
    visitors?: number | null;
    pageviews?: number | null;
    uptime_percent?: number | null;
    work_done?: string | null;
    issues?: string | null;
    next_steps?: string | null;
}
interface Report { id: string; title: string; report_date: string; summary: string | null; meta: ReportMeta | null; }

type FormData = {
    title: string; report_date: string; summary: string;
    'meta.visitors': string; 'meta.pageviews': string; 'meta.uptime_percent': string;
    'meta.work_done': string; 'meta.issues': string; 'meta.next_steps': string;
};

const today = new Date().toISOString().slice(0, 10);

function buildForm(report?: Report): FormData {
    const m = report?.meta ?? {};
    return {
        title: report?.title ?? '',
        report_date: report?.report_date ?? today,
        summary: report?.summary ?? '',
        'meta.visitors': m.visitors != null ? String(m.visitors) : '',
        'meta.pageviews': m.pageviews != null ? String(m.pageviews) : '',
        'meta.uptime_percent': m.uptime_percent != null ? String(m.uptime_percent) : '',
        'meta.work_done': m.work_done ?? '',
        'meta.issues': m.issues ?? '',
        'meta.next_steps': m.next_steps ?? '',
    };
}

function ReportSheet({ siteId, report, onClose }: { siteId: string; report: Report | null; onClose: () => void }) {
    const isEdit = report !== null;
    const { data, setData, post, put, processing, errors } = useForm<FormData>(buildForm(report ?? undefined));
    const s = (k: keyof FormData) => (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) => setData(k, e.target.value);
    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        const opts = { onSuccess: onClose };
        if (isEdit) put(`/dashboard/sites/${siteId}/reports/${report!.id}`, opts);
        else post(`/dashboard/sites/${siteId}/reports`, opts);
    };
    return (
        <Sheet open onOpenChange={(o) => !o && onClose()}>
            <SheetContent className="border-zinc-800 bg-[#1e1e1e] w-full sm:max-w-2xl overflow-y-auto">
                <SheetHeader className="mb-4">
                    <SheetTitle className="text-zinc-100">{isEdit ? 'Edit Report' : 'New Report'}</SheetTitle>
                </SheetHeader>
                <form onSubmit={submit} className="space-y-6">
                    {/* Header */}
                    <div className="grid grid-cols-2 gap-3">
                        <div className="col-span-2 space-y-1">
                            <Label className="text-xs text-zinc-400">Title *</Label>
                            <Input value={data.title} onChange={s('title')} className="border-zinc-700 bg-zinc-900 text-zinc-100" />
                            {errors.title && <p className="text-xs text-red-400">{errors.title}</p>}
                        </div>
                        <div className="space-y-1">
                            <Label className="text-xs text-zinc-400">Report date *</Label>
                            <Input type="date" value={data.report_date} onChange={s('report_date')} className="border-zinc-700 bg-zinc-900 text-zinc-100" />
                        </div>
                        <div className="space-y-1">
                            <Label className="text-xs text-zinc-400">Uptime %</Label>
                            <Input type="number" min="0" max="100" step="0.1" value={data['meta.uptime_percent']} onChange={s('meta.uptime_percent')} placeholder="99.9" className="border-zinc-700 bg-zinc-900 text-zinc-100" />
                        </div>
                    </div>

                    {/* Summary */}
                    <div className="space-y-1">
                        <Label className="text-xs text-zinc-400">Executive summary</Label>
                        <Textarea value={data.summary} onChange={s('summary')} rows={3} placeholder="High-level summary of the reporting period…" className="border-zinc-700 bg-zinc-900 text-zinc-100" />
                    </div>

                    {/* Traffic metrics */}
                    <div>
                        <p className="mb-2 flex items-center gap-1.5 text-xs font-medium uppercase tracking-wider text-zinc-500">
                            <TrendingUp className="h-3.5 w-3.5" />Traffic metrics
                        </p>
                        <div className="grid grid-cols-2 gap-3">
                            <div className="space-y-1">
                                <Label className="text-xs text-zinc-400">Total visitors</Label>
                                <Input type="number" min="0" value={data['meta.visitors']} onChange={s('meta.visitors')} placeholder="0" className="border-zinc-700 bg-zinc-900 text-zinc-100" />
                            </div>
                            <div className="space-y-1">
                                <Label className="text-xs text-zinc-400">Page views</Label>
                                <Input type="number" min="0" value={data['meta.pageviews']} onChange={s('meta.pageviews')} placeholder="0" className="border-zinc-700 bg-zinc-900 text-zinc-100" />
                            </div>
                        </div>
                    </div>

                    {/* Work done */}
                    <div className="space-y-1">
                        <Label className="mb-1 flex items-center gap-1.5 text-xs font-medium uppercase tracking-wider text-zinc-500">
                            <FileText className="h-3.5 w-3.5" />Work completed
                        </Label>
                        <Textarea value={data['meta.work_done']} onChange={s('meta.work_done')} rows={4} placeholder="— Updated homepage copy&#10;— Fixed mobile nav bug&#10;— Deployed new blog section" className="border-zinc-700 bg-zinc-900 text-zinc-100 text-sm" />
                    </div>

                    {/* Issues */}
                    <div className="space-y-1">
                        <Label className="mb-1 text-xs text-zinc-400">Issues / blockers</Label>
                        <Textarea value={data['meta.issues']} onChange={s('meta.issues')} rows={3} placeholder="Any outstanding issues or blockers…" className="border-zinc-700 bg-zinc-900 text-zinc-100 text-sm" />
                    </div>

                    {/* Next steps */}
                    <div className="space-y-1">
                        <Label className="mb-1 text-xs text-zinc-400">Next steps / recommendations</Label>
                        <Textarea value={data['meta.next_steps']} onChange={s('meta.next_steps')} rows={3} placeholder="Planned work for the next period…" className="border-zinc-700 bg-zinc-900 text-zinc-100 text-sm" />
                    </div>

                    <SheetFooter>
                        <Button type="button" variant="ghost" onClick={onClose}>Cancel</Button>
                        <Button type="submit" disabled={processing}>{isEdit ? 'Save changes' : <><Plus className="mr-1.5 h-3.5 w-3.5" />Create report</>}</Button>
                    </SheetFooter>
                </form>
            </SheetContent>
        </Sheet>
    );
}

export default function Reports({ site, reports }: { site: Site; reports: Report[] }) {
    const [sheetReport, setSheetReport] = useState<Report | 'new' | null>(null);
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
                    <Button size="sm" onClick={() => setSheetReport('new')}>
                        <Plus className="mr-1.5 h-3.5 w-3.5" />New Report
                    </Button>
                </div>

                <div className="space-y-3">
                    {reports.length === 0 ? (
                        <Card className="border-zinc-800 bg-[#1e1e1e]">
                            <CardContent className="py-8 text-center text-sm text-zinc-500">No reports yet.</CardContent>
                        </Card>
                    ) : reports.map((r) => {
                        const m = r.meta ?? {};
                        return (
                            <Card key={r.id} className="border-zinc-800 bg-[#1e1e1e] hover:border-zinc-700 transition-colors">
                                <CardContent className="p-4">
                                    <div className="flex items-start gap-4">
                                        <div className="flex-1 min-w-0">
                                            <div className="flex flex-wrap items-center gap-3">
                                                <p className="font-medium text-zinc-100">{r.title}</p>
                                                <p className="text-xs text-zinc-500">
                                                    {new Date(r.report_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}
                                                </p>
                                            </div>
                                            {r.summary && (
                                                <p className="mt-1 text-sm text-zinc-400 line-clamp-2">{r.summary}</p>
                                            )}

                                            {/* Metrics pills */}
                                            {(m.visitors != null || m.pageviews != null || m.uptime_percent != null) && (
                                                <div className="mt-3 flex flex-wrap gap-2">
                                                    {m.visitors != null && (
                                                        <span className="flex items-center gap-1 rounded-md bg-zinc-800 px-2.5 py-1 text-xs text-zinc-300">
                                                            <Users className="h-3 w-3 text-zinc-500" />{Number(m.visitors).toLocaleString()} visitors
                                                        </span>
                                                    )}
                                                    {m.pageviews != null && (
                                                        <span className="flex items-center gap-1 rounded-md bg-zinc-800 px-2.5 py-1 text-xs text-zinc-300">
                                                            <TrendingUp className="h-3 w-3 text-zinc-500" />{Number(m.pageviews).toLocaleString()} views
                                                        </span>
                                                    )}
                                                    {m.uptime_percent != null && (
                                                        <span className="flex items-center gap-1 rounded-md bg-zinc-800 px-2.5 py-1 text-xs text-zinc-300">
                                                            <ShieldCheck className="h-3 w-3 text-zinc-500" />{m.uptime_percent}% uptime
                                                        </span>
                                                    )}
                                                </div>
                                            )}

                                            {/* Work done preview */}
                                            {m.work_done && (
                                                <p className="mt-2 text-xs text-zinc-500 line-clamp-2 whitespace-pre-line">{m.work_done}</p>
                                            )}
                                        </div>
                                        <DropdownMenu>
                                            <DropdownMenuTrigger asChild>
                                                <Button variant="ghost" size="icon" className="h-7 w-7 shrink-0">
                                                    <MoreHorizontal className="h-4 w-4" />
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end" className="border-zinc-700 bg-zinc-900">
                                                <DropdownMenuItem onClick={() => setSheetReport(r)} className="gap-2">
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
                                </CardContent>
                            </Card>
                        );
                    })}
                </div>
            </div>

            {sheetReport !== null && (
                <ReportSheet
                    siteId={site.id}
                    report={sheetReport === 'new' ? null : sheetReport}
                    onClose={() => setSheetReport(null)}
                />
            )}

            <AlertDialog open={!!deleteId} onOpenChange={(open) => !open && setDeleteId(null)}>
                <AlertDialogContent className="border-zinc-800 bg-[#1e1e1e]">
                    <AlertDialogHeader>
                        <AlertDialogTitle className="text-zinc-100">Delete report?</AlertDialogTitle>
                        <AlertDialogDescription>This cannot be undone.</AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel className="border-zinc-700">Cancel</AlertDialogCancel>
                        <AlertDialogAction className="bg-destructive text-destructive-foreground hover:bg-destructive/90" onClick={() => deleteId && deleteReport(deleteId)}>Delete</AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </AppLayout>
    );
}
