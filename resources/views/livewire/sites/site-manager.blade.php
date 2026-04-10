<div class="w-full max-w-3xl">
    <div class="mb-8">
        <flux:button href="{{ route('sites.index') }}" variant="subtle" size="sm" icon="arrow-left" class="mb-4">
            Back to sites
        </flux:button>
        <flux:heading size="xl">Add a new site</flux:heading>
        <flux:subheading>Set up a new project with client info, repository, and integrations.</flux:subheading>
    </div>

    <form wire:submit="create" class="space-y-10">

        {{-- 1 — Client information --}}
        <section class="space-y-6">
            <div class="flex items-center gap-3">
                <span class="flex items-center justify-center size-7 rounded-full bg-emerald-600 text-white text-xs font-bold shrink-0">1</span>
                <flux:heading size="lg">Client information</flux:heading>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>First name</flux:label>
                    <flux:input wire:model="clientFirstName" placeholder="Robert" />
                    <flux:error name="clientFirstName" />
                </flux:field>
                <flux:field>
                    <flux:label>Last name</flux:label>
                    <flux:input wire:model="clientLastName" placeholder="Artho" />
                    <flux:error name="clientLastName" />
                </flux:field>
            </div>

            <flux:field>
                <flux:label>Email address</flux:label>
                <flux:input type="email" wire:model="clientEmail" placeholder="robert@example.com" />
                <flux:error name="clientEmail" />
            </flux:field>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <flux:field>
                    <flux:label badge="Optional">Phone number</flux:label>
                    <flux:input wire:model="clientPhone" placeholder="+41 79 123 4567" />
                    <flux:error name="clientPhone" />
                </flux:field>
                <flux:field>
                    <flux:label badge="Optional">Company</flux:label>
                    <flux:input wire:model="clientCompany" placeholder="Sommerstein GmbH" />
                    <flux:error name="clientCompany" />
                </flux:field>
            </div>

            <flux:field>
                <flux:label badge="Optional">Address</flux:label>
                <flux:input wire:model="clientAddress" placeholder="Bahnhofstrasse 12, 8001 Zürich, Switzerland" />
                <flux:error name="clientAddress" />
            </flux:field>

            <flux:field>
                <flux:label badge="Optional">Notes</flux:label>
                <flux:textarea wire:model="clientNotes" placeholder="e.g. Prefers communication in German, billing via invoice." rows="3" />
                <flux:error name="clientNotes" />
            </flux:field>
        </section>

        <flux:separator />

        {{-- 2 — Project setup --}}
        <section class="space-y-6">
            <div class="flex items-center gap-3">
                <span class="flex items-center justify-center size-7 rounded-full bg-emerald-600 text-white text-xs font-bold shrink-0">2</span>
                <flux:heading size="lg">Project setup</flux:heading>
            </div>

            <flux:field>
                <flux:label>Site / project name</flux:label>
                <flux:input wire:model="name" placeholder="My Portfolio" />
                <flux:error name="name" />
            </flux:field>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>Project type</flux:label>
                    <flux:select wire:model="projectType">
                        <flux:select.option value="static_html">Static HTML</flux:select.option>
                        <flux:select.option value="react">React</flux:select.option>
                        <flux:select.option value="vue">Vue</flux:select.option>
                        <flux:select.option value="svelte">Svelte</flux:select.option>
                        <flux:select.option value="astro">Astro</flux:select.option>
                        <flux:select.option value="hugo">Hugo</flux:select.option>
                        <flux:select.option value="eleventy">Eleventy</flux:select.option>
                        <flux:select.option value="nextjs">Next.js</flux:select.option>
                        <flux:select.option value="nuxt">Nuxt</flux:select.option>
                        <flux:select.option value="custom">Custom</flux:select.option>
                    </flux:select>
                    <flux:error name="projectType" />
                </flux:field>
                <flux:field>
                    <flux:label badge="Optional">Billing cycle</flux:label>
                    <flux:select wire:model="billingCycle">
                        <flux:select.option value="monthly">Monthly</flux:select.option>
                        <flux:select.option value="quarterly">Quarterly</flux:select.option>
                        <flux:select.option value="yearly">Yearly</flux:select.option>
                    </flux:select>
                    <flux:error name="billingCycle" />
                </flux:field>
            </div>

            <flux:field>
                <flux:label badge="Optional">Monthly retainer</flux:label>
                <flux:input type="number" wire:model="monthlyRetainer" placeholder="0.00" step="0.01" min="0" icon="currency-euro" />
                <flux:error name="monthlyRetainer" />
            </flux:field>
        </section>

        <flux:separator />

        {{-- 3 — Repository & deployment --}}
        <section class="space-y-6">
            <div class="flex items-center gap-3">
                <span class="flex items-center justify-center size-7 rounded-full bg-emerald-600 text-white text-xs font-bold shrink-0">3</span>
                <flux:heading size="lg">Repository & deployment</flux:heading>
            </div>

            <flux:field>
                <flux:label>GitHub repository URL</flux:label>
                <flux:input wire:model="repoUrl" placeholder="https://github.com/user/repo.git" class="font-mono" />
                <flux:error name="repoUrl" />
            </flux:field>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>Branch</flux:label>
                    <flux:input wire:model="branch" placeholder="main" class="font-mono" />
                    <flux:error name="branch" />
                </flux:field>
                <flux:field>
                    <flux:label badge="Optional">Build command</flux:label>
                    <flux:input wire:model="buildCommand" placeholder="npm run build" class="font-mono" />
                    <flux:error name="buildCommand" />
                </flux:field>
            </div>

            <flux:field>
                <flux:label badge="Optional">GitHub personal access token</flux:label>
                <flux:input type="password" wire:model="githubToken" placeholder="ghp_xxxxxxxxxxxx" class="font-mono" viewable />
                <flux:description>Encrypted at rest. Needs <code class="text-xs">repo</code> scope.</flux:description>
                <flux:error name="githubToken" />
            </flux:field>
        </section>

        <flux:separator />

        {{-- 4 — Domain & SSL --}}
        <section class="space-y-6">
            <div class="flex items-center gap-3">
                <span class="flex items-center justify-center size-7 rounded-full bg-emerald-600 text-white text-xs font-bold shrink-0">4</span>
                <flux:heading size="lg">Domain & SSL</flux:heading>
            </div>

            <flux:field>
                <flux:label badge="Optional — can be set later">Custom domain</flux:label>
                <flux:input wire:model="domain" placeholder="www.example.com" />
                <flux:error name="domain" />
            </flux:field>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>SSL provider</flux:label>
                    <flux:select wire:model="sslProvider">
                        <flux:select.option value="letsencrypt">Let's Encrypt (free)</flux:select.option>
                        <flux:select.option value="cloudflare">Cloudflare</flux:select.option>
                        <flux:select.option value="custom">Custom</flux:select.option>
                        <flux:select.option value="none">None</flux:select.option>
                    </flux:select>
                    <flux:error name="sslProvider" />
                </flux:field>
                <flux:field>
                    <flux:label badge="Optional">DNS provider</flux:label>
                    <flux:select wire:model="dnsProvider">
                        <flux:select.option value="">—</flux:select.option>
                        <flux:select.option value="cloudflare">Cloudflare</flux:select.option>
                        <flux:select.option value="namecheap">Namecheap</flux:select.option>
                        <flux:select.option value="route53">Route 53</flux:select.option>
                        <flux:select.option value="godaddy">GoDaddy</flux:select.option>
                        <flux:select.option value="other">Other</flux:select.option>
                    </flux:select>
                    <flux:error name="dnsProvider" />
                </flux:field>
            </div>
        </section>

        <flux:separator />

        {{-- 5 — Integrations (optional sub-groups) --}}
        <section class="space-y-8">
            <div class="flex items-center gap-3 flex-wrap">
                <span class="flex items-center justify-center size-7 rounded-full bg-emerald-600 text-white text-xs font-bold shrink-0">5</span>
                <div class="flex items-center gap-2 flex-wrap">
                    <flux:heading size="lg">Integrations</flux:heading>
                    <flux:badge color="zinc" size="sm">Optional</flux:badge>
                </div>
            </div>

            <div class="space-y-6">
                <flux:field>
                    <flux:label badge="Optional">Google Analytics measurement ID</flux:label>
                    <flux:input wire:model="gaPropertyId" placeholder="G-XXXXXXXXXX" class="font-mono" />
                    <flux:description>
                        <a
                            href="https://support.google.com/analytics/answer/9304153"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="text-emerald-600 dark:text-emerald-400 hover:underline"
                        >Found in Google Analytics → Admin → Data streams</a>
                    </flux:description>
                    <flux:error name="gaPropertyId" />
                </flux:field>

                <flux:field>
                    <flux:label badge="Optional">Google Search Console property</flux:label>
                    <flux:input wire:model="gscProperty" placeholder="https://www.example.com" />
                    <flux:error name="gscProperty" />
                </flux:field>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <flux:field>
                        <flux:label badge="Optional">Google Tag Manager ID</flux:label>
                        <flux:input wire:model="gtmId" placeholder="GTM-XXXXXXX" class="font-mono" />
                        <flux:error name="gtmId" />
                    </flux:field>
                    <flux:field>
                        <flux:label badge="Optional">Google Ads ID</flux:label>
                        <flux:input wire:model="googleAdsId" placeholder="AW-XXXXXXXXX" class="font-mono" />
                        <flux:error name="googleAdsId" />
                    </flux:field>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <flux:field>
                        <flux:label badge="Optional">Cloudflare API token</flux:label>
                        <flux:input type="password" wire:model="cfApiToken" placeholder="API token" viewable />
                        <flux:error name="cfApiToken" />
                    </flux:field>
                    <flux:field>
                        <flux:label badge="Optional">Cloudflare Zone ID</flux:label>
                        <flux:input wire:model="cfZoneId" placeholder="Zone ID" class="font-mono" />
                        <flux:error name="cfZoneId" />
                    </flux:field>
                </div>
            </div>

            <div class="space-y-6 pt-2 border-t border-zinc-200 dark:border-zinc-700">
                <div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <flux:heading size="sm">SMTP relay / transactional email</flux:heading>
                        <flux:badge color="zinc" size="sm">Optional</flux:badge>
                    </div>
                    <flux:subheading class="mt-1">Used for form submissions and client notifications.</flux:subheading>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <flux:field>
                        <flux:label badge="Optional">SMTP host</flux:label>
                        <flux:input wire:model="smtpHost" placeholder="smtp.example.com" class="font-mono" />
                        <flux:error name="smtpHost" />
                    </flux:field>
                    <flux:field>
                        <flux:label badge="Optional">Port</flux:label>
                        <flux:input type="number" wire:model="smtpPort" placeholder="587" />
                        <flux:error name="smtpPort" />
                    </flux:field>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <flux:field>
                        <flux:label badge="Optional">SMTP username</flux:label>
                        <flux:input wire:model="smtpUsername" placeholder="SMTP username" />
                        <flux:error name="smtpUsername" />
                    </flux:field>
                    <flux:field>
                        <flux:label badge="Optional">SMTP password</flux:label>
                        <flux:input type="password" wire:model="smtpPassword" placeholder="SMTP password" viewable />
                        <flux:error name="smtpPassword" />
                    </flux:field>
                </div>
            </div>

            <div class="space-y-6 pt-2 border-t border-zinc-200 dark:border-zinc-700">
                <div class="flex items-center gap-2 flex-wrap">
                    <flux:heading size="sm">Hosting / server access</flux:heading>
                    <flux:badge color="zinc" size="sm">Optional</flux:badge>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <flux:field>
                        <flux:label badge="Optional">Hosting provider</flux:label>
                        <flux:select wire:model="hostingProvider">
                            <flux:select.option value="">—</flux:select.option>
                            <flux:select.option value="hetzner">Hetzner</flux:select.option>
                            <flux:select.option value="digitalocean">DigitalOcean</flux:select.option>
                            <flux:select.option value="aws">AWS</flux:select.option>
                            <flux:select.option value="vercel">Vercel</flux:select.option>
                            <flux:select.option value="netlify">Netlify</flux:select.option>
                            <flux:select.option value="other">Other</flux:select.option>
                        </flux:select>
                        <flux:error name="hostingProvider" />
                    </flux:field>
                    <flux:field>
                        <flux:label badge="Optional">SSH host or panel URL</flux:label>
                        <flux:input wire:model="sshHost" placeholder="SSH host or panel URL" />
                        <flux:error name="sshHost" />
                    </flux:field>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <flux:field>
                        <flux:label badge="Optional">FTP / SSH user</flux:label>
                        <flux:input wire:model="ftpSshUser" placeholder="username" class="font-mono" />
                        <flux:error name="ftpSshUser" />
                    </flux:field>
                    <flux:field>
                        <flux:label badge="Optional">FTP / SSH password</flux:label>
                        <flux:input type="password" wire:model="ftpSshPassword" placeholder="password" viewable />
                        <flux:error name="ftpSshPassword" />
                    </flux:field>
                </div>
            </div>
        </section>

        <flux:separator />

        {{-- Actions --}}
        <div class="flex flex-wrap items-center gap-3 pb-6">
            <flux:button
                type="submit"
                variant="primary"
                class="!bg-emerald-500 hover:!bg-emerald-400 !text-zinc-950 dark:!text-zinc-950"
                wire:loading.attr="disabled"
            >
                <span wire:loading.remove wire:target="create">Create project</span>
                <span wire:loading wire:target="create">Creating...</span>
            </flux:button>
            <flux:button href="{{ route('sites.index') }}" variant="ghost">Cancel</flux:button>
        </div>
    </form>
</div>
