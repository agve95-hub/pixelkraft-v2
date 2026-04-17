import { Head, useForm, usePage, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/layouts/AppLayout';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
    Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter,
} from '@/components/ui/dialog';
import {
    AlertDialog, AlertDialogContent, AlertDialogHeader, AlertDialogTitle,
    AlertDialogDescription, AlertDialogFooter, AlertDialogAction, AlertDialogCancel,
} from '@/components/ui/alert-dialog';
import { Eye, EyeOff, ShieldCheck, ShieldOff, Copy, Check, Server } from 'lucide-react';
import type { PageProps } from '@/types';

interface SettingsProps {
    twoFactorEnabled: boolean;
    twoFactorConfirmed: boolean;
}

function PasswordInput(props: React.InputHTMLAttributes<HTMLInputElement>) {
    const [show, setShow] = useState(false);
    return (
        <div className="relative">
            <Input type={show ? 'text' : 'password'} className="pr-10 border-zinc-700 bg-zinc-900 text-zinc-100" {...props} />
            <button
                type="button"
                tabIndex={-1}
                className="absolute right-3 top-1/2 -translate-y-1/2 text-zinc-400 hover:text-zinc-200"
                onClick={() => setShow((v) => !v)}
            >
                {show ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
            </button>
        </div>
    );
}

function ProfileSection() {
    const { auth } = usePage<PageProps>().props;
    const user = auth.user!;
    const { data, setData, put, processing, errors } = useForm({
        name: user.name,
        email: user.email,
    });

    return (
        <Card className="border-zinc-800 bg-[#1e1e1e]">
            <CardHeader><CardTitle className="text-base text-zinc-100">Profile</CardTitle></CardHeader>
            <CardContent>
                <form onSubmit={(e) => { e.preventDefault(); put('/user/profile-information'); }} className="space-y-4">
                    <div className="space-y-1.5">
                        <Label className="text-zinc-300">Name</Label>
                        <Input value={data.name} onChange={(e) => setData('name', e.target.value)} className="border-zinc-700 bg-zinc-900 text-zinc-100" />
                        {errors.name && <p className="text-xs text-red-400">{errors.name}</p>}
                    </div>
                    <div className="space-y-1.5">
                        <Label className="text-zinc-300">Email</Label>
                        <Input type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} className="border-zinc-700 bg-zinc-900 text-zinc-100" />
                        {errors.email && <p className="text-xs text-red-400">{errors.email}</p>}
                    </div>
                    <Button type="submit" disabled={processing} size="sm">Save profile</Button>
                </form>
            </CardContent>
        </Card>
    );
}

function PasswordSection() {
    const { data, setData, put, processing, errors, reset } = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        put('/user/password', { onSuccess: () => reset() });
    }

    return (
        <Card className="border-zinc-800 bg-[#1e1e1e]">
            <CardHeader><CardTitle className="text-base text-zinc-100">Change Password</CardTitle></CardHeader>
            <CardContent>
                <form onSubmit={submit} className="space-y-4">
                    <div className="space-y-1.5">
                        <Label className="text-zinc-300">Current password</Label>
                        <PasswordInput value={data.current_password} onChange={(e) => setData('current_password', e.target.value)} />
                        {errors.current_password && <p className="text-xs text-red-400">{errors.current_password}</p>}
                    </div>
                    <div className="space-y-1.5">
                        <Label className="text-zinc-300">New password</Label>
                        <PasswordInput value={data.password} onChange={(e) => setData('password', e.target.value)} />
                        {errors.password && <p className="text-xs text-red-400">{errors.password}</p>}
                    </div>
                    <div className="space-y-1.5">
                        <Label className="text-zinc-300">Confirm new password</Label>
                        <PasswordInput value={data.password_confirmation} onChange={(e) => setData('password_confirmation', e.target.value)} />
                    </div>
                    <Button type="submit" disabled={processing} size="sm">Update password</Button>
                </form>
            </CardContent>
        </Card>
    );
}

function TwoFactorSection({ twoFactorEnabled }: { twoFactorEnabled: boolean }) {
    const [step, setStep] = useState<'idle' | 'qr' | 'confirm' | 'recovery'>('idle');
    const [qrSvg, setQrSvg] = useState('');
    const [manualKey, setManualKey] = useState('');
    const [recoveryCodes, setRecoveryCodes] = useState<string[]>([]);
    const [otp, setOtp] = useState('');
    const [loading, setLoading] = useState(false);
    const [disableOpen, setDisableOpen] = useState(false);
    const [copied, setCopied] = useState(false);
    const [error, setError] = useState('');

    const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

    async function enable2FA() {
        setLoading(true);
        setError('');
        try {
            await fetch('/user/two-factor-authentication', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken() } });
            const [qrRes, keyRes] = await Promise.all([
                fetch('/user/two-factor-qr-code', { headers: { 'X-Requested-With': 'XMLHttpRequest' } }),
                fetch('/user/two-factor-secret-key', { headers: { 'X-Requested-With': 'XMLHttpRequest' } }),
            ]);
            const qr = await qrRes.json();
            const key = await keyRes.json();
            setQrSvg(qr.svg ?? '');
            setManualKey(key.secretKey ?? '');
            setStep('qr');
        } catch {
            setError('Failed to enable 2FA. Please try again.');
        } finally {
            setLoading(false);
        }
    }

    async function confirm2FA() {
        setLoading(true);
        setError('');
        try {
            const res = await fetch('/user/confirmed-two-factor-authentication', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken() },
                body: JSON.stringify({ code: otp }),
            });
            if (!res.ok) { setError('Invalid code. Please try again.'); setLoading(false); return; }
            const rcRes = await fetch('/user/two-factor-recovery-codes', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            setRecoveryCodes(await rcRes.json());
            setStep('recovery');
        } catch {
            setError('Confirmation failed. Please try again.');
        } finally {
            setLoading(false);
        }
    }

    async function disable2FA() {
        setLoading(true);
        try {
            await fetch('/user/two-factor-authentication', { method: 'DELETE', headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken() } });
            router.reload();
        } finally {
            setLoading(false);
            setDisableOpen(false);
        }
    }

    function copyRecovery() {
        navigator.clipboard.writeText(recoveryCodes.join('\n'));
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    }

    return (
        <>
            <Card className="border-zinc-800 bg-[#1e1e1e]">
                <CardHeader>
                    <div className="flex items-center justify-between">
                        <CardTitle className="text-base text-zinc-100">Two-Factor Authentication</CardTitle>
                        {twoFactorEnabled && <Badge variant="success">Enabled</Badge>}
                    </div>
                    <CardDescription>Add an extra layer of security using a TOTP app (Google Authenticator, Authy, etc.).</CardDescription>
                </CardHeader>
                <CardContent>
                    {twoFactorEnabled ? (
                        <Button variant="destructive" size="sm" onClick={() => setDisableOpen(true)}>
                            <ShieldOff className="mr-2 h-4 w-4" />Disable 2FA
                        </Button>
                    ) : (
                        <Button size="sm" onClick={enable2FA} disabled={loading}>
                            <ShieldCheck className="mr-2 h-4 w-4" />Enable 2FA
                        </Button>
                    )}
                </CardContent>
            </Card>

            <Dialog open={step !== 'idle'} onOpenChange={(open) => { if (!open && step !== 'recovery') setStep('idle'); }}>
                <DialogContent className="sm:max-w-md border-zinc-800 bg-[#1e1e1e]" hideClose={step === 'recovery'}>
                    {step === 'qr' && (
                        <>
                            <DialogHeader>
                                <DialogTitle className="text-zinc-100">Set up Two-Factor Authentication</DialogTitle>
                                <DialogDescription>Scan this QR code with your authenticator app.</DialogDescription>
                            </DialogHeader>
                            <div className="flex flex-col items-center gap-4 py-2">
                                {qrSvg && <div className="rounded-lg border border-zinc-700 bg-white p-3" dangerouslySetInnerHTML={{ __html: qrSvg }} />}
                                <div className="w-full space-y-1">
                                    <p className="text-xs text-zinc-500">Or enter this key manually:</p>
                                    <code className="block w-full rounded border border-zinc-700 bg-zinc-900 px-3 py-2 text-center font-mono text-xs text-zinc-300 break-all">{manualKey}</code>
                                </div>
                            </div>
                            {error && <p className="text-sm text-red-400">{error}</p>}
                            <DialogFooter>
                                <Button variant="outline" onClick={() => setStep('idle')} className="border-zinc-700">Cancel</Button>
                                <Button onClick={() => setStep('confirm')}>Next</Button>
                            </DialogFooter>
                        </>
                    )}

                    {step === 'confirm' && (
                        <>
                            <DialogHeader>
                                <DialogTitle className="text-zinc-100">Confirm your code</DialogTitle>
                                <DialogDescription>Enter the 6-digit code from your authenticator app.</DialogDescription>
                            </DialogHeader>
                            <Input
                                autoFocus
                                maxLength={6}
                                placeholder="000000"
                                value={otp}
                                onChange={(e) => setOtp(e.target.value.replace(/\D/g, ''))}
                                className="text-center font-mono text-xl tracking-widest border-zinc-700 bg-zinc-900 text-zinc-100"
                            />
                            {error && <p className="text-sm text-red-400">{error}</p>}
                            <DialogFooter>
                                <Button variant="outline" onClick={() => setStep('qr')} className="border-zinc-700">Back</Button>
                                <Button onClick={confirm2FA} disabled={loading || otp.length !== 6}>Confirm</Button>
                            </DialogFooter>
                        </>
                    )}

                    {step === 'recovery' && (
                        <>
                            <DialogHeader>
                                <DialogTitle className="text-zinc-100">Save your recovery codes</DialogTitle>
                                <DialogDescription>Store these in a safe place. Each code can only be used once.</DialogDescription>
                            </DialogHeader>
                            <div className="rounded-lg border border-zinc-700 bg-zinc-900 p-4">
                                <div className="grid grid-cols-2 gap-1.5">
                                    {recoveryCodes.map((code) => (
                                        <code key={code} className="text-center font-mono text-xs text-zinc-300">{code}</code>
                                    ))}
                                </div>
                            </div>
                            <Button variant="outline" onClick={copyRecovery} className="w-full border-zinc-700">
                                {copied ? <><Check className="mr-2 h-4 w-4 text-emerald-400" />Copied!</> : <><Copy className="mr-2 h-4 w-4" />Copy all codes</>}
                            </Button>
                            <DialogFooter>
                                <Button onClick={() => router.reload()} className="w-full">I've saved my codes — finish</Button>
                            </DialogFooter>
                        </>
                    )}
                </DialogContent>
            </Dialog>

            <AlertDialog open={disableOpen} onOpenChange={setDisableOpen}>
                <AlertDialogContent className="border-zinc-800 bg-[#1e1e1e]">
                    <AlertDialogHeader>
                        <AlertDialogTitle className="text-zinc-100">Disable Two-Factor Authentication?</AlertDialogTitle>
                        <AlertDialogDescription>This will remove 2FA from your account. You can re-enable it at any time.</AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel className="border-zinc-700">Cancel</AlertDialogCancel>
                        <AlertDialogAction onClick={disable2FA} disabled={loading} className="bg-destructive text-destructive-foreground hover:bg-destructive/90">
                            Disable 2FA
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </>
    );
}

export default function Settings({ twoFactorEnabled, twoFactorConfirmed }: SettingsProps) {
    return (
        <AppLayout title="Settings">
            <Head title="Settings" />
            <div className="max-w-2xl space-y-6">
                <h1 className="text-xl font-semibold text-zinc-100">Settings</h1>

                <Tabs defaultValue="profile">
                    <TabsList className="bg-zinc-900 border border-zinc-800">
                        <TabsTrigger value="profile">Profile</TabsTrigger>
                        <TabsTrigger value="security">Security</TabsTrigger>
                        <TabsTrigger value="system">System</TabsTrigger>
                    </TabsList>

                    <TabsContent value="profile" className="mt-6 space-y-6">
                        <ProfileSection />
                    </TabsContent>

                    <TabsContent value="security" className="mt-6 space-y-6">
                        <PasswordSection />
                        <TwoFactorSection twoFactorEnabled={twoFactorEnabled} />
                    </TabsContent>

                    <TabsContent value="system" className="mt-6 space-y-6">
                        <Card className="border-zinc-800 bg-[#1e1e1e]">
                            <CardHeader>
                                <CardTitle className="text-base text-zinc-100">System Diagnostics</CardTitle>
                                <CardDescription>Inspect queue health, recent failed jobs, and sites that look stuck.</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <Button asChild size="sm">
                                    <a href="/dashboard/system"><Server className="mr-2 h-4 w-4" />Open diagnostics</a>
                                </Button>
                            </CardContent>
                        </Card>
                    </TabsContent>
                </Tabs>
            </div>
        </AppLayout>
    );
}
