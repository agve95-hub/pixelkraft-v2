<x-layouts.app>
    <x-slot:title>Google Integrations</x-slot:title>

    <div class="space-y-6">
        <div class="ui-page-head">
            <div>
                <x-ui.breadcrumb>
                    <a href="{{ route('settings') }}">Settings</a>
                    <span>/</span>
                    <span>Google integrations</span>
                </x-ui.breadcrumb>
                <h1 class="ui-page-title mt-3">Google integrations</h1>
                <p class="ui-page-sub">Upload GA4 service account credentials for organic traffic sync.</p>
            </div>
        </div>

        <livewire:settings.google-integrations />
    </div>
</x-layouts.app>
