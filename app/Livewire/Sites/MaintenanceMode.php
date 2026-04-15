<?php

namespace App\Livewire\Sites;

use App\Support\SiteAccess;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class MaintenanceMode extends Component
{
    public string $siteId;

    public bool $enabled = false;

    public bool $autoOnDown = true;

    public string $heading = "We'll be back soon";

    public string $message = '';

    public bool $showCountdown = false;

    public string $countdownTo = '';

    public string $bgColor = '#18181b';

    public string $textColor = '#ffffff';

    public string $accentColor = '#34d399';

    public bool $showLogo = true;

    public string $customCss = '';

    public string $allowedIps = '';

    public function mount(): void
    {
        $site = SiteAccess::findOrFail($this->siteId);
        $defaults = $this->defaultSettings();
        $stored = is_array($site->maintenance_settings) ? $site->maintenance_settings : [];
        $merged = array_merge($defaults, $stored);

        $this->enabled = (bool) ($merged['enabled'] ?? false);
        $this->autoOnDown = (bool) ($merged['autoOnDown'] ?? true);
        $this->heading = (string) ($merged['heading'] ?? $defaults['heading']);
        $this->message = (string) ($merged['message'] ?? $defaults['message']);
        $this->showCountdown = (bool) ($merged['showCountdown'] ?? false);
        $this->countdownTo = (string) ($merged['countdownTo'] ?? '');
        $this->bgColor = (string) ($merged['bgColor'] ?? $defaults['bgColor']);
        $this->textColor = (string) ($merged['textColor'] ?? $defaults['textColor']);
        $this->accentColor = (string) ($merged['accentColor'] ?? $defaults['accentColor']);
        $this->showLogo = (bool) ($merged['showLogo'] ?? true);
        $this->customCss = (string) ($merged['customCSS'] ?? $merged['customCss'] ?? '');
        $this->allowedIps = (string) ($merged['allowedIPs'] ?? $merged['allowedIps'] ?? '');
    }

    public function save(): void
    {
        // Hex color regex: #RGB, #RRGGBB, or #RRGGBBAA.  Restricting to hex prevents
        // CSS injection via style-attribute context (e.g. "red; display:none").
        $hexColor = '/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/';

        $this->validate([
            'heading' => 'required|string|max:255',
            'message' => 'nullable|string|max:5000',
            'countdownTo' => 'nullable|string|max:64',
            'bgColor' => ['required', 'string', 'max:9', 'regex:'.$hexColor],
            'textColor' => ['required', 'string', 'max:9', 'regex:'.$hexColor],
            'accentColor' => ['required', 'string', 'max:9', 'regex:'.$hexColor],
            'customCss' => 'nullable|string|max:10000',
            'allowedIps' => 'nullable|string|max:2000',
        ]);

        $site = SiteAccess::findOrFail($this->siteId);
        $site->maintenance_settings = [
            'enabled' => $this->enabled,
            'autoOnDown' => $this->autoOnDown,
            'heading' => $this->heading,
            'message' => $this->message,
            'showCountdown' => $this->showCountdown,
            'countdownTo' => $this->countdownTo,
            'bgColor' => $this->bgColor,
            'textColor' => $this->textColor,
            'accentColor' => $this->accentColor,
            'showLogo' => $this->showLogo,
            'customCSS' => $this->customCss,
            'allowedIPs' => $this->allowedIps,
        ];
        $site->save();

        session()->flash('success', 'Maintenance settings saved.');
    }

    public function render(): View
    {
        return view('livewire.sites.maintenance-mode');
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultSettings(): array
    {
        return [
            'enabled' => false,
            'autoOnDown' => true,
            'heading' => "We'll be back soon",
            'message' => "We're performing scheduled maintenance to improve your experience. Please check back shortly.",
            'showCountdown' => false,
            'countdownTo' => '',
            'bgColor' => '#18181b',
            'textColor' => '#ffffff',
            'accentColor' => '#34d399',
            'showLogo' => true,
            'customCSS' => '',
            'allowedIPs' => '',
        ];
    }
}
