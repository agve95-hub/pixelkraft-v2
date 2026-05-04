import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/layouts/AppLayout';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import {
    AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent,
    AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Plus, Pencil, Trash2, X, Check } from 'lucide-react';

interface Site { id: string; name: string; }
interface Template { id: string; name: string; type: string | null; created_at: string; }

const TYPES = ['newsletter', 'blog_post', 'page', 'email', 'other'];

function TypeBadge({ type }: { type: string | null }) {
    if (!type) return <span className="text-zinc-600">—</span>;
    return <Badge variant="secondary" className="capitalize">{type.replace('_', ' ')}</Badge>;
}

function CreateForm({ siteId, onClose }: { siteId: string; onClose: () => void }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        type: 'newsletter',
        html_template: '',
    });
    return (
        <form
            onSubmit={(e) => { e.preventDefault(); post(`/dashboard/sites/${siteId}/templates`, { onSuccess: () => { reset(); onClose(); } }); }}
            className="space-y-4"
        >
            <div className="grid grid-cols-2 gap-3">
                <div className="space-y-1">
                    <Label className="text-xs text-zinc-400">Name</Label>
                    <Input value={data.name} onChange={(e) => setData('name', e.target.value)} placeholder="Monthly newsletter" className="border-zinc-700 bg-zinc-900 text-zinc-100" />
                    {errors.name && <p className="text-xs text-red-400">{errors.name}</p>}
                </div>
                <div className="space-y-1">
                    <Label className="text-xs text-zinc-400">Type</Label>
                    <select
                        value={data.type}
                        onChange={(e) => setData('type', e.target.value)}
                        className="w-full rounded-md border border-zinc-700 bg-zinc-900 px-3 py-2 text-sm text-zinc-100 focus:outline-none focus:ring-1 focus:ring-zinc-500"
                    >
                        {TYPES.map((t) => <option key={t} value={t}>{t.replace('_', ' ')}</option>)}
                    </select>
                </div>
            </div>
            <div className="space-y-1">
                <Label className="text-xs text-zinc-400">HTML template — use {'{{placeholder}}'} for dynamic fields</Label>
                <textarea
                    value={data.html_template}
                    onChange={(e) => setData('html_template', e.target.value)}
                    rows={8}
                    placeholder={'<h1>{{title}}</h1>\n<p>Hello {{name}},</p>\n<p>{{body}}</p>'}
                    className="w-full rounded-md border border-zinc-700 bg-zinc-900 px-3 py-2 font-mono text-xs text-zinc-100 placeholder:text-zinc-600 focus:outline-none focus:ring-1 focus:ring-zinc-500"
                />
            </div>
            <div className="flex gap-2">
                <Button type="submit" size="sm" disabled={processing}><Plus className="mr-1.5 h-3.5 w-3.5" />Create</Button>
                <Button type="button" variant="ghost" size="sm" onClick={onClose}>Cancel</Button>
            </div>
        </form>
    );
}

function EditForm({ template, siteId, onClose }: { template: Template; siteId: string; onClose: () => void }) {
    const { data, setData, put, processing } = useForm({
        name: template.name,
        type: template.type ?? 'other',
        html_template: '',
    });
    return (
        <div className="space-y-3 p-3">
            <div className="flex gap-2">
                <Input value={data.name} onChange={(e) => setData('name', e.target.value)} className="border-zinc-700 bg-zinc-900 text-zinc-100" />
                <select
                    value={data.type}
                    onChange={(e) => setData('type', e.target.value)}
                    className="rounded-md border border-zinc-700 bg-zinc-900 px-2 py-1.5 text-sm text-zinc-100 focus:outline-none"
                >
                    {TYPES.map((t) => <option key={t} value={t}>{t.replace('_', ' ')}</option>)}
                </select>
                <Button
                    size="sm" className="h-9 px-3"
                    disabled={processing}
                    onClick={() => put(`/dashboard/sites/${siteId}/templates/${template.id}`, { onSuccess: onClose })}
                >
                    <Check className="h-3.5 w-3.5" />
                </Button>
                <Button variant="ghost" size="sm" className="h-9 px-2" onClick={onClose}><X className="h-3.5 w-3.5" /></Button>
            </div>
            <textarea
                value={data.html_template}
                onChange={(e) => setData('html_template', e.target.value)}
                rows={6}
                placeholder="Paste updated HTML…"
                className="w-full rounded-md border border-zinc-700 bg-zinc-900 px-3 py-2 font-mono text-xs text-zinc-100 placeholder:text-zinc-600 focus:outline-none focus:ring-1 focus:ring-zinc-500"
            />
        </div>
    );
}

export default function Templates({ site, templates = [] }: { site: Site; templates: Template[] }) {
    const [showCreate, setShowCreate] = useState(false);
    const [editingId, setEditingId] = useState<string | null>(null);
    const [deleteTarget, setDeleteTarget] = useState<Template | null>(null);

    const deleteTemplate = (t: Template) => {
        router.delete(`/dashboard/sites/${site.id}/templates/${t.id}`);
        setDeleteTarget(null);
    };

    return (
        <AppLayout title="Templates">
            <Head title={`Templates — ${site.name}`} />
            <div className="space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold text-zinc-100">Templates</h1>
                        <p className="text-sm text-zinc-400">{site.name}</p>
                    </div>
                    <Button size="sm" onClick={() => setShowCreate((v) => !v)}>
                        <Plus className="mr-1.5 h-3.5 w-3.5" />New Template
                    </Button>
                </div>

                {showCreate && (
                    <Card className="border-zinc-800 bg-[#1e1e1e]">
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-zinc-300">New template</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <CreateForm siteId={site.id} onClose={() => setShowCreate(false)} />
                        </CardContent>
                    </Card>
                )}

                <Card className="border-zinc-800 bg-[#1e1e1e]">
                    <CardHeader className="pb-2">
                        <CardTitle className="text-sm font-medium text-zinc-300">
                            {templates.length} template{templates.length !== 1 ? 's' : ''}
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="p-0">
                        {templates.length === 0 && !showCreate ? (
                            <p className="px-4 py-10 text-center text-sm text-zinc-500">
                                No templates yet. Create one to reuse HTML across newsletters and blog posts.
                            </p>
                        ) : (
                            <div className="divide-y divide-zinc-800">
                                {templates.map((t) => (
                                    <div key={t.id}>
                                        {editingId === t.id ? (
                                            <EditForm template={t} siteId={site.id} onClose={() => setEditingId(null)} />
                                        ) : (
                                            <div className="flex items-center justify-between px-4 py-3 hover:bg-zinc-800/30">
                                                <div className="flex items-center gap-3 min-w-0">
                                                    <span className="truncate text-sm text-zinc-200">{t.name}</span>
                                                    <TypeBadge type={t.type} />
                                                </div>
                                                <div className="flex shrink-0 gap-1">
                                                    <Button variant="ghost" size="icon" className="h-7 w-7" onClick={() => setEditingId(t.id)} title="Edit">
                                                        <Pencil className="h-3.5 w-3.5" />
                                                    </Button>
                                                    <Button variant="ghost" size="icon" className="h-7 w-7 text-red-400 hover:text-red-300" onClick={() => setDeleteTarget(t)} title="Delete">
                                                        <Trash2 className="h-3.5 w-3.5" />
                                                    </Button>
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>

            <AlertDialog open={!!deleteTarget} onOpenChange={(open) => !open && setDeleteTarget(null)}>
                <AlertDialogContent className="border-zinc-800 bg-[#1e1e1e]">
                    <AlertDialogHeader>
                        <AlertDialogTitle className="text-zinc-100">Delete template?</AlertDialogTitle>
                        <AlertDialogDescription>
                            <span className="font-medium text-zinc-300">"{deleteTarget?.name}"</span> will be permanently removed. Blog posts using it will keep their content.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel className="border-zinc-700">Cancel</AlertDialogCancel>
                        <AlertDialogAction className="bg-destructive text-destructive-foreground hover:bg-destructive/90" onClick={() => deleteTarget && deleteTemplate(deleteTarget)}>
                            Delete
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </AppLayout>
    );
}
