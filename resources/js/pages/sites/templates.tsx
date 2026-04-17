import { Head } from '@inertiajs/react';
import AppLayout from '@/layouts/AppLayout';
import { Card, CardContent } from '@/components/ui/card';
import { LayoutTemplate } from 'lucide-react';

interface Site { id: string; name: string; }

export default function Templates({ site }: { site: Site }) {
    return (
        <AppLayout title="Templates">
            <Head title={`Templates — ${site.name}`} />
            <div className="space-y-6">
                <div>
                    <h1 className="text-xl font-semibold text-zinc-100">Templates</h1>
                    <p className="text-sm text-zinc-400">{site.name}</p>
                </div>
                <Card className="border-zinc-800 bg-[#1e1e1e]">
                    <CardContent className="flex flex-col items-center gap-3 py-16">
                        <LayoutTemplate className="h-8 w-8 text-zinc-600" />
                        <p className="text-sm text-zinc-500">Template manager coming soon.</p>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
