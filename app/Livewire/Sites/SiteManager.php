<?php

namespace App\Livewire\Sites;

use App\Jobs\CloneRepoJob;
use App\Models\Site;
use Illuminate\Contracts\View\View;
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
            'projectType' => 'required|string|in:'.implode(',', config('pixelkraft.project_types', ['static_html'])),
            'billingCycle' => 'nullable|string|in:monthly,quarterly,yearly',
            'monthlyRetainer' => 'nullable|numeric|min:0',

            'repoUrl' => 'required|url',
            'branch' => 'required|string|max:100',
            'buildCommand' => 'nullable|string|max:500',
            'githubToken' => 'nullable|string|max:500',

            'domain' => 'nullable|string|max:255',
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
            'sshHost' => 'nullable|string|max:255',
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
            'slug' => Str::slug($this->name),
            'client_first_name' => $this->clientFirstName ?: null,
            'client_last_name' => $this->clientLastName ?: null,
            'client_email' => $this->clientEmail ?: null,
            'client_phone' => $this->clientPhone ?: null,
            'client_company' => $this->clientCompany ?: null,
            'client_address' => $this->clientAddress ?: null,
            'client_notes' => $this->clientNotes ?: null,
            'project_type' => $this->projectType,
            'billing_cycle' => $this->billingCycle ?: null,
            'monthly_retainer' => $this->monthlyRetainer ?: null,
            'repo_url' => $this->repoUrl,
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

        CloneRepoJob::dispatch($site);

        session()->flash('success', "Site '{$site->name}' created. Cloning repository in background...");

        $this->redirect(route('sites.index', ['site' => $site->id]), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.sites.site-manager');
    }
}
