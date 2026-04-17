import { Head } from '@inertiajs/react';
import AppLayout from '@/layouts/AppLayout';
import { Card, CardContent } from '@/components/ui/card';
import { Inbox } from 'lucide-react';

export default function EmailInbox() {
    return (
        <AppLayout title="Inbox">
            <Head title="Inbox" />
            <div className="space-y-6">
                <h1 className="text-xl font-semibold text-zinc-100">Inbox</h1>
                <Card className="border-zinc-800 bg-[#1e1e1e]">
                    <CardContent className="flex flex-col items-center gap-3 py-16">
                        <Inbox className="h-8 w-8 text-zinc-600" />
                        <p className="text-sm text-zinc-500">Global inbox coming soon.</p>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
