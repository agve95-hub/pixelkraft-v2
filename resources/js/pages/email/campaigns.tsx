import { Head } from '@inertiajs/react';
import AppLayout from '@/layouts/AppLayout';
import { Card, CardContent } from '@/components/ui/card';
import { Mail } from 'lucide-react';

export default function EmailCampaigns() {
    return (
        <AppLayout title="Newsletters">
            <Head title="Newsletters" />
            <div className="space-y-6">
                <h1 className="text-xl font-semibold text-zinc-100">Newsletters</h1>
                <Card className="border-zinc-800 bg-[#1e1e1e]">
                    <CardContent className="flex flex-col items-center gap-3 py-16">
                        <Mail className="h-8 w-8 text-zinc-600" />
                        <p className="text-sm text-zinc-500">Email campaigns coming soon.</p>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
