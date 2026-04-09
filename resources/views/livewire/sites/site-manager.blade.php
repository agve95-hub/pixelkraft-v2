<div class="w-full max-w-3xl mx-auto">
    <div class="mb-8">
        <flux:button href="{{ route('sites.index') }}" variant="subtle" size="sm" icon="arrow-left" class="mb-4">
            Back to sites
        </flux:button>
        <flux:heading size="xl">Add a new site</flux:heading>
        <flux:subheading>Set up a new project with client info, repository, and integrations.</flux:subheading>
    </div>

    <form wire:submit="create" class="space-y-10">

        {{-- 1 ─ Client information --}}
        <section class="space-y-6">
            <div class="flex items-center gap-3">
                <span class="flex items-center justify-center size-7 rounded-full bg-emerald-600 text-white text-xs font-bold shrink-0">1</span>
                <div>
                    <flux:heading size="lg">Client information</flux:heading>
                    <flux:subheading>Who is this project for?</flux:subheading>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>First name</flux:label>
                    <flux:input wire:model="clientFirstName" placeholder="Robert" />
                    <flux:error name="clientFirstName" />
                </flux:field>
                <flux:field>
                    <flux:label>Last name</flux:label>
                    <flux:input wire:model="clientLastName" placeholder="Arbo" />
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

        {{-- 2 ─ Project setup --}}
        <section class="space-y-6">
            <div class="flex items-center gap-3">
                <span class="flex items-center justify-center size-7 rounded-full bg-emerald-600 text-white text-xs font-bold shrink-0">2</span>
                <div>
                    <flux:heading size="lg">Project setup</flux:heading>
                    <flux:subheading>Name your project and connect the codebase.</flux:subheading>
                </div>
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

        {{-- 3 ─ Repository & deployment --}}
        <section class="space-y-6">
            <div class="flex items-center gap-3">
                <span class="flex items-center justify-center size-7 rounded-full bg-emerald-600 text-white text-xs font-bold shrink-0">3</span>
                <div>
                    <flux:heading size="lg">Repository & deployment</flux:heading>
                    <flux:subheading>Connect the GitHub repo to enable deploys.</flux:subheading>
                </div>
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
                <flux:label badge="Required for private repos">GitHub personal access token</flux:label>
                <flux:input type="password" wire:model="githubToken" placeholder="ghp_xxxxxxxxxxxx" class="font-mono" viewable />
                <flux:description>Encrypted at rest. Needs <code>repo</code> scope.</flux:description>
                <flux:error name="githubToken" />
            </flux:field>
        </section>

        <flux:separator />

        {{-- 4 ─ Domain & SSL --}}
        <section class="space-y-6">
            <div class="flex items-center gap-3">
                <span class="flex items-center justify-center size-7 rounded-full bg-emerald-600 text-white text-xs font-bold shrink-0">4</span>
                <div>
                    <flux:heading size="lg">Domain & SSL</flux:heading>
                    <flux:subheading>Configure the production domain and certificate.</flux:subheading>
                </div>
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

        {{-- 5 ─ Integrations --}}
        <section class="space-y-6">
            <div class="flex items-center gap-3">
                <span class="flex items-center justify-center size-7 rounded-full bg-emerald-600 text-white text-xs font-bold shrink-0">5</span>
                <div>
                    <flux:heading size="lg">Integrations</flux:heading>
                    <flux:subheading>Connect third-party services — all optional, can be added later.</flux:subheading>
                </div>
            </div>

            <flux:field>
                <flux:label badge="Optional">Google Analytics measurement ID</flux:label>
                <flux:input wire:model="gaPropertyId" placeholder="G-XXXXXXXXXX" class="font-mono" />
                <flux:description>Search → Google Analytics → Admin → Data streams.</flux:description>
                <flux:error name="gaPropertyId" />
            </flux:field>

            <flux:field>
                <flux:label badge="Optional">Google Search Console property</flux:label>
                <flux:input wire:model="gscProperty" placeholder="https://www.example.com" />
                <flux:description>Enables SEO crawl data and indexing status.</flux:description>
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
                    <flux:input type="password" wire:model="cfApiToken" placeholder="Bearer token" viewable />
                    <flux:error name="cfApiToken" />
                </flux:field>
                <flux:field>
                    <flux:label badge="Optional">Cloudflare Zone ID</flux:label>
                    <flux:input wire:model="cfZoneId" placeholder="Zone ID" class="font-mono" />
                    <flux:error name="cfZoneId" />
                </flux:field>
            </div>
            <flux:description>For DNS and cache purge.</flux:description>
        </section>

        <flux:separator />

        {{-- 6 ─ SMTP --}}
        <section class="space-y-6">
            <div class="flex items-center gap-3">
                <span class="flex items-center justify-center size-7 rounded-full bg-emerald-600 text-white text-xs font-bold shrink-0">6</span>
                <div>
                    <flux:heading size="lg">SMTP relay / transactional email</flux:heading>
                    <flux:subheading badge="Optional">Used for form submissions and client notifications.</flux:subheading>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>SMTP host</flux:label>
                    <flux:input wire:model="smtpHost" placeholder="SMTP host" class="font-mono" />
                    <flux:error name="smtpHost" />
                </flux:field>
                <flux:field>
                    <flux:label>Port (587)</flux:label>
                    <flux:input type="number" wire:model="smtpPort" placeholder="587" />
                    <flux:error name="smtpPort" />
                </flux:field>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>SMTP username</flux:label>
                    <flux:input wire:model="smtpUsername" placeholder="SMTP username" />
                    <flux:error name="smtpUsername" />
                </flux:field>
                <flux:field>
                    <flux:label>SMTP password</flux:label>
                    <flux:input type="password" wire:model="smtpPassword" placeholder="SMTP password" viewable />
                    <flux:error name="smtpPassword" />
                </flux:field>
            </div>
        </section>

        <flux:separator />

        {{-- 7 ─ Hosting / server access --}}
        <section class="space-y-6">
            <div class="flex items-center gap-3">
                <span class="flex items-center justify-center size-7 rounded-full bg-emerald-600 text-white text-xs font-bold shrink-0">7</span>
                <div>
                    <flux:heading size="lg">Hosting / server access</flux:heading>
                    <flux:subheading badge="Optional">Store credentials for deployment targets.</flux:subheading>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>Hosting provider</flux:label>
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
                    <flux:label>SSH host or panel URL</flux:label>
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
        </section>

        <flux:separator />

        {{-- Actions --}}
        <div class="flex items-center gap-3 pb-6">
            <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="create">Create project</span>
                <span wire:loading wire:target="create">Creating...</span>
            </flux:button>
            <flux:button href="{{ route('sites.index') }}" variant="ghost">Cancel</flux:button>
        </div>
    </form>
</div>
