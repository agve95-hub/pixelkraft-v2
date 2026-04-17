import { Head } from '@inertiajs/react';
import AppLayout from '@/layouts/AppLayout';
import { Card, CardContent } from '@/components/ui/card';
import { BarChart2 } from 'lucide-react';

export default function Analytics() {
    return (
        <AppLayout title="Analytics">
            <Head title="Analytics" />
            <div className="space-y-6">
                <h1 className="text-xl font-semibold text-zinc-100">Analytics</h1>
                <Card className="border-zinc-800 bg-[#1e1e1e]">
                    <CardContent className="flex flex-col items-center gap-3 py-16">
                        <BarChart2 className="h-8 w-8 text-zinc-600" />
                        <p className="text-sm text-zinc-500">Cross-site analytics coming soon.</p>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
