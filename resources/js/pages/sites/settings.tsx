import { Head, useForm } from '@inertiajs/react';
import AppLayout from '@/layouts/AppLayout';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Separator } from '@/components/ui/separator';
import { Tabs, TabsList, TabsTrigger, TabsContent } from '@/components/ui/tabs';
import { Save } from 'lucide-react';

interface Site {
    id: string; name: string; domain: string | null; project_type: string | null;
    client_first_name: string | null; client_last_name: string | null;
    client_email: string | null; client_phone: string | null;
    client_company: string | null; client_address: string | null; client_notes: string | null;
    billing_cycle: string | null; monthly_retainer: string | null;
    ga_property_id: string | null; gtm_id: string | null; google_ads_id: string | null;
    cf_zone_id: string | null; cf_api_token: string | null;
    smtp_host: string | null; smtp_port: number | null; smtp_username: string | null; smtp_password: string | null;
    ssh_host: string | null; ftp_ssh_user: string | null; ftp_ssh_password: string | null;
    hosting_provider: string | null; ssl_provider: string | null; dns_provider: string | null;
}

function FieldGroup({ label, hint, children }: { label: string; hint?: string; children: React.ReactNode }) {
    return (
        <div className="grid grid-cols-1 gap-1 md:grid-cols-[220px_1fr] md:gap-6 md:items-start">
            <div className="pt-1">
                <p className="text-sm font-medium text-zinc-200">{label}</p>
                {hint && <p className="mt-0.5 text-xs text-zinc-500">{hint}</p>}
            </div>
            <div>{children}</div>
        </div>
    );
}

function Row({ children }: { children: React.ReactNode }) {
    return <div className="flex flex-col gap-3 sm:flex-row">{children}</div>;
}

function Field({ label, error, children }: { label: string; error?: string; children: React.ReactNode }) {
    return (
        <div className="flex-1 space-y-1">
            <Label className="text-xs text-zinc-400">{label}</Label>
            {children}
            {error && <p className="text-xs text-red-400">{error}</p>}
        </div>
    );
}

export default function SiteSettings({ site }: { site: Site }) {
    const { data, setData, put, processing, errors } = useForm({
        name: site.name,
        domain: site.domain ?? '',
        project_type: site.project_type ?? '',
        client_first_name: site.client_first_name ?? '',
        client_last_name: site.client_last_name ?? '',
        client_email: site.client_email ?? '',
        client_phone: site.client_phone ?? '',
        client_company: site.client_company ?? '',
        client_address: site.client_address ?? '',
        client_notes: site.client_notes ?? '',
        billing_cycle: site.billing_cycle ?? '',
        monthly_retainer: site.monthly_retainer ?? '',
        ga_property_id: site.ga_property_id ?? '',
        gtm_id: site.gtm_id ?? '',
        google_ads_id: site.google_ads_id ?? '',
        cf_zone_id: site.cf_zone_id ?? '',
        cf_api_token: site.cf_api_token ?? '',
        smtp_host: site.smtp_host ?? '',
        smtp_port: String(site.smtp_port ?? ''),
        smtp_username: site.smtp_username ?? '',
        smtp_password: site.smtp_password ?? '',
        ssh_host: site.ssh_host ?? '',
        ftp_ssh_user: site.ftp_ssh_user ?? '',
        ftp_ssh_password: site.ftp_ssh_password ?? '',
        hosting_provider: site.hosting_provider ?? '',
        ssl_provider: site.ssl_provider ?? '',
        dns_provider: site.dns_provider ?? '',
    });

    const s = (k: keyof typeof data) => (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) => setData(k, e.target.value);
    const submit = (e: React.FormEvent) => { e.preventDefault(); put(`/dashboard/sites/${site.id}/settings`); };

    return (
        <AppLayout title="Site Settings">
            <Head title={`Settings — ${site.name}`} />
            <form onSubmit={submit}>
                <div className="space-y-6">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h1 className="text-xl font-semibold text-zinc-100">Settings</h1>
                            <p className="text-sm text-zinc-400">{site.name}</p>
                        </div>
                        <Button type="submit" size="sm" disabled={processing}>
                            <Save className="mr-1.5 h-3.5 w-3.5" />Save changes
                        </Button>
                    </div>

                    <Tabs defaultValue="general">
                        <TabsList className="bg-zinc-900 border border-zinc-800">
                            <TabsTrigger value="general">General</TabsTrigger>
                            <TabsTrigger value="client">Client</TabsTrigger>
                            <TabsTrigger value="integrations">Integrations</TabsTrigger>
                            <TabsTrigger value="server">Server & SSH</TabsTrigger>
                        </TabsList>

                        {/* General */}
                        <TabsContent value="general" className="mt-4">
                            <Card className="border-zinc-800 bg-[#1e1e1e]">
                                <CardContent className="pt-6 space-y-6">
                                    <FieldGroup label="Project name">
                                        <Input value={data.name} onChange={s('name')} className="border-zinc-700 bg-zinc-900 text-zinc-100" />
                                        {errors.name && <p className="mt-1 text-xs text-red-400">{errors.name}</p>}
                                    </FieldGroup>
                                    <Separator className="bg-zinc-800" />
                                    <FieldGroup label="Primary domain" hint="The live URL for this site.">
                                        <Input value={data.domain} onChange={s('domain')} placeholder="example.com" className="border-zinc-700 bg-zinc-900 text-zinc-100" />
                                    </FieldGroup>
                                    <Separator className="bg-zinc-800" />
                                    <FieldGroup label="Project type">
                                        <Input value={data.project_type} onChange={s('project_type')} placeholder="e.g. wordpress, nextjs" className="border-zinc-700 bg-zinc-900 text-zinc-100" />
                                    </FieldGroup>
                                    <Separator className="bg-zinc-800" />
                                    <FieldGroup label="SSL & DNS">
                                        <Row>
                                            <Field label="SSL provider">
                                                <Input value={data.ssl_provider} onChange={s('ssl_provider')} placeholder="Let's Encrypt" className="border-zinc-700 bg-zinc-900 text-zinc-100" />
                                            </Field>
                                            <Field label="DNS provider">
                                                <Input value={data.dns_provider} onChange={s('dns_provider')} placeholder="Cloudflare" className="border-zinc-700 bg-zinc-900 text-zinc-100" />
                                            </Field>
                                        </Row>
                                    </FieldGroup>
                                </CardContent>
                            </Card>
                        </TabsContent>

                        {/* Client */}
                        <TabsContent value="client" className="mt-4">
                            <Card className="border-zinc-800 bg-[#1e1e1e]">
                                <CardHeader>
                                    <CardTitle className="text-sm font-medium text-zinc-300">Client information</CardTitle>
                                    <CardDescription className="text-xs text-zinc-500">Used on invoices and reports.</CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <Row>
                                        <Field label="First name">
                                            <Input value={data.client_first_name} onChange={s('client_first_name')} className="border-zinc-700 bg-zinc-900 text-zinc-100" />
                                        </Field>
                                        <Field label="Last name">
                                            <Input value={data.client_last_name} onChange={s('client_last_name')} className="border-zinc-700 bg-zinc-900 text-zinc-100" />
                                        </Field>
                                    </Row>
                                    <Row>
                                        <Field label="Email">
                                            <Input type="email" value={data.client_email} onChange={s('client_email')} className="border-zinc-700 bg-zinc-900 text-zinc-100" />
                                        </Field>
                                        <Field label="Phone">
                                            <Input value={data.client_phone} onChange={s('client_phone')} className="border-zinc-700 bg-zinc-900 text-zinc-100" />
                                        </Field>
                                    </Row>
                                    <Row>
                                        <Field label="Company">
                                            <Input value={data.client_company} onChange={s('client_company')} className="border-zinc-700 bg-zinc-900 text-zinc-100" />
                                        </Field>
                                        <Field label="Address">
                                            <Input value={data.client_address} onChange={s('client_address')} className="border-zinc-700 bg-zinc-900 text-zinc-100" />
                                        </Field>
                                    </Row>
                                    <Field label="Notes">
                                        <Textarea value={data.client_notes} onChange={s('client_notes')} rows={3} className="border-zinc-700 bg-zinc-900 text-zinc-100" />
                                    </Field>
                                    <Separator className="bg-zinc-800" />
                                    <p className="text-xs font-semibold uppercase tracking-widest text-zinc-500">Billing</p>
                                    <Row>
                                        <Field label="Billing cycle">
                                            <Input value={data.billing_cycle} onChange={s('billing_cycle')} placeholder="monthly" className="border-zinc-700 bg-zinc-900 text-zinc-100" />
                                        </Field>
                                        <Field label="Monthly retainer (€)">
                                            <Input type="number" step="0.01" value={data.monthly_retainer} onChange={s('monthly_retainer')} className="border-zinc-700 bg-zinc-900 text-zinc-100" />
                                        </Field>
                                    </Row>
                                </CardContent>
                            </Card>
                        </TabsContent>

                        {/* Integrations */}
                        <TabsContent value="integrations" className="mt-4 space-y-4">
                            <Card className="border-zinc-800 bg-[#1e1e1e]">
                                <CardHeader>
                                    <CardTitle className="text-sm font-medium text-zinc-300">Cloudflare</CardTitle>
                                    <CardDescription className="text-xs text-zinc-500">Cache purging and DNS management.</CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-3">
                                    <Field label="Zone ID">
                                        <Input value={data.cf_zone_id} onChange={s('cf_zone_id')} placeholder="a1b2c3d4..." className="border-zinc-700 bg-zinc-900 text-zinc-100 font-mono text-xs" />
                                    </Field>
                                    <Field label="API Token">
                                        <Input type="password" value={data.cf_api_token} onChange={s('cf_api_token')} placeholder="••••••••" className="border-zinc-700 bg-zinc-900 text-zinc-100 font-mono text-xs" />
                                    </Field>
                                </CardContent>
                            </Card>

                            <Card className="border-zinc-800 bg-[#1e1e1e]">
                                <CardHeader>
                                    <CardTitle className="text-sm font-medium text-zinc-300">Analytics</CardTitle>
                                    <CardDescription className="text-xs text-zinc-500">Google Analytics, Tag Manager, and Ads.</CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-3">
                                    <Row>
                                        <Field label="GA4 Property ID">
                                            <Input value={data.ga_property_id} onChange={s('ga_property_id')} placeholder="G-XXXXXXXXXX" className="border-zinc-700 bg-zinc-900 text-zinc-100" />
                                        </Field>
                                        <Field label="GTM Container ID">
                                            <Input value={data.gtm_id} onChange={s('gtm_id')} placeholder="GTM-XXXXXX" className="border-zinc-700 bg-zinc-900 text-zinc-100" />
                                        </Field>
                                    </Row>
                                    <Field label="Google Ads ID">
                                        <Input value={data.google_ads_id} onChange={s('google_ads_id')} placeholder="AW-XXXXXXXXX" className="border-zinc-700 bg-zinc-900 text-zinc-100" />
                                    </Field>
                                </CardContent>
                            </Card>

                            <Card className="border-zinc-800 bg-[#1e1e1e]">
                                <CardHeader>
                                    <CardTitle className="text-sm font-medium text-zinc-300">SMTP / Email</CardTitle>
                                    <CardDescription className="text-xs text-zinc-500">Outgoing mail server for this site.</CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-3">
                                    <Row>
                                        <Field label="SMTP Host">
                                            <Input value={data.smtp_host} onChange={s('smtp_host')} placeholder="smtp.example.com" className="border-zinc-700 bg-zinc-900 text-zinc-100" />
                                        </Field>
                                        <Field label="Port">
                                            <Input type="number" value={data.smtp_port} onChange={s('smtp_port')} placeholder="587" className="border-zinc-700 bg-zinc-900 text-zinc-100 w-28" />
                                        </Field>
                                    </Row>
                                    <Row>
                                        <Field label="Username">
                                            <Input value={data.smtp_username} onChange={s('smtp_username')} className="border-zinc-700 bg-zinc-900 text-zinc-100" />
                                        </Field>
                                        <Field label="Password">
                                            <Input type="password" value={data.smtp_password} onChange={s('smtp_password')} placeholder="••••••••" className="border-zinc-700 bg-zinc-900 text-zinc-100" />
                                        </Field>
                                    </Row>
                                </CardContent>
                            </Card>
                        </TabsContent>

                        {/* Server & SSH */}
                        <TabsContent value="server" className="mt-4 space-y-4">
                            <Card className="border-zinc-800 bg-[#1e1e1e]">
                                <CardHeader>
                                    <CardTitle className="text-sm font-medium text-zinc-300">SSH / FTP Access</CardTitle>
                                    <CardDescription className="text-xs text-zinc-500">Used for deployments and file management.</CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-3">
                                    <Row>
                                        <Field label="Hosting provider">
                                            <Input value={data.hosting_provider} onChange={s('hosting_provider')} placeholder="e.g. Hetzner, DigitalOcean" className="border-zinc-700 bg-zinc-900 text-zinc-100" />
                                        </Field>
                                        <Field label="SSH host / IP">
                                            <Input value={data.ssh_host} onChange={s('ssh_host')} placeholder="192.168.1.1" className="border-zinc-700 bg-zinc-900 text-zinc-100 font-mono text-xs" />
                                        </Field>
                                    </Row>
                                    <Row>
                                        <Field label="Username">
                                            <Input value={data.ftp_ssh_user} onChange={s('ftp_ssh_user')} placeholder="root" className="border-zinc-700 bg-zinc-900 text-zinc-100" />
                                        </Field>
                                        <Field label="Password / key passphrase">
                                            <Input type="password" value={data.ftp_ssh_password} onChange={s('ftp_ssh_password')} placeholder="••••••••" className="border-zinc-700 bg-zinc-900 text-zinc-100" />
                                        </Field>
                                    </Row>
                                </CardContent>
                            </Card>
                        </TabsContent>
                    </Tabs>
                </div>
            </form>
        </AppLayout>
    );
}
