import { Head } from '@inertiajs/react';
import AppLayout from '@/layouts/AppLayout';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Skeleton } from '@/components/ui/skeleton';
import { Link } from '@inertiajs/react';
import { Plus, Globe } from 'lucide-react';

export default function SitesIndex() {
    return (
        <AppLayout title="All Sites">
            <Head title="All Sites" />
            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold text-zinc-100">All Sites</h1>
                    <Button asChild>
                        <Link href="/dashboard/sites/create"><Plus className="mr-2 h-4 w-4" />New Site</Link>
                    </Button>
                </div>
                <Card className="border-zinc-800 bg-[#1e1e1e]">
                    <CardContent className="pt-6">
                        <p className="text-sm text-zinc-500">Sites list coming soon — migrating from Livewire.</p>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
