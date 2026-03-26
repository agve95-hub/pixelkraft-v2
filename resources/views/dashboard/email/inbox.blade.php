<x-layouts.app>
    <x-slot:title>Form Inbox</x-slot:title>

    <div class="max-w-4xl">
        <div class="mb-6">
            <h2 class="text-lg font-semibold text-zinc-100">Form Inbox</h2>
            <p class="text-sm text-zinc-500">Contact form submissions from all your sites.</p>
        </div>

        @livewire('email.form-inbox')
    </div>
</x-layouts.app>
