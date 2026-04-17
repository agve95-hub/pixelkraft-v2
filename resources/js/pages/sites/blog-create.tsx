import { Head, Link, useForm } from '@inertiajs/react';
import AppLayout from '@/layouts/AppLayout';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { ArrowLeft, Save } from 'lucide-react';

interface Site { id: string; name: string; }

export default function BlogCreate({ site }: { site: Site }) {
    const { data, setData, post, processing, errors } = useForm({
        title: '', slug: '', excerpt: '', body: '', status: 'draft', published_at: '',
    });

    const handleTitle = (v: string) => {
        setData('title', v);
        if (!data.slug) setData('slug', v.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, ''));
    };

    return (
        <AppLayout title="New post">
            <Head title={`New post — ${site.name}`} />
            <form onSubmit={(e) => { e.preventDefault(); post(`/dashboard/sites/${site.id}/blog`); }}>
                <div className="space-y-6">
                    <div className="flex items-center justify-between gap-3">
                        <div className="flex items-center gap-3">
                            <Link href={`/dashboard/sites/${site.id}/blog`}>
                                <Button type="button" variant="ghost" size="icon" className="h-8 w-8"><ArrowLeft className="h-4 w-4" /></Button>
                            </Link>
                            <div>
                                <h1 className="text-xl font-semibold text-zinc-100">New post</h1>
                                <p className="text-sm text-zinc-400">{site.name}</p>
                            </div>
                        </div>
                        <div className="flex items-center gap-2">
                            <Select value={data.status} onValueChange={(v) => setData('status', v)}>
                                <SelectTrigger className="w-32 border-zinc-700 bg-zinc-900 text-zinc-100"><SelectValue /></SelectTrigger>
                                <SelectContent className="border-zinc-700 bg-zinc-900">
                                    <SelectItem value="draft">Draft</SelectItem>
                                    <SelectItem value="published">Publish now</SelectItem>
                                    <SelectItem value="scheduled">Schedule</SelectItem>
                                </SelectContent>
                            </Select>
                            <Button type="submit" size="sm" disabled={processing}>
                                <Save className="mr-1.5 h-3.5 w-3.5" />Save
                            </Button>
                        </div>
                    </div>

                    <Card className="border-zinc-800 bg-[#1e1e1e]">
                        <CardContent className="pt-5 space-y-4">
                            <div className="space-y-1">
                                <Label className="text-xs text-zinc-400">Title *</Label>
                                <Input value={data.title} onChange={(e) => handleTitle(e.target.value)} className="border-zinc-700 bg-zinc-900 text-zinc-100 text-lg" />
                                {errors.title && <p className="text-xs text-red-400">{errors.title}</p>}
                            </div>
                            <div className="space-y-1">
                                <Label className="text-xs text-zinc-400">Slug *</Label>
                                <Input value={data.slug} onChange={(e) => setData('slug', e.target.value)} className="border-zinc-700 bg-zinc-900 text-zinc-100 font-mono text-sm" />
                                {errors.slug && <p className="text-xs text-red-400">{errors.slug}</p>}
                            </div>
                            <div className="space-y-1">
                                <Label className="text-xs text-zinc-400">Excerpt</Label>
                                <Textarea value={data.excerpt} onChange={(e) => setData('excerpt', e.target.value)} rows={2} className="border-zinc-700 bg-zinc-900 text-zinc-100" />
                            </div>
                            <div className="space-y-1">
                                <Label className="text-xs text-zinc-400">Body</Label>
                                <Textarea value={data.body} onChange={(e) => setData('body', e.target.value)} rows={14} className="border-zinc-700 bg-zinc-900 text-zinc-100 font-mono text-sm" />
                            </div>
                            {data.status === 'scheduled' && (
                                <div className="space-y-1">
                                    <Label className="text-xs text-zinc-400">Publish at</Label>
                                    <Input type="datetime-local" value={data.published_at} onChange={(e) => setData('published_at', e.target.value)} className="border-zinc-700 bg-zinc-900 text-zinc-100" />
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </form>
        </AppLayout>
    );
}
