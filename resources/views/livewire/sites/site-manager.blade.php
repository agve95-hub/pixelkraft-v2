<div class="w-full max-w-6xl">
    <div class="ui-page-head">
        <div>
            <a href="{{ route('sites.index') }}" class="back-link mb-3">
                <flux:icon name="chevron-left" class="size-3.5" /> Sites
            </a>
            <h1 class="ui-page-title">Add a new site</h1>
            <p class="ui-page-sub">Set up a project with client info, source files, deployment checks, and production gates.</p>
        </div>
        <x-ui.button type="button" variant="outline" size="sm" wire:click="checkSourceReadiness">Run readiness checks</x-ui.button>
    </div>

    <form wire:submit="create" class="space-y-8">
        <div class="ui-stepper">
            @foreach ([['Client', 'green'], ['Project', 'blue'], ['Deploy', 'violet'], ['Domain', 'orange'], ['Integrations', 'amber']] as $i => [$label, $tone])
                @php($locked = $i >= 3 && $sourceCheckStatus !== 'ready')
                <div class="ui-step {{ $locked ? 'is-locked' : '' }}">
                    <span class="ui-step-dot ui-step-{{ $tone }}">{{ $i + 1 }}</span>
                    <span>{{ $label }}</span>
                </div>
            @endforeach
        </div>

        <section class="ui-section-block">
            <div class="ui-section-title">
                <span class="ui-step-dot ui-step-green">1</span>
                Client information
            </div>
            <p class="ui-section-help">Primary contact data is reused for approvals, invoices, reports, and maintenance pages.</p>

            <x-ui.card>
                <div class="grid gap-4 sm:grid-cols-2">
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
                    <flux:field>
                        <flux:label>Email address</flux:label>
                        <flux:input type="email" wire:model="clientEmail" placeholder="robert@example.com" />
                        <flux:error name="clientEmail" />
                    </flux:field>
                    <flux:field>
                        <flux:label badge="Optional">Phone number</flux:label>
                        <flux:input wire:model="clientPhone" placeholder="+41 79 123 4567" />
                        <flux:error name="clientPhone" />
                    </flux:field>
                    <flux:field>
                        <flux:label badge="Optional">Company</flux:label>
                        <flux:input wire:model="clientCompany" placeholder="Sonnenrobert GmbH" />
                        <flux:error name="clientCompany" />
                    </flux:field>
                    <flux:field>
                        <flux:label badge="Optional">Outage contact note</flux:label>
                        <flux:input wire:model="clientNotes" placeholder="Shown on downtime page if needed" />
                        <flux:error name="clientNotes" />
                    </flux:field>
                </div>
                <div class="mt-4">
                    <flux:field>
                        <flux:label badge="Optional">Address</flux:label>
                        <flux:input wire:model="clientAddress" placeholder="Bahnhofstrasse 12, 8001 Zurich, Switzerland" />
                        <flux:error name="clientAddress" />
                    </flux:field>
                </div>
            </x-ui.card>
        </section>

        <section class="ui-section-block">
            <div class="ui-section-title">
                <span class="ui-step-dot ui-step-blue">2</span>
                Project setup
            </div>
            <p class="ui-section-help">The system can detect the project type from a Git source or start as an upload draft.</p>

            <x-ui.card>
                <flux:field>
                    <flux:label>Site / project name</flux:label>
                    <flux:input wire:model="name" placeholder="My Portfolio" />
                    <flux:error name="name" />
                </flux:field>

                <div class="mt-4 grid gap-4 lg:grid-cols-3">
                    <flux:field>
                        <flux:label>
                            Project type
                            @if ($detectedStackNote)
                                <x-ui.badge variant="success">Auto-detected</x-ui.badge>
                            @endif
                        </flux:label>
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
                        <flux:label>Source mode</flux:label>
                        <flux:select wire:model.live="sourceMode">
                            <flux:select.option value="managed_build">Managed build</flux:select.option>
                            <flux:select.option value="upload_ready_build">Upload ready build</flux:select.option>
                        </flux:select>
                        <flux:error name="sourceMode" />
                    </flux:field>

                    <flux:field>
                        <flux:label badge="Optional">Billing cycle</flux:label>
                        <flux:select wire:model="billingCycle">
                            <flux:select.option value="monthly">Monthly</flux:select.option>
                            <flux:select.option value="quarterly">Quarterly</flux:select.option>
                            <flux:select.option value="yearly">Yearly</flux:select.option>
                            <flux:select.option value="one_time">One-time</flux:select.option>
                        </flux:select>
                        <flux:error name="billingCycle" />
                    </flux:field>
                </div>

                <div class="mt-4 max-w-sm">
                    <flux:field>
                        <flux:label badge="Optional">Monthly retainer</flux:label>
                        <flux:input type="number" wire:model="monthlyRetainer" placeholder="0.00" step="0.01" min="0" icon="currency-euro" />
                        <flux:error name="monthlyRetainer" />
                    </flux:field>
                </div>
            </x-ui.card>
        </section>

        <section class="ui-section-block">
            <div class="ui-section-title">
                <span class="ui-step-dot ui-step-violet">3</span>
                Code, files & deployment
            </div>
            <p class="ui-section-help">A source must be verified and built before domain, SSL, and integrations are unlocked.</p>

            <div class="ui-readiness-grid">
                <div class="ui-readiness-card">
                    <p class="stat-label">Source</p>
                    <p class="ui-readiness-value">{{ $sourceType === 'github' ? 'Git repository' : 'Upload package' }}</p>
                    <p class="stat-note">{{ $sourceCheckStatus === 'ready' ? 'Ready' : 'Pending' }}</p>
                </div>
                <div class="ui-readiness-card">
                    <p class="stat-label">Language detected</p>
                    <p class="ui-readiness-value {{ $detectedLanguage ? 'text-emerald-300' : 'text-zinc-500' }}">{{ $detectedLanguage ?: 'None yet' }}</p>
                    <p class="stat-note">From source inspection</p>
                </div>
                <div class="ui-readiness-card">
                    <p class="stat-label">Build artifact</p>
                    <p class="ui-readiness-value {{ $sourceCheckStatus === 'ready' ? 'text-emerald-300' : 'text-amber-300' }}">{{ $sourceCheckStatus === 'ready' ? 'Expected' : 'Missing' }}</p>
                    <p class="stat-note">Confirmed after build/import</p>
                </div>
                <div class="ui-readiness-card">
                    <p class="stat-label">Last deploy</p>
                    <p class="ui-readiness-value text-zinc-500">Not deployed</p>
                    <p class="stat-note">First deploy unlocks production</p>
                </div>
            </div>

            @if ($detectedStackNote)
                <div class="ui-inline-alert {{ $sourceCheckStatus === 'ready' ? 'ui-inline-alert-ok' : '' }}">
                    {{ $detectedStackNote }}
                </div>
            @endif

            @if ($sourceType === 'github')
                <x-ui.card>
                    <x-ui.card-header>
                        <x-ui.card-title><flux:icon name="code-bracket" class="size-4" /> Primary Git source</x-ui.card-title>
                        <span class="tag">Recommended</span>
                    </x-ui.card-header>

                    <flux:field>
                        <flux:label>GitHub repository URL</flux:label>
                        <div class="flex gap-2">
                            <flux:input wire:model.live="repoUrl" placeholder="https://github.com/user/repo.git" class="font-mono" />
                            <flux:button type="button" variant="subtle" wire:click="detectStack">Detect stack</flux:button>
                        </div>
                        <flux:error name="repoUrl" />
                    </flux:field>

                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
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

                    <div class="mt-4">
                        <flux:field>
                            <flux:label badge="Optional">GitHub personal access token</flux:label>
                            <flux:input type="password" wire:model="githubToken" placeholder="ghp_xxxxxxxxxxxx" class="font-mono" viewable />
                            <flux:description>Encrypted at rest. Needs repo scope for private repositories.</flux:description>
                            <flux:error name="githubToken" />
                        </flux:field>
                    </div>

                    <div class="mt-4 flex flex-wrap gap-2">
                        <flux:button type="button" size="sm" variant="subtle" wire:click="checkSourceReadiness">Check repo access</flux:button>
                        <flux:button type="button" size="sm" variant="subtle" wire:click="checkSourceReadiness">Queue clone/build on create</flux:button>
                    </div>
                </x-ui.card>
            @else
                <div class="grid gap-4 lg:grid-cols-2">
                    <x-ui.card>
                        <x-ui.card-header>
                            <x-ui.card-title><flux:icon name="archive-box-arrow-down" class="size-4" /> Upload a ready build</x-ui.card-title>
                        </x-ui.card-header>
                        <p class="mb-4 text-sm text-zinc-500">Use this when the client hands over a ZIP, export folder, or static build artifact.</p>
                        <flux:field>
                            <flux:label>Accepted upload</flux:label>
                            <flux:input value=".zip, dist/, build/, public/" readonly />
                        </flux:field>
                        <flux:button type="button" class="mt-4" size="sm" variant="subtle" wire:click="checkSourceReadiness">Inspect upload readiness</flux:button>
                    </x-ui.card>

                    <x-ui.card>
                        <x-ui.card-header>
                            <x-ui.card-title><flux:icon name="server-stack" class="size-4" /> Release gate</x-ui.card-title>
                            <span class="tag">Draft first</span>
                        </x-ui.card-header>
                        <div class="space-y-2 text-sm text-zinc-400">
                            <p class="flex items-center gap-2"><span class="ui-check-box"></span> Source package selected</p>
                            <p class="flex items-center gap-2"><span class="ui-check-box"></span> Build output will be detected after upload</p>
                            <p class="flex items-center gap-2"><span class="ui-check-box"></span> First deploy unlocks DNS and SSL</p>
                        </div>
                    </x-ui.card>
                </div>
            @endif
        </section>

        <section class="ui-section-block {{ $sourceCheckStatus !== 'ready' ? 'ui-locked-section' : '' }}">
            <div class="ui-section-title">
                <span class="ui-step-dot ui-step-orange">4</span>
                Domain & SSL
                @if ($sourceCheckStatus !== 'ready')
                    <x-ui.badge variant="warning">Locked</x-ui.badge>
                @endif
            </div>
            <p class="ui-section-help">DNS cutover and SSL issuance stay gated until the source checks pass.</p>

            <x-ui.card>
                <fieldset @disabled($sourceCheckStatus !== 'ready') class="grid gap-4 sm:grid-cols-3">
                    <flux:field>
                        <flux:label badge="Optional">Custom domain</flux:label>
                        <flux:input wire:model="domain" placeholder="www.example.com" />
                        <flux:error name="domain" />
                    </flux:field>
                    <flux:field>
                        <flux:label>SSL provider</flux:label>
                        <flux:select wire:model="sslProvider">
                            <flux:select.option value="letsencrypt">Let's Encrypt</flux:select.option>
                            <flux:select.option value="cloudflare">Cloudflare</flux:select.option>
                            <flux:select.option value="custom">Custom certificate</flux:select.option>
                            <flux:select.option value="none">None</flux:select.option>
                        </flux:select>
                        <flux:error name="sslProvider" />
                    </flux:field>
                    <flux:field>
                        <flux:label badge="Optional">DNS provider</flux:label>
                        <flux:select wire:model="dnsProvider">
                            <flux:select.option value="">-</flux:select.option>
                            <flux:select.option value="cloudflare">Cloudflare</flux:select.option>
                            <flux:select.option value="namecheap">Namecheap</flux:select.option>
                            <flux:select.option value="route53">Route 53</flux:select.option>
                            <flux:select.option value="godaddy">GoDaddy</flux:select.option>
                            <flux:select.option value="other">Other</flux:select.option>
                        </flux:select>
                        <flux:error name="dnsProvider" />
                    </flux:field>
                </fieldset>
            </x-ui.card>
        </section>

        <section class="ui-section-block {{ $sourceCheckStatus !== 'ready' ? 'ui-locked-section' : '' }}">
            <div class="ui-section-title">
                <span class="ui-step-dot ui-step-amber">5</span>
                Integrations
                @if ($sourceCheckStatus !== 'ready')
                    <x-ui.badge variant="warning">Locked</x-ui.badge>
                @endif
            </div>
            <p class="ui-section-help">Service keys for analytics, DNS, mail providers, and form automation.</p>

            <x-ui.card>
                <fieldset @disabled($sourceCheckStatus !== 'ready') class="grid gap-4 lg:grid-cols-2">
                    <flux:field>
                        <flux:label badge="Optional">Google Analytics measurement ID</flux:label>
                        <flux:input wire:model="gaPropertyId" placeholder="G-XXXXXXXXXX" class="font-mono" />
                        <flux:error name="gaPropertyId" />
                    </flux:field>
                    <flux:field>
                        <flux:label badge="Optional">Google Search Console property</flux:label>
                        <flux:input wire:model="gscProperty" placeholder="https://www.example.com" />
                        <flux:error name="gscProperty" />
                    </flux:field>
                    <flux:field>
                        <flux:label badge="Optional">Cloudflare API token</flux:label>
                        <flux:input type="password" wire:model="cfApiToken" placeholder="API token" viewable />
                        <flux:error name="cfApiToken" />
                    </flux:field>
                    <flux:field>
                        <flux:label badge="Optional">SMTP host</flux:label>
                        <flux:input wire:model="smtpHost" placeholder="smtp.example.com" class="font-mono" />
                        <flux:error name="smtpHost" />
                    </flux:field>
                </fieldset>
            </x-ui.card>
        </section>

        <div class="flex flex-wrap items-center gap-3 pb-8">
            <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="create">{{ $sourceType === 'upload' ? 'Create project draft' : 'Create project' }}</span>
                <span wire:loading wire:target="create">Creating...</span>
            </flux:button>
            <flux:button type="button" variant="subtle" wire:click="checkSourceReadiness">Save and continue later</flux:button>
            <flux:button href="{{ route('sites.index') }}" variant="ghost">Cancel</flux:button>
        </div>
    </form>
</div>
