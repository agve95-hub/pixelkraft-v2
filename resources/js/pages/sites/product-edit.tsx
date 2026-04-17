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
interface Product { id: string; name: string; description: string | null; price: string; currency: string; status: string; }

export default function ProductEdit({ site, product }: { site: Site; product: Product }) {
    const { data, setData, put, processing, errors } = useForm({
        name: product.name,
        description: product.description ?? '',
        price: product.price,
        currency: product.currency,
        status: product.status,
    });

    return (
        <AppLayout title="Edit product">
            <Head title={`Edit product — ${site.name}`} />
            <form onSubmit={(e) => { e.preventDefault(); put(`/dashboard/sites/${site.id}/products/${product.id}`); }}>
                <div className="space-y-6">
                    <div className="flex items-center justify-between gap-3">
                        <div className="flex items-center gap-3">
                            <Link href={`/dashboard/sites/${site.id}/products`}>
                                <Button type="button" variant="ghost" size="icon" className="h-8 w-8"><ArrowLeft className="h-4 w-4" /></Button>
                            </Link>
                            <div>
                                <h1 className="text-xl font-semibold text-zinc-100">Edit product</h1>
                                <p className="text-sm text-zinc-400">{site.name}</p>
                            </div>
                        </div>
                        <Button type="submit" size="sm" disabled={processing}>
                            <Save className="mr-1.5 h-3.5 w-3.5" />Save
                        </Button>
                    </div>

                    <Card className="border-zinc-800 bg-[#1e1e1e]">
                        <CardContent className="pt-5 space-y-4">
                            <div className="space-y-1">
                                <Label className="text-xs text-zinc-400">Name *</Label>
                                <Input value={data.name} onChange={(e) => setData('name', e.target.value)} className="border-zinc-700 bg-zinc-900 text-zinc-100" />
                                {errors.name && <p className="text-xs text-red-400">{errors.name}</p>}
                            </div>
                            <div className="space-y-1">
                                <Label className="text-xs text-zinc-400">Description</Label>
                                <Textarea value={data.description} onChange={(e) => setData('description', e.target.value)} rows={4} className="border-zinc-700 bg-zinc-900 text-zinc-100" />
                            </div>
                            <div className="flex gap-3">
                                <div className="flex-1 space-y-1">
                                    <Label className="text-xs text-zinc-400">Price *</Label>
                                    <Input type="number" step="0.01" value={data.price} onChange={(e) => setData('price', e.target.value)} placeholder="0.00" className="border-zinc-700 bg-zinc-900 text-zinc-100" />
                                    {errors.price && <p className="text-xs text-red-400">{errors.price}</p>}
                                </div>
                                <div className="w-24 space-y-1">
                                    <Label className="text-xs text-zinc-400">Currency</Label>
                                    <Input value={data.currency} onChange={(e) => setData('currency', e.target.value.toUpperCase())} maxLength={3} className="border-zinc-700 bg-zinc-900 text-zinc-100" />
                                </div>
                                <div className="w-36 space-y-1">
                                    <Label className="text-xs text-zinc-400">Status</Label>
                                    <Select value={data.status} onValueChange={(v) => setData('status', v)}>
                                        <SelectTrigger className="border-zinc-700 bg-zinc-900 text-zinc-100"><SelectValue /></SelectTrigger>
                                        <SelectContent className="border-zinc-700 bg-zinc-900">
                                            <SelectItem value="draft">Draft</SelectItem>
                                            <SelectItem value="published">Published</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </form>
        </AppLayout>
    );
}
