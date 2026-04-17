import { Head, router } from '@inertiajs/react';
import { useState, useCallback } from 'react';
import AppLayout from '@/layouts/AppLayout';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Badge } from '@/components/ui/badge';
import {
    Check, X, Loader2, ChevronRight, ChevronLeft, GitBranch,
    Server, Upload, Globe, Building, ArrowLeft,
} from 'lucide-react';
import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/react';

interface CreateSiteProps {
    projectTypes: string[];
}

type SourceType = 'github' | 'server_path' | 'upload';

interface FormData {
    // Step 1: Client
    client_first_name: string;
    client_last_name: string;
    client_email: string;
    client_company: string;
    // Step 2: Project
    name: string;
    project_type: string;
    // Step 3: Source
    source_type: SourceType;
    repo_url: string;
    branch: string;
    build_command: string;
    github_token: string;
    server_path: string;
    // Step 4: Domain
    domain: string;
    ssl_provider: string;
}

type ImportStep = {
    key: string;
    label: string;
    status: 'pending' | 'active' | 'done' | 'failed';
    error?: string;
};

const IMPORT_STEPS: Omit<ImportStep, 'status'>[] = [
    { key: 'validate', label: 'Validating credentials' },
    { key: 'clone', label: 'Cloning repository / Mounting path' },
    { key: 'detect', label: 'Detecting package manager' },
    { key: 'install', label: 'Installing dependencies' },
    { key: 'build', label: 'Running build' },
    { key: 'nginx', label: 'Generating Nginx config' },
    { key: 'seo', label: 'Analysing SEO metadata' },
    { key: 'save', label: 'Saving to database' },
];

const STEP_LABELS = ['Client Info', 'Project Setup', 'Source & Deploy', 'Domain & SSL'];

export default function CreateSite({ projectTypes }: CreateSiteProps) {
    const [step, setStep] = useState(0);
    const [form, setForm] = useState<FormData>({
        client_first_name: '', client_last_name: '', client_email: '', client_company: '',
        name: '', project_type: 'static_html',
        source_type: 'github', repo_url: '', branch: 'main', build_command: '', github_token: '', server_path: '',
        domain: '', ssl_provider: 'letsencrypt',
    });
    const [errors, setErrors] = useState<Partial<Record<keyof FormData, string>>>({});
    const [importing, setImporting] = useState(false);
    const [siteId, setSiteId] = useState<string | null>(null);
    const [importSteps, setImportSteps] = useState<ImportStep[]>(
        IMPORT_STEPS.map((s) => ({ ...s, status: 'pending' })),
    );
    const [importFailed, setImportFailed] = useState(false);

    function update(field: keyof FormData, value: string) {
        setForm((prev) => ({ ...prev, [field]: value }));
        setErrors((prev) => { const e = { ...prev }; delete e[field]; return e; });
    }

    function validate(): boolean {
        const e: typeof errors = {};
        if (step === 1 && !form.name.trim()) e.name = 'Site name is required.';
        if (step === 2 && form.source_type === 'github' && !form.repo_url.trim()) e.repo_url = 'Repository URL is required.';
        setErrors(e);
        return Object.keys(e).length === 0;
    }

    async function submit() {
        if (!validate()) return;
        setImporting(true);
        setImportSteps(IMPORT_STEPS.map((s) => ({ ...s, status: 'pending' })));

        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
            const res = await fetch('/dashboard/sites', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
                body: JSON.stringify(form),
            });
            if (!res.ok) {
                const data = await res.json();
                setErrors(data.errors ?? {});
                setImporting(false);
                return;
            }
            const data = await res.json();
            setSiteId(data.siteId);
            pollImportStatus(data.siteId);
        } catch {
            setImporting(false);
        }
    }

    async function pollImportStatus(id: string) {
        let attempts = 0;
        const maxAttempts = 90;
        let stuckAtZeroCount = 0;

        const tick = async () => {
            if (attempts++ > maxAttempts) { setImportFailed(true); return; }
            try {
                const res = await fetch(`/dashboard/sites/${id}/import-status`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                const data = await res.json();
                const progressMap: Record<string, number> = {
                    queued: 0, cloning: 1, parsing: 2, deploying: 3, building: 4,
                };
                const activeIdx = progressMap[data.status] ?? 0;
                if (activeIdx === 0) {
                    stuckAtZeroCount++;
                    if (stuckAtZeroCount >= 10) { setImportFailed(true); return; }
                } else {
                    stuckAtZeroCount = 0;
                }
                simulateStepProgress(data.status);
                if (data.status === 'live') {
                    setImportSteps((prev) => prev.map((s) => ({ ...s, status: 'done' })));
                    setTimeout(() => router.visit(`/dashboard/sites/${id}`), 2000);
                } else if (data.status === 'failed') {
                    setImportFailed(true);
                } else {
                    setTimeout(tick, 2000);
                }
            } catch {
                setTimeout(tick, 2000);
            }
        };
        setTimeout(tick, 1000);
    }

    function simulateStepProgress(deployStatus: string) {
        const progressMap: Record<string, number> = {
            queued: 0, cloning: 1, parsing: 2, deploying: 3, building: 4,
        };
        const activeIdx = progressMap[deployStatus] ?? 0;
        setImportSteps((prev) =>
            prev.map((s, i) => ({
                ...s,
                status: i < activeIdx ? 'done' : i === activeIdx ? 'active' : 'pending',
            })),
        );
    }

    function StepIndicator() {
        return (
            <div className="flex items-center gap-2 mb-6">
                {STEP_LABELS.map((label, i) => (
                    <div key={i} className="flex items-center gap-2">
                        <div className={cn(
                            'flex h-7 w-7 items-center justify-center rounded-full text-xs font-semibold',
                            i < step ? 'bg-emerald-500 text-zinc-950' :
                            i === step ? 'bg-zinc-100 text-zinc-950' :
                            'bg-zinc-800 text-zinc-500',
                        )}>
                            {i < step ? <Check className="h-3.5 w-3.5" /> : i + 1}
                        </div>
                        <span className={cn('text-sm', i === step ? 'text-zinc-100' : 'text-zinc-500')}>{label}</span>
                        {i < STEP_LABELS.length - 1 && <div className="h-px w-6 bg-zinc-800" />}
                    </div>
                ))}
            </div>
        );
    }

    return (
        <AppLayout title="Add New Site">
            <Head title="Add New Site" />

            <form className="max-w-2xl" onSubmit={(e) => e.preventDefault()}>
                <div className="mb-6 flex items-center gap-3">
                    <Button variant="ghost" size="icon" asChild className="text-zinc-400">
                        <Link href="/dashboard/sites"><ArrowLeft className="h-4 w-4" /></Link>
                    </Button>
                    <h1 className="text-xl font-semibold text-zinc-100">Add New Site</h1>
                </div>

                <StepIndicator />

                <Card className="border-zinc-800 bg-[#1e1e1e]">
                    <CardContent className="pt-6 space-y-5">
                        {/* Step 0: Client Info */}
                        {step === 0 && (
                            <>
                                <CardTitle className="text-base text-zinc-100 mb-2">Client Information <span className="text-zinc-500 text-sm font-normal">(optional)</span></CardTitle>
                                <div className="grid grid-cols-2 gap-4">
                                    <div className="space-y-1.5">
                                        <Label className="text-zinc-300">First name</Label>
                                        <Input value={form.client_first_name} onChange={(e) => update('client_first_name', e.target.value)} className="border-zinc-700 bg-zinc-900 text-zinc-100" />
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label className="text-zinc-300">Last name</Label>
                                        <Input value={form.client_last_name} onChange={(e) => update('client_last_name', e.target.value)} className="border-zinc-700 bg-zinc-900 text-zinc-100" />
                                    </div>
                                </div>
                                <div className="space-y-1.5">
                                    <Label className="text-zinc-300">Email</Label>
                                    <Input type="email" value={form.client_email} onChange={(e) => update('client_email', e.target.value)} className="border-zinc-700 bg-zinc-900 text-zinc-100" />
                                </div>
                                <div className="space-y-1.5">
                                    <Label className="text-zinc-300">Company</Label>
                                    <Input value={form.client_company} onChange={(e) => update('client_company', e.target.value)} className="border-zinc-700 bg-zinc-900 text-zinc-100" />
                                </div>
                            </>
                        )}

                        {/* Step 1: Project Setup */}
                        {step === 1 && (
                            <>
                                <CardTitle className="text-base text-zinc-100 mb-2">Project Setup</CardTitle>
                                <div className="space-y-1.5">
                                    <Label className="text-zinc-300">Site name <span className="text-red-400">*</span></Label>
                                    <Input value={form.name} onChange={(e) => update('name', e.target.value)} placeholder="My Awesome Site" className="border-zinc-700 bg-zinc-900 text-zinc-100" />
                                    {errors.name && <p className="text-xs text-red-400">{errors.name}</p>}
                                </div>
                                <div className="space-y-1.5">
                                    <Label className="text-zinc-300">Project type</Label>
                                    <Select value={form.project_type} onValueChange={(v) => update('project_type', v)}>
                                        <SelectTrigger className="border-zinc-700 bg-zinc-900 text-zinc-100"><SelectValue /></SelectTrigger>
                                        <SelectContent className="border-zinc-700 bg-zinc-900">
                                            {projectTypes.map((t) => <SelectItem key={t} value={t}>{t}</SelectItem>)}
                                        </SelectContent>
                                    </Select>
                                </div>
                            </>
                        )}

                        {/* Step 2: Source & Deploy */}
                        {step === 2 && (
                            <>
                                <CardTitle className="text-base text-zinc-100 mb-2">Source & Deployment</CardTitle>

                                {/* Source type segmented control */}
                                <div>
                                    <Label className="text-zinc-300 mb-2 block">Source type</Label>
                                    <div className="flex rounded-lg border border-zinc-700 overflow-hidden">
                                        {([['github', 'GitHub', GitBranch], ['server_path', 'Server Path', Server], ['upload', 'Upload .zip', Upload]] as const).map(([value, label, Icon]) => (
                                            <button
                                                key={value}
                                                type="button"
                                                onClick={() => update('source_type', value)}
                                                className={cn(
                                                    'flex flex-1 items-center justify-center gap-2 py-2.5 text-sm transition-colors',
                                                    form.source_type === value
                                                        ? 'bg-zinc-100 text-zinc-950 font-medium'
                                                        : 'bg-zinc-900 text-zinc-400 hover:text-zinc-200',
                                                )}
                                            >
                                                <Icon className="h-4 w-4" />{label}
                                            </button>
                                        ))}
                                    </div>
                                </div>

                                {form.source_type === 'github' && (
                                    <>
                                        <div className="space-y-1.5">
                                            <Label className="text-zinc-300">Repository URL <span className="text-red-400">*</span></Label>
                                            <Input value={form.repo_url} onChange={(e) => update('repo_url', e.target.value)} placeholder="https://github.com/user/repo" className="border-zinc-700 bg-zinc-900 text-zinc-100" />
                                            {errors.repo_url && <p className="text-xs text-red-400">{errors.repo_url}</p>}
                                        </div>
                                        <div className="grid grid-cols-2 gap-4">
                                            <div className="space-y-1.5">
                                                <Label className="text-zinc-300">Branch</Label>
                                                <Input value={form.branch} onChange={(e) => update('branch', e.target.value)} className="border-zinc-700 bg-zinc-900 text-zinc-100" />
                                            </div>
                                            <div className="space-y-1.5">
                                                <Label className="text-zinc-300">Build command <span className="text-zinc-500">(optional)</span></Label>
                                                <Input value={form.build_command} onChange={(e) => update('build_command', e.target.value)} placeholder="npm run build" className="border-zinc-700 bg-zinc-900 text-zinc-100" />
                                            </div>
                                        </div>
                                        <div className="space-y-1.5">
                                            <Label className="text-zinc-300">GitHub token <span className="text-zinc-500">(for private repos)</span></Label>
                                            <Input type="password" value={form.github_token} onChange={(e) => update('github_token', e.target.value)} className="border-zinc-700 bg-zinc-900 text-zinc-100" />
                                        </div>
                                    </>
                                )}

                                {form.source_type === 'server_path' && (
                                    <div className="space-y-1.5">
                                        <Label className="text-zinc-300">Absolute path on this server</Label>
                                        <Input value={form.server_path} onChange={(e) => update('server_path', e.target.value)} placeholder="/var/www/mysite" className="border-zinc-700 bg-zinc-900 font-mono text-zinc-100" />
                                        <p className="text-xs text-zinc-500">The path will be validated before proceeding.</p>
                                    </div>
                                )}

                                {form.source_type === 'upload' && (
                                    <div className="rounded-lg border-2 border-dashed border-zinc-700 p-8 text-center">
                                        <Upload className="mx-auto h-8 w-8 text-zinc-500 mb-3" />
                                        <p className="text-sm text-zinc-400">Drag & drop a <code className="text-xs bg-zinc-800 px-1 py-0.5 rounded">.zip</code> file here, or click to browse.</p>
                                        <p className="mt-1 text-xs text-zinc-600">Upload feature available in next release.</p>
                                    </div>
                                )}
                            </>
                        )}

                        {/* Step 3: Domain & SSL */}
                        {step === 3 && (
                            <>
                                <CardTitle className="text-base text-zinc-100 mb-2">Domain & SSL</CardTitle>
                                <div className="space-y-1.5">
                                    <Label className="text-zinc-300">Domain <span className="text-zinc-500">(optional)</span></Label>
                                    <Input value={form.domain} onChange={(e) => update('domain', e.target.value)} placeholder="mysite.com" className="border-zinc-700 bg-zinc-900 text-zinc-100" />
                                </div>
                                <div className="space-y-1.5">
                                    <Label className="text-zinc-300">SSL Provider</Label>
                                    <Select value={form.ssl_provider} onValueChange={(v) => update('ssl_provider', v)}>
                                        <SelectTrigger className="border-zinc-700 bg-zinc-900 text-zinc-100"><SelectValue /></SelectTrigger>
                                        <SelectContent className="border-zinc-700 bg-zinc-900">
                                            <SelectItem value="letsencrypt">Let's Encrypt</SelectItem>
                                            <SelectItem value="cloudflare">Cloudflare</SelectItem>
                                            <SelectItem value="none">None</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </>
                        )}

                        {/* Navigation */}
                        <div className="flex justify-between pt-2">
                            {step > 0 ? (
                                <Button variant="outline" onClick={() => setStep((s) => s - 1)} className="border-zinc-700 text-zinc-200">
                                    <ChevronLeft className="mr-1.5 h-4 w-4" />Back
                                </Button>
                            ) : <div />}
                            {step < 3 ? (
                                <Button onClick={() => { if (validate()) setStep((s) => s + 1); }}>
                                    Next<ChevronRight className="ml-1.5 h-4 w-4" />
                                </Button>
                            ) : (
                                <Button onClick={submit} className="bg-emerald-500 text-zinc-950 hover:bg-emerald-400">
                                    Create Site
                                </Button>
                            )}
                        </div>
                    </CardContent>
                </Card>
            </form>

            {/* Blocking Import Progress Modal */}
            <Dialog open={importing} onOpenChange={() => {}}>
                <DialogContent
                    className="sm:max-w-md border-zinc-800 bg-[#1e1e1e]"
                    hideClose
                    onInteractOutside={(e) => e.preventDefault()}
                    onEscapeKeyDown={(e) => e.preventDefault()}
                >
                    <DialogHeader>
                        <DialogTitle className="text-zinc-100">Setting up your site…</DialogTitle>
                        <DialogDescription className="sr-only">Import progress — please wait while your site is being set up.</DialogDescription>
                    </DialogHeader>

                    <div className="space-y-3 py-2">
                        {importSteps.map((s, i) => (
                            <div key={s.key} className="flex items-start gap-3">
                                <div className="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center">
                                    {s.status === 'done' && <Check className="h-4 w-4 text-emerald-400" />}
                                    {s.status === 'active' && <Loader2 className="h-4 w-4 animate-spin text-zinc-300" />}
                                    {s.status === 'failed' && <X className="h-4 w-4 text-red-400" />}
                                    {s.status === 'pending' && <div className="h-2 w-2 rounded-full bg-zinc-700" />}
                                </div>
                                <div className="flex-1">
                                    <p className={cn(
                                        'text-sm',
                                        s.status === 'done' ? 'text-emerald-400' :
                                        s.status === 'active' ? 'text-zinc-100 font-medium' :
                                        s.status === 'failed' ? 'text-red-400' :
                                        'text-zinc-600',
                                    )}>{s.label}</p>
                                    {s.error && <p className="mt-1 text-xs text-red-400">{s.error}</p>}
                                </div>
                            </div>
                        ))}
                    </div>

                    {importFailed && (
                        <div className="border-t border-zinc-800 pt-4 text-center">
                            <button
                                type="button"
                                className="text-xs text-red-400 hover:text-red-300"
                                onClick={async () => {
                                    if (siteId) {
                                        await fetch(`/dashboard/sites/${siteId}`, {
                                            method: 'DELETE',
                                            headers: { 'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '', 'X-Requested-With': 'XMLHttpRequest' },
                                        });
                                    }
                                    router.visit('/dashboard/sites');
                                }}
                            >
                                Cancel and delete this site
                            </button>
                        </div>
                    )}
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
