import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/layouts/AppLayout';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
    AlertDialog, AlertDialogContent, AlertDialogHeader, AlertDialogTitle,
    AlertDialogDescription, AlertDialogFooter, AlertDialogAction, AlertDialogCancel,
} from '@/components/ui/alert-dialog';
import { Plus, Pencil, Trash2, FileText } from 'lucide-react';

interface Site { id: string; name: string; }
interface Post { id: string; title: string; slug: string; status: string; published_at: string | null; created_at: string; }

function statusBadge(status: string) {
    if (status === 'published') return <Badge variant="success">Published</Badge>;
    if (status === 'scheduled') return <Badge variant="warning">Scheduled</Badge>;
    return <Badge variant="secondary">Draft</Badge>;
}

export default function BlogIndex({ site, posts }: { site: Site; posts: Post[] }) {
    const [deleteId, setDeleteId] = useState<string | null>(null);
    const del = (id: string) => { router.delete(`/dashboard/sites/${site.id}/blog/${id}`); setDeleteId(null); };

    return (
        <AppLayout title="Blog">
            <Head title={`Blog — ${site.name}`} />
            <div className="space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold text-zinc-100">Blog</h1>
                        <p className="text-sm text-zinc-400">{site.name}</p>
                    </div>
                    <Link href={`/dashboard/sites/${site.id}/blog/create`}>
                        <Button size="sm"><Plus className="mr-1.5 h-3.5 w-3.5" />New post</Button>
                    </Link>
                </div>

                <Card className="border-zinc-800 bg-[#1e1e1e]">
                    <CardContent className="p-0">
                        {posts.length === 0 ? (
                            <div className="flex flex-col items-center gap-3 py-16">
                                <FileText className="h-8 w-8 text-zinc-600" />
                                <p className="text-sm text-zinc-500">No posts yet.</p>
                            </div>
                        ) : (
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b border-zinc-800">
                                        {['Title', 'Slug', 'Status', 'Published', ''].map((h) => (
                                            <th key={h} className="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-widest text-zinc-500">{h}</th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {posts.map((p) => (
                                        <tr key={p.id} className="border-b border-zinc-800/60 hover:bg-zinc-800/20">
                                            <td className="px-4 py-3 font-medium text-zinc-100">{p.title}</td>
                                            <td className="px-4 py-3 font-mono text-xs text-zinc-400">{p.slug}</td>
                                            <td className="px-4 py-3">{statusBadge(p.status)}</td>
                                            <td className="px-4 py-3 text-xs text-zinc-400">{p.published_at ? new Date(p.published_at).toLocaleDateString() : '—'}</td>
                                            <td className="px-4 py-3">
                                                <div className="flex gap-1">
                                                    <Link href={`/dashboard/sites/${site.id}/blog/${p.id}/edit`}>
                                                        <Button variant="ghost" size="icon" className="h-7 w-7"><Pencil className="h-3.5 w-3.5" /></Button>
                                                    </Link>
                                                    <Button variant="ghost" size="icon" className="h-7 w-7 text-red-400 hover:text-red-300" onClick={() => setDeleteId(p.id)}>
                                                        <Trash2 className="h-3.5 w-3.5" />
                                                    </Button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                    </CardContent>
                </Card>
            </div>

            <AlertDialog open={!!deleteId} onOpenChange={(open) => !open && setDeleteId(null)}>
                <AlertDialogContent className="border-zinc-800 bg-[#1e1e1e]">
                    <AlertDialogHeader>
                        <AlertDialogTitle className="text-zinc-100">Delete post?</AlertDialogTitle>
                        <AlertDialogDescription>This cannot be undone.</AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel className="border-zinc-700">Cancel</AlertDialogCancel>
                        <AlertDialogAction className="bg-destructive text-destructive-foreground hover:bg-destructive/90" onClick={() => deleteId && del(deleteId)}>Delete</AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </AppLayout>
    );
}
