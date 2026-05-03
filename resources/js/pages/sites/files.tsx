import { Head, router, useForm } from '@inertiajs/react';
import { useRef, useState } from 'react';
import AppLayout from '@/layouts/AppLayout';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import {
    AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent,
    AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Copy, Trash2, Upload, Check } from 'lucide-react';

interface Site { id: string; name: string; }
interface SiteFile { name: string; url: string; size: number; mime: string; modified: number; }

function fmtSize(bytes: number): string {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

function fmtMime(mime: string): string {
    const map: Record<string, string> = {
        'image/jpeg': 'JPEG', 'image/png': 'PNG', 'image/gif': 'GIF',
        'image/webp': 'WebP', 'image/avif': 'AVIF', 'image/svg+xml': 'SVG',
        'application/pdf': 'PDF', 'text/plain': 'TXT', 'text/csv': 'CSV',
        'application/json': 'JSON', 'application/xml': 'XML', 'text/xml': 'XML',
        'application/zip': 'ZIP',
        'font/woff': 'WOFF', 'font/woff2': 'WOFF2',
        'font/ttf': 'TTF', 'font/otf': 'OTF',
        'image/x-icon': 'ICO', 'image/vnd.microsoft.icon': 'ICO',
    };
    return map[mime] ?? mime.split('/')[1]?.toUpperCase() ?? mime;
}

function CopyButton({ url }: { url: string }) {
    const [copied, setCopied] = useState(false);
    const copy = () => {
        navigator.clipboard.writeText(url).then(() => {
            setCopied(true);
            setTimeout(() => setCopied(false), 1500);
        });
    };
    return (
        <Button variant="ghost" size="icon" className="h-7 w-7" onClick={copy} title="Copy URL">
            {copied ? <Check className="h-3.5 w-3.5 text-green-400" /> : <Copy className="h-3.5 w-3.5" />}
        </Button>
    );
}

export default function Files({ site, files }: { site: Site; files: SiteFile[] }) {
    const fileInputRef = useRef<HTMLInputElement>(null);
    const [deleteTarget, setDeleteTarget] = useState<string | null>(null);
    const { data, setData, post, processing, progress, reset, errors } = useForm<{ file: File | null }>({ file: null });

    const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0] ?? null;
        setData('file', file);
        if (file) {
            post(`/dashboard/sites/${site.id}/files`, {
                forceFormData: true,
                onSuccess: () => { reset(); if (fileInputRef.current) fileInputRef.current.value = ''; },
                onError: () => { reset(); if (fileInputRef.current) fileInputRef.current.value = ''; },
            });
        }
    };

    const deleteFile = (name: string) => {
        router.delete(`/dashboard/sites/${site.id}/files/${encodeURIComponent(name)}`);
        setDeleteTarget(null);
    };

    return (
        <AppLayout title="Files">
            <Head title={`Files — ${site.name}`} />
            <div className="space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold text-zinc-100">Files</h1>
                        <p className="text-sm text-zinc-400">{site.name}</p>
                    </div>
                    <div>
                        <input
                            ref={fileInputRef}
                            type="file"
                            className="hidden"
                            accept=".jpg,.jpeg,.png,.gif,.webp,.avif,.svg,.pdf,.txt,.csv,.json,.xml,.zip,.woff,.woff2,.ttf,.otf,.ico"
                            onChange={handleFileChange}
                        />
                        <Button size="sm" disabled={processing} onClick={() => fileInputRef.current?.click()}>
                            <Upload className="mr-1.5 h-3.5 w-3.5" />
                            {processing ? `Uploading${progress ? ` ${progress.percentage}%` : '…'}` : 'Upload File'}
                        </Button>
                    </div>
                </div>

                {errors.file && (
                    <p className="text-sm text-red-400">{errors.file}</p>
                )}

                <Card className="border-zinc-800 bg-[#1e1e1e]">
                    <CardHeader className="pb-2">
                        <CardTitle className="text-sm font-medium text-zinc-300">
                            {files.length} file{files.length !== 1 ? 's' : ''}
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b border-zinc-800">
                                        {['Name', 'Type', 'Size', 'Modified', 'Actions'].map((h) => (
                                            <th key={h} className="px-3 py-2.5 text-left text-xs font-medium uppercase tracking-widest text-zinc-500">{h}</th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {files.length === 0 ? (
                                        <tr>
                                            <td colSpan={5} className="px-3 py-10 text-center text-sm text-zinc-500">
                                                No files uploaded yet.
                                            </td>
                                        </tr>
                                    ) : files.map((f) => (
                                        <tr key={f.name} className="border-b border-zinc-800/60 hover:bg-zinc-800/30">
                                            <td className="px-3 py-2.5">
                                                <a href={f.url} target="_blank" rel="noreferrer" className="text-zinc-200 hover:text-white hover:underline">
                                                    {f.name}
                                                </a>
                                            </td>
                                            <td className="px-3 py-2.5">
                                                <Badge variant="secondary">{fmtMime(f.mime)}</Badge>
                                            </td>
                                            <td className="px-3 py-2.5 tabular-nums text-zinc-400">{fmtSize(f.size)}</td>
                                            <td className="px-3 py-2.5 text-zinc-400">
                                                {new Date(f.modified * 1000).toLocaleDateString()}
                                            </td>
                                            <td className="px-3 py-2.5">
                                                <div className="flex gap-1">
                                                    <CopyButton url={f.url} />
                                                    <Button
                                                        variant="ghost" size="icon"
                                                        className="h-7 w-7 text-red-400 hover:text-red-300"
                                                        onClick={() => setDeleteTarget(f.name)}
                                                        title="Delete"
                                                    >
                                                        <Trash2 className="h-3.5 w-3.5" />
                                                    </Button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <AlertDialog open={!!deleteTarget} onOpenChange={(open) => !open && setDeleteTarget(null)}>
                <AlertDialogContent className="border-zinc-800 bg-[#1e1e1e]">
                    <AlertDialogHeader>
                        <AlertDialogTitle className="text-zinc-100">Delete file?</AlertDialogTitle>
                        <AlertDialogDescription>
                            <span className="font-mono text-zinc-300">{deleteTarget}</span> will be permanently removed.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel className="border-zinc-700">Cancel</AlertDialogCancel>
                        <AlertDialogAction
                            className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                            onClick={() => deleteTarget && deleteFile(deleteTarget)}
                        >
                            Delete
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </AppLayout>
    );
}
