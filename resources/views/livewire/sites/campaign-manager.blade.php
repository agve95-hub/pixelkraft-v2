<div class="space-y-6">

    {{-- Header --}}
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">Campaigns &amp; Announcements</flux:heading>
            <flux:subheading>{{ $site->name }} — popup campaigns and top-bar banners served by <code class="text-xs text-zinc-400">pixelkraft.js</code>.</flux:subheading>
        </div>
        <div class="flex gap-2">
            <flux:button wire:click="$set('tab', 'campaigns')" size="sm"
                variant="{{ $tab === 'campaigns' ? 'primary' : 'ghost' }}">
                Campaigns <span class="ml-1 text-xs">({{ $campaigns->count() }})</span>
            </flux:button>
            <flux:button wire:click="$set('tab', 'announcements')" size="sm"
                variant="{{ $tab === 'announcements' ? 'primary' : 'ghost' }}">
                Announcements <span class="ml-1 text-xs">({{ $announcements->count() }})</span>
            </flux:button>
        </div>
    </div>

    @if (session('success'))
        <flux:callout variant="success" icon="check-circle">{{ session('success') }}</flux:callout>
    @endif

    {{-- ── CAMPAIGNS TAB ──────────────────────────────────────────────────── --}}
    @if ($tab === 'campaigns')

        {{-- Campaign form --}}
        <flux:card>
            <flux:heading size="lg" class="mb-4">
                {{ $editingCampaignId ? 'Edit campaign' : 'New popup campaign' }}
            </flux:heading>

            <form wire:submit="saveCampaign" class="grid gap-4 sm:grid-cols-2">

                <flux:field class="sm:col-span-2">
                    <flux:label>Internal name</flux:label>
                    <flux:input wire:model="cf_name" placeholder="e.g. Black Friday 2026" />
                    <flux:error name="cf_name" />
                </flux:field>

                <flux:field class="sm:col-span-2">
                    <flux:label>Headline</flux:label>
                    <flux:input wire:model="cf_headline" placeholder="Bold headline shown in the popup" />
                    <flux:error name="cf_headline" />
                </flux:field>

                <flux:field class="sm:col-span-2">
                    <flux:label>Body text</flux:label>
                    <flux:textarea wire:model="cf_body" rows="3" placeholder="Optional supporting copy" />
                    <flux:error name="cf_body" />
                </flux:field>

                <flux:field>
                    <flux:label>CTA button text</flux:label>
                    <flux:input wire:model="cf_cta_text" placeholder="e.g. Shop now" />
                    <flux:error name="cf_cta_text" />
                </flux:field>

                <flux:field>
                    <flux:label>CTA URL</flux:label>
                    <flux:input wire:model="cf_cta_url" type="url" placeholder="https://…" />
                    <flux:error name="cf_cta_url" />
                </flux:field>

                <flux:field>
                    <flux:label>Trigger</flux:label>
                    <flux:select wire:model="cf_trigger">
                        <option value="on_load">On page load</option>
                        <option value="on_delay">After delay</option>
                        <option value="on_scroll">On scroll</option>
                        <option value="on_exit">On exit intent</option>
                    </flux:select>
                    <flux:error name="cf_trigger" />
                </flux:field>

                <flux:field>
                    <flux:label>Trigger delay (ms)</flux:label>
                    <flux:input wire:model="cf_trigger_delay_ms" type="number" min="0" placeholder="0" />
                    <flux:description>Only used when trigger is "After delay".</flux:description>
                    <flux:error name="cf_trigger_delay_ms" />
                </flux:field>

                <flux:field>
                    <flux:label>Starts at</flux:label>
                    <flux:input wire:model="cf_starts_at" type="datetime-local" />
                    <flux:error name="cf_starts_at" />
                </flux:field>

                <flux:field>
                    <flux:label>Ends at</flux:label>
                    <flux:input wire:model="cf_ends_at" type="datetime-local" />
                    <flux:error name="cf_ends_at" />
                </flux:field>

                <flux:field>
                    <flux:label>Priority</flux:label>
                    <flux:input wire:model="cf_priority" type="number" min="0" max="255" placeholder="0" />
                    <flux:description>Higher number = shown first when multiple match.</flux:description>
                    <flux:error name="cf_priority" />
                </flux:field>

                <flux:field>
                    <flux:label>Locale</flux:label>
                    <flux:input wire:model="cf_locale" placeholder="en" maxlength="10" />
                    <flux:error name="cf_locale" />
                </flux:field>

                <flux:field>
                    <flux:checkbox wire:model="cf_is_dismissible" label="Dismissible" />
                </flux:field>

                <flux:field>
                    <flux:checkbox wire:model="cf_is_enabled" label="Enabled" />
                </flux:field>

                <div class="flex gap-2 sm:col-span-2">
                    <flux:button type="submit" variant="primary">
                        {{ $editingCampaignId ? 'Update campaign' : 'Create campaign' }}
                    </flux:button>
                    @if ($editingCampaignId)
                        <flux:button type="button" wire:click="$set('editingCampaignId', null)" variant="ghost">
                            Cancel
                        </flux:button>
                    @endif
                </div>
            </form>
        </flux:card>

        {{-- Campaign list --}}
        <flux:card>
            <flux:heading size="lg" class="mb-4">Campaigns</flux:heading>
            <div class="space-y-2">
                @forelse ($campaigns as $campaign)
                    <div class="flex flex-wrap items-start justify-between gap-3 rounded-lg border border-zinc-800/80 bg-zinc-950/40 px-3 py-3">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <p class="font-medium text-zinc-100">{{ $campaign->headline }}</p>
                                @if ($campaign->isActive())
                                    <span class="rounded-full bg-emerald-500/15 px-2 py-0.5 text-xs font-medium text-emerald-400">Active</span>
                                @elseif ($campaign->is_enabled)
                                    <span class="rounded-full bg-amber-500/15 px-2 py-0.5 text-xs font-medium text-amber-400">Scheduled</span>
                                @else
                                    <span class="rounded-full bg-zinc-700/40 px-2 py-0.5 text-xs font-medium text-zinc-500">Disabled</span>
                                @endif
                            </div>
                            <p class="mt-0.5 text-xs text-zinc-500">
                                {{ $campaign->name }}
                                &middot; {{ $campaign->trigger }}
                                &middot; {{ $campaign->starts_at?->format('M j, Y') }} – {{ $campaign->ends_at?->format('M j, Y') }}
                                &middot; priority {{ $campaign->priority }}
                            </p>
                        </div>
                        <div class="flex shrink-0 gap-2">
                            <flux:button type="button" wire:click="toggleCampaign('{{ $campaign->id }}')" size="xs" variant="subtle">
                                {{ $campaign->is_enabled ? 'Disable' : 'Enable' }}
                            </flux:button>
                            <flux:button type="button" wire:click="editCampaign('{{ $campaign->id }}')" size="xs" variant="subtle">
                                Edit
                            </flux:button>
                            <flux:button type="button" wire:click="deleteCampaign('{{ $campaign->id }}')" wire:confirm="Delete this campaign?" size="xs" variant="ghost" class="text-red-400">
                                Delete
                            </flux:button>
                        </div>
                    </div>
                @empty
                    <p class="py-6 text-center text-sm text-zinc-500">No popup campaigns yet.</p>
                @endforelse
            </div>
        </flux:card>

    @endif

    {{-- ── ANNOUNCEMENTS TAB ──────────────────────────────────────────────── --}}
    @if ($tab === 'announcements')

        {{-- Announcement form --}}
        <flux:card>
            <flux:heading size="lg" class="mb-4">
                {{ $editingAnnouncementId ? 'Edit announcement' : 'New top-bar announcement' }}
            </flux:heading>

            <form wire:submit="saveAnnouncement" class="grid gap-4 sm:grid-cols-2">

                <flux:field class="sm:col-span-2">
                    <flux:label>Message</flux:label>
                    <flux:textarea wire:model="af_message" rows="2" placeholder="e.g. Free shipping on orders over €50!" />
                    <flux:error name="af_message" />
                </flux:field>

                <flux:field>
                    <flux:label>Style</flux:label>
                    <flux:select wire:model="af_style">
                        <option value="info">Info</option>
                        <option value="warning">Warning</option>
                        <option value="error">Error</option>
                        <option value="success">Success</option>
                        <option value="promo">Promo</option>
                    </flux:select>
                    <flux:error name="af_style" />
                </flux:field>

                <flux:field>
                    <flux:label>Placement</flux:label>
                    <flux:select wire:model="af_placement">
                        <option value="top_bar">Top bar</option>
                        <option value="inline">Inline</option>
                        <option value="floating">Floating</option>
                    </flux:select>
                    <flux:error name="af_placement" />
                </flux:field>

                <flux:field>
                    <flux:label>CTA button text</flux:label>
                    <flux:input wire:model="af_cta_text" placeholder="e.g. Learn more" />
                    <flux:error name="af_cta_text" />
                </flux:field>

                <flux:field>
                    <flux:label>CTA URL</flux:label>
                    <flux:input wire:model="af_cta_url" type="url" placeholder="https://…" />
                    <flux:error name="af_cta_url" />
                </flux:field>

                <flux:field>
                    <flux:label>Starts at</flux:label>
                    <flux:input wire:model="af_starts_at" type="datetime-local" />
                    <flux:error name="af_starts_at" />
                </flux:field>

                <flux:field>
                    <flux:label>Ends at</flux:label>
                    <flux:input wire:model="af_ends_at" type="datetime-local" />
                    <flux:error name="af_ends_at" />
                </flux:field>

                <flux:field>
                    <flux:label>Priority</flux:label>
                    <flux:input wire:model="af_priority" type="number" min="0" max="255" placeholder="0" />
                    <flux:error name="af_priority" />
                </flux:field>

                <flux:field>
                    <flux:label>Locale</flux:label>
                    <flux:input wire:model="af_locale" placeholder="en" maxlength="10" />
                    <flux:error name="af_locale" />
                </flux:field>

                <flux:field>
                    <flux:checkbox wire:model="af_is_dismissible" label="Dismissible" />
                </flux:field>

                <flux:field>
                    <flux:checkbox wire:model="af_is_enabled" label="Enabled" />
                </flux:field>

                <div class="flex gap-2 sm:col-span-2">
                    <flux:button type="submit" variant="primary">
                        {{ $editingAnnouncementId ? 'Update announcement' : 'Create announcement' }}
                    </flux:button>
                    @if ($editingAnnouncementId)
                        <flux:button type="button" wire:click="$set('editingAnnouncementId', null)" variant="ghost">
                            Cancel
                        </flux:button>
                    @endif
                </div>
            </form>
        </flux:card>

        {{-- Announcement list --}}
        <flux:card>
            <flux:heading size="lg" class="mb-4">Announcements</flux:heading>
            <div class="space-y-2">
                @forelse ($announcements as $announcement)
                    <div class="flex flex-wrap items-start justify-between gap-3 rounded-lg border border-zinc-800/80 bg-zinc-950/40 px-3 py-3">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <p class="font-medium text-zinc-100">{{ \Illuminate\Support\Str::limit($announcement->message, 80) }}</p>
                                @if ($announcement->isActive())
                                    <span class="rounded-full bg-emerald-500/15 px-2 py-0.5 text-xs font-medium text-emerald-400">Active</span>
                                @elseif ($announcement->is_enabled)
                                    <span class="rounded-full bg-amber-500/15 px-2 py-0.5 text-xs font-medium text-amber-400">Scheduled</span>
                                @else
                                    <span class="rounded-full bg-zinc-700/40 px-2 py-0.5 text-xs font-medium text-zinc-500">Disabled</span>
                                @endif
                            </div>
                            <p class="mt-0.5 text-xs text-zinc-500">
                                {{ $announcement->style }} &middot; {{ $announcement->placement }}
                                &middot; {{ $announcement->starts_at?->format('M j, Y') }} – {{ $announcement->ends_at?->format('M j, Y') }}
                            </p>
                        </div>
                        <div class="flex shrink-0 gap-2">
                            <flux:button type="button" wire:click="toggleAnnouncement('{{ $announcement->id }}')" size="xs" variant="subtle">
                                {{ $announcement->is_enabled ? 'Disable' : 'Enable' }}
                            </flux:button>
                            <flux:button type="button" wire:click="editAnnouncement('{{ $announcement->id }}')" size="xs" variant="subtle">
                                Edit
                            </flux:button>
                            <flux:button type="button" wire:click="deleteAnnouncement('{{ $announcement->id }}')" wire:confirm="Delete this announcement?" size="xs" variant="ghost" class="text-red-400">
                                Delete
                            </flux:button>
                        </div>
                    </div>
                @empty
                    <p class="py-6 text-center text-sm text-zinc-500">No announcements yet.</p>
                @endforelse
            </div>
        </flux:card>

    @endif

</div>
