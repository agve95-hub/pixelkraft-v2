<?php

namespace App\Livewire\Settings;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\File;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Manages server-level Google integrations that require credentials files.
 *
 * GA4 organic traffic sync uses a Google service account JSON key.
 * The file is stored at GOOGLE_ANALYTICS_CREDENTIALS_PATH (default:
 * storage/app/private/google-credentials.json).
 *
 * This component uploads the file to that path so operators do not need
 * SSH access to configure GA4 after initial setup.
 */
class GoogleIntegrations extends Component
{
    use WithFileUploads;

    public $credentialsFile = null;

    public ?string $uploadMessage = null;

    public bool $uploadSuccess = false;

    public function uploadCredentials(): void
    {
        $this->uploadMessage = null;
        $this->uploadSuccess = false;

        $this->validate([
            'credentialsFile' => 'required|file|mimes:json|max:512',
        ]);

        $destPath = config('platform.google_analytics_credentials_path',
            storage_path('app/private/google-credentials.json')
        );

        // Validate the JSON before saving — a malformed file would cause
        // cryptic Google API errors later.
        $content = $this->credentialsFile->get();
        $decoded = json_decode($content, true);

        if (! is_array($decoded) || ! isset($decoded['type'], $decoded['client_email'], $decoded['private_key'])) {
            $this->uploadMessage = 'The uploaded file does not look like a valid Google service account JSON. Download it from Google Cloud Console → IAM → Service Accounts → Keys.';

            return;
        }

        if ($decoded['type'] !== 'service_account') {
            $this->uploadMessage = 'Expected a service_account credentials file. Found type: '.($decoded['type'] ?? 'unknown').'.';

            return;
        }

        File::ensureDirectoryExists(dirname($destPath), 0700, true);
        File::put($destPath, $content);
        chmod($destPath, 0600); // readable only by the web server user

        $this->credentialsFile = null;
        $this->uploadSuccess = true;
        $this->uploadMessage = "Credentials saved to {$destPath}. GA4 organic traffic sync will use this file on the next daily run.";
    }

    public function removeCredentials(): void
    {
        $destPath = config('platform.google_analytics_credentials_path',
            storage_path('app/private/google-credentials.json')
        );

        if (File::exists($destPath)) {
            File::delete($destPath);
        }

        $this->uploadMessage = 'Credentials file removed. GA4 sync is now disabled.';
        $this->uploadSuccess = false;
    }

    public function render(): View
    {
        $credPath = config('platform.google_analytics_credentials_path',
            storage_path('app/private/google-credentials.json')
        );

        $hasCredentials = File::isReadable($credPath);
        $credEmail = null;

        if ($hasCredentials) {
            try {
                $json = json_decode(File::get($credPath), true);
                $credEmail = $json['client_email'] ?? null;
            } catch (\Throwable) {
                // ignore
            }
        }

        return view('livewire.settings.google-integrations', [
            'credPath' => $credPath,
            'hasCredentials' => $hasCredentials,
            'credEmail' => $credEmail,
        ]);
    }
}
