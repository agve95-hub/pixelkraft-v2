import { Head } from '@inertiajs/react';
import AppLayout from '@/layouts/AppLayout';
import { Card, CardContent } from '@/components/ui/card';
import { Users } from 'lucide-react';

export default function Subscribers() {
    return (
        <AppLayout title="Subscribers">
            <Head title="Subscribers" />
            <div className="space-y-6">
                <h1 className="text-xl font-semibold text-zinc-100">Subscribers</h1>
                <Card className="border-zinc-800 bg-[#1e1e1e]">
                    <CardContent className="flex flex-col items-center gap-3 py-16">
                        <Users className="h-8 w-8 text-zinc-600" />
                        <p className="text-sm text-zinc-500">Newsletter subscribers coming soon.</p>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
