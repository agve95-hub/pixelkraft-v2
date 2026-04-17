import { Head } from '@inertiajs/react';
import AppLayout from '@/layouts/AppLayout';
import { Card, CardContent } from '@/components/ui/card';

export default function Page() {
    return (
        <AppLayout title="Settings">
            <Head title="Settings" />
            <div className="space-y-6">
                <h1 className="text-xl font-semibold text-zinc-100">Settings</h1>
                <Card className="border-zinc-800 bg-[#1e1e1e]">
                    <CardContent className="pt-6">
                        <p className="text-sm text-zinc-500">This page is being migrated to React/shadcn.</p>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
