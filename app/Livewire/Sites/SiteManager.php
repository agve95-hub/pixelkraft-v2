<?php

namespace App\Livewire\Sites;

use App\Jobs\CloneRepoJob;
use App\Models\Site;
use App\Rules\GitRemoteUrl;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Livewire\Component;

class SiteManager extends Component
{
    // Client information
    public string $clientFirstName = '';

    public string $clientLastName = '';

    public string $clientEmail = '';

    public string $clientPhone = '';

    public string $clientCompany = '';

    public string $clientAddress = '';

    public string $clientNotes = '';

    // Project setup
    public string $name = '';

    public string $projectType = 'static_html';

    public string $sourceType = 'github';

    public string $sourceMode = 'managed_build';

    public string $billingCycle = 'monthly';

    public ?string $monthlyRetainer = null;

    // Repository & deployment
    public string $repoUrl = '';

    public string $branch = 'main';

    public string $buildCommand = '';

    public string $githubToken = '';

    // Domain & SSL
    public string $domain = '';

    public string $sslProvider = 'letsencrypt';

    public string $dnsProvider = '';

    // Integrations
    public string $gaPropertyId = '';

    public string $gscProperty = '';

    public string $gtmId = '';

    public string $googleAdsId = '';

    public string $cfApiToken = '';

    public string $cfZoneId = '';

    // SMTP
    public string $smtpHost = '';

    public ?int $smtpPort = 587;

    public string $smtpUsername = '';

    public string $smtpPassword = '';

    // Hosting / server access
    public string $hostingProvider = '';

    public string $sshHost = '';

    public string $ftpSshUser = '';

    public string $ftpSshPassword = '';

    public ?string $detectedStackNote = null;

    public ?string $detectedLanguage = null;

    public string $sourceCheckStatus = 'pending';

    protected function rules(): array
    {
        return [
            'clientFirstName' => 'nullable|string|max:255',
            'clientLastName' => 'nullable|string|max:255',
            'clientEmail' => 'nullable|email|max:255',
            'clientPhone' => 'nullable|string|max:50',
            'clientCompany' => 'nullable|string|max:255',
            'clientAddress' => 'nullable|string|max:500',
            'clientNotes' => 'nullable|string|max:2000',

            'name' => 'required|string|max:255',
            'projectType' => 'required|string|in:'.implode(',', config('platform.project_types', ['static_html'])),
            'sourceType' => ['required', Rule::in(['github', 'upload'])],
            'sourceMode' => ['required', Rule::in(['managed_build', 'upload_ready_build'])],
            'billingCycle' => 'nullable|string|in:monthly,quarterly,yearly,one_time',
            'monthlyRetainer' => 'nullable|numeric|min:0',

            // Restrict to known git hosting providers to prevent GitHub token exfiltration
            // via a crafted repo URL pointing to an attacker-controlled host.
            'repoUrl' => [$this->sourceType === 'github' ? 'required' : 'nullable', 'string', 'max:500', new GitRemoteUrl],
            // Branch: same strict regex as SiteSettings to prevent injection.
            'branch' => ['required', 'string', 'max:100', 'regex:/^[a-zA-Z0-9][a-zA-Z0-9\-._\/]*$/'],
            // Build command: disallow shell injection metacharacters (; | ` $ < >) and newlines.
            'buildCommand' => ['nullable', 'string', 'max:500', 'not_regex:/[;|`\$<>\r\n]/'],
            'githubToken' => ['nullable', 'string', 'max:500', 'not_regex:/[\r\n]/'],

            // Hostname: letters, digits, dots, hyphens only; no newlines or shell chars.
            'domain' => ['nullable', 'string', 'max:253', 'regex:/^[a-zA-Z0-9*][a-zA-Z0-9.\-*]*$/'],
            'sslProvider' => 'nullable|string|in:letsencrypt,cloudflare,custom,none',
            'dnsProvider' => 'nullable|string|max:255',

            'gaPropertyId' => ['nullable', 'string', 'max:50', 'regex:/^G-[A-Z0-9]{4,20}$/i'],
            'gscProperty' => 'nullable|string|max:255',
            'gtmId' => ['nullable', 'string', 'max:20', 'regex:/^GTM-[A-Z0-9]{4,12}$/i'],
            'googleAdsId' => 'nullable|string|max:50',
            'cfApiToken' => 'nullable|string|max:500',
            'cfZoneId' => 'nullable|string|max:100',

            'smtpHost' => 'nullable|string|max:255',
            'smtpPort' => 'nullable|integer|min:1|max:65535',
            'smtpUsername' => 'nullable|string|max:255',
            'smtpPassword' => 'nullable|string|max:500',

            'hostingProvider' => 'nullable|string|max:255',
            // sshHost: reject newlines to prevent header/command injection.
            'sshHost' => ['nullable', 'string', 'max:255', 'not_regex:/[\r\n]/'],
            'ftpSshUser' => 'nullable|string|max:255',
            'ftpSshPassword' => 'nullable|string|max:500',
        ];
    }

    public function create(): void
    {
        $this->validate();

        $site = Site::create([
            'user_id' => auth()->id(),
            'name' => $this->name,
            'slug' => $this->uniqueSlug($this->name),
            'client_first_name' => $this->clientFirstName ?: null,
            'client_last_name' => $this->clientLastName ?: null,
            'client_email' => $this->clientEmail ?: null,
            'client_phone' => $this->clientPhone ?: null,
            'client_company' => $this->clientCompany ?: null,
            'client_address' => $this->clientAddress ?: null,
            'client_notes' => $this->clientNotes ?: null,
            'project_type' => $this->projectType,
            'source_type' => $this->sourceType,
            'billing_cycle' => $this->billingCycle ?: null,
            'monthly_retainer' => $this->monthlyRetainer ?: null,
            'repo_url' => $this->sourceType === 'github' ? $this->repoUrl : null,
            'branch' => $this->branch,
            'build_command' => $this->buildCommand ?: null,
            'github_token' => $this->githubToken ?: null,
            'domain' => $this->domain ?: null,
            'ssl_provider' => $this->sslProvider ?: null,
            'dns_provider' => $this->dnsProvider ?: null,
            'ga_property_id' => $this->gaPropertyId ?: null,
            'gsc_property' => $this->gscProperty ?: null,
            'gtm_id' => $this->gtmId ?: null,
            'google_ads_id' => $this->googleAdsId ?: null,
            'cf_api_token' => $this->cfApiToken ?: null,
            'cf_zone_id' => $this->cfZoneId ?: null,
            'smtp_host' => $this->smtpHost ?: null,
            'smtp_port' => $this->smtpPort,
            'smtp_username' => $this->smtpUsername ?: null,
            'smtp_password' => $this->smtpPassword ?: null,
            'hosting_provider' => $this->hostingProvider ?: null,
            'ssh_host' => $this->sshHost ?: null,
            'ftp_ssh_user' => $this->ftpSshUser ?: null,
            'ftp_ssh_password' => $this->ftpSshPassword ?: null,
        ]);

        if ($this->sourceType === 'github' && $this->repoUrl !== '') {
            CloneRepoJob::dispatch($site);
        }

        $message = $this->sourceType === 'github'
            ? "Site '{$site->name}' created. Cloning repository in background..."
            : "Project draft '{$site->name}' created. Upload a build package from the project files screen.";

        session()->flash('success', $message);

        $this->redirect(route('sites.show', $site), navigate: true);
    }

    public function updatedSourceMode(string $mode): void
    {
        $this->sourceType = $mode === 'upload_ready_build' ? 'upload' : 'github';
        $this->sourceCheckStatus = 'pending';
    }

    public function detectStack(): void
    {
        $repoName = Str::lower(Str::replaceEnd('.git', '', basename(parse_url($this->repoUrl, PHP_URL_PATH) ?: $this->repoUrl)));

        [$type, $language, $command, $note] = match (true) {
            Str::contains($repoName, ['next', 'nextjs']) => ['nextjs', 'JavaScript / TypeScript', 'npm run build', 'Detected: Next.js project. Build output: .next/'],
            Str::contains($repoName, ['react', 'cra']) => ['react', 'JavaScript', 'npm run build', 'Detected: React project. Build output: build/ or dist/.'],
            Str::contains($repoName, 'vue') => ['vue', 'JavaScript', 'npm run build', 'Detected: Vue project. Build output: dist/.'],
            Str::contains($repoName, 'astro') => ['astro', 'JavaScript / TypeScript', 'npm run build', 'Detected: Astro project. Build output: dist/.'],
            Str::contains($repoName, ['laravel', 'php']) => ['custom', 'PHP', 'composer install', 'Detected: PHP/Laravel project. Confirm public/ as the web root before deploy.'],
            default => ['static_html', 'HTML / CSS / JS', '', 'Detected: Static site. No build step required.'],
        };

        $this->projectType = $type;
        $this->detectedLanguage = $language;
        $this->detectedStackNote = $note;
        if ($this->buildCommand === '' && $command !== '') {
            $this->buildCommand = $command;
        }
        $this->sourceCheckStatus = 'detected';
    }

    public function checkSourceReadiness(): void
    {
        $this->validateOnly('sourceType');

        if ($this->sourceType === 'github') {
            $this->validateOnly('repoUrl');
            $this->validateOnly('branch');
            $this->sourceCheckStatus = 'ready';
            $this->detectedStackNote ??= 'Repository URL and branch passed local validation. Create the project to queue clone/build.';

            return;
        }

        $this->sourceCheckStatus = 'ready';
        $this->detectedStackNote = 'Upload source selected. Create the project draft, then upload a ZIP or ready build package.';
    }

    public function render(): View
    {
        return view('livewire.sites.site-manager');
    }

    private function uniqueSlug(string $name): string
    {
        $baseSlug = Str::slug($name) ?: 'site';
        $slug = $baseSlug;
        $suffix = 2;

        while (Site::query()->where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$suffix++;
        }

        return $slug;
    }
}
