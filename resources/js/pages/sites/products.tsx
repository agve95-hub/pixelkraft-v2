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
import { Plus, Pencil, Trash2, ShoppingBag } from 'lucide-react';

interface Site { id: string; name: string; }
interface Product { id: string; name: string; price: string; currency: string; status: string; }

export default function Products({ site, products }: { site: Site; products: Product[] }) {
    const [deleteId, setDeleteId] = useState<string | null>(null);
    const del = (id: string) => { router.delete(`/dashboard/sites/${site.id}/products/${id}`); setDeleteId(null); };

    return (
        <AppLayout title="Products">
            <Head title={`Products — ${site.name}`} />
            <div className="space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold text-zinc-100">Products</h1>
                        <p className="text-sm text-zinc-400">{site.name}</p>
                    </div>
                    <Link href={`/dashboard/sites/${site.id}/products/create`}>
                        <Button size="sm"><Plus className="mr-1.5 h-3.5 w-3.5" />New product</Button>
                    </Link>
                </div>

                <Card className="border-zinc-800 bg-[#1e1e1e]">
                    <CardContent className="p-0">
                        {products.length === 0 ? (
                            <div className="flex flex-col items-center gap-3 py-16">
                                <ShoppingBag className="h-8 w-8 text-zinc-600" />
                                <p className="text-sm text-zinc-500">No products yet.</p>
                            </div>
                        ) : (
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b border-zinc-800">
                                        {['Name', 'Price', 'Status', ''].map((h) => (
                                            <th key={h} className="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-widest text-zinc-500">{h}</th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {products.map((p) => (
                                        <tr key={p.id} className="border-b border-zinc-800/60 hover:bg-zinc-800/20">
                                            <td className="px-4 py-3 font-medium text-zinc-100">{p.name}</td>
                                            <td className="px-4 py-3 tabular-nums text-zinc-200">
                                                {p.currency} {Number(p.price).toFixed(2)}
                                            </td>
                                            <td className="px-4 py-3">
                                                {p.status === 'published'
                                                    ? <Badge variant="success">Published</Badge>
                                                    : <Badge variant="secondary">Draft</Badge>}
                                            </td>
                                            <td className="px-4 py-3 flex items-center gap-1">
                                                <Link href={`/dashboard/sites/${site.id}/products/${p.id}/edit`}>
                                                    <Button variant="ghost" size="icon" className="h-7 w-7 text-zinc-400 hover:text-zinc-200">
                                                        <Pencil className="h-3.5 w-3.5" />
                                                    </Button>
                                                </Link>
                                                <Button variant="ghost" size="icon" className="h-7 w-7 text-red-400 hover:text-red-300" onClick={() => setDeleteId(p.id)}>
                                                    <Trash2 className="h-3.5 w-3.5" />
                                                </Button>
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
                        <AlertDialogTitle className="text-zinc-100">Delete product?</AlertDialogTitle>
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
