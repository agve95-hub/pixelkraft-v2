import { Head, useForm } from '@inertiajs/react';
import AppLayout from '@/layouts/AppLayout';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Save } from 'lucide-react';

interface Site { id: string; name: string; }
interface Page {
    id: string; title: string | null; url_path: string | null;
    meta_description: string | null;
    og_title: string | null; og_description: string | null; og_image: string | null;
    canonical_url: string | null;
}

export default function SeoMeta({ site, page }: { site: Site; page: Page }) {
    const { data, setData, put, processing } = useForm({
        title: page.title ?? '',
        meta_description: page.meta_description ?? '',
        og_title: page.og_title ?? '',
        og_description: page.og_description ?? '',
        og_image: page.og_image ?? '',
        canonical_url: page.canonical_url ?? '',
    });

    const s = (k: keyof typeof data) => (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) => setData(k, e.target.value);

    return (
        <AppLayout title="SEO Meta">
            <Head title={`SEO — ${page.title || page.url_path}`} />
            <form onSubmit={(e) => { e.preventDefault(); put(`/dashboard/sites/${site.id}/pages/${page.id}/seo`); }}>
                <div className="space-y-6">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h1 className="text-xl font-semibold text-zinc-100">SEO Meta</h1>
                            <p className="text-sm text-zinc-400">{page.url_path || '/'} · {site.name}</p>
                        </div>
                        <Button type="submit" size="sm" disabled={processing}>
                            <Save className="mr-1.5 h-3.5 w-3.5" />Save
                        </Button>
                    </div>

                    <Card className="border-zinc-800 bg-[#1e1e1e]">
                        <CardContent className="pt-5 space-y-4">
                            <p className="text-xs font-semibold uppercase tracking-widest text-zinc-500">Search engine</p>
                            <div className="space-y-1">
                                <Label className="text-xs text-zinc-400">Meta title</Label>
                                <Input value={data.title} onChange={s('title')} className="border-zinc-700 bg-zinc-900 text-zinc-100" />
                                <p className="text-xs text-zinc-600">{data.title.length} / 60 chars</p>
                            </div>
                            <div className="space-y-1">
                                <Label className="text-xs text-zinc-400">Meta description</Label>
                                <Textarea value={data.meta_description} onChange={s('meta_description')} rows={3} className="border-zinc-700 bg-zinc-900 text-zinc-100" />
                                <p className="text-xs text-zinc-600">{data.meta_description.length} / 160 chars</p>
                            </div>
                            <div className="space-y-1">
                                <Label className="text-xs text-zinc-400">Canonical URL</Label>
                                <Input value={data.canonical_url} onChange={s('canonical_url')} placeholder="https://" className="border-zinc-700 bg-zinc-900 text-zinc-100" />
                            </div>

                            <p className="mt-2 text-xs font-semibold uppercase tracking-widest text-zinc-500">Open Graph</p>
                            <div className="space-y-1">
                                <Label className="text-xs text-zinc-400">OG Title</Label>
                                <Input value={data.og_title} onChange={s('og_title')} className="border-zinc-700 bg-zinc-900 text-zinc-100" />
                            </div>
                            <div className="space-y-1">
                                <Label className="text-xs text-zinc-400">OG Description</Label>
                                <Textarea value={data.og_description} onChange={s('og_description')} rows={2} className="border-zinc-700 bg-zinc-900 text-zinc-100" />
                            </div>
                            <div className="space-y-1">
                                <Label className="text-xs text-zinc-400">OG Image URL</Label>
                                <Input value={data.og_image} onChange={s('og_image')} placeholder="https://..." className="border-zinc-700 bg-zinc-900 text-zinc-100" />
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </form>
        </AppLayout>
    );
}
