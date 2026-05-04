<x-layouts.app>
    <x-slot:title>Newsletters</x-slot:title>

    <div class="max-w-4xl">
        <div class="mb-6">
            <h2 class="text-lg font-semibold text-zinc-100">Newsletter Campaigns</h2>
            <p class="text-sm text-zinc-500">Compose, schedule, and send newsletters via Resend.</p>
        </div>

        @livewire('email.campaign-editor', ['siteId' => $site->id ?? null])
    </div>
</x-layouts.app>
