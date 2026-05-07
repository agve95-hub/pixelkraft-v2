<x-layouts.app>
    <x-slot:title>UI System</x-slot:title>

    <div class="space-y-6">
        <div class="pk-page-head">
            <div>
                <x-ui.breadcrumb>
                    <a href="{{ route('dashboard') }}">Dashboard</a>
                    <span>/</span>
                    <a href="{{ route('settings') }}">Settings</a>
                    <span>/</span>
                    <span>UI system</span>
                </x-ui.breadcrumb>
                <h1 class="pk-page-title mt-3">Pixelkraft UI system</h1>
                <p class="pk-page-sub">Shadcn-inspired Blade and Livewire primitives, spacing samples, and adopted component states.</p>
            </div>
            <x-ui.button-group align="end">
                <x-ui.button href="{{ route('system.diagnostics') }}" variant="outline" size="sm" icon="server-stack">Diagnostics</x-ui.button>
                <x-ui.button href="{{ route('sites.create') }}" size="sm" icon="plus">New project</x-ui.button>
            </x-ui.button-group>
        </div>

        <div class="stats stats-4">
            <div class="stat">
                <p class="stat-label">Page rhythm</p>
                <p class="stat-val-sm">24px sections</p>
                <p class="stat-note">Desktop page padding 28px / 36px</p>
            </div>
            <div class="stat">
                <p class="stat-label">Cards</p>
                <p class="stat-val-sm">16-18px padding</p>
                <p class="stat-note">10px radius, one border tone</p>
            </div>
            <div class="stat">
                <p class="stat-label">Controls</p>
                <p class="stat-val-sm">36px default</p>
                <p class="stat-note">28px compact actions</p>
            </div>
            <div class="stat">
                <p class="stat-label">Tables</p>
                <p class="stat-val-sm">10px / 16px cells</p>
                <p class="stat-note">Mobile scroll only for data grids</p>
            </div>
        </div>

        <div class="grid gap-5 xl:grid-cols-3">
            <x-ui.card>
                <x-ui.card-header>
                    <div>
                        <x-ui.card-title>Buttons and badges</x-ui.card-title>
                        <x-ui.card-description>Core command sizing, variants, and icon rhythm.</x-ui.card-description>
                    </div>
                    <x-ui.badge variant="success" dot>Adopted</x-ui.badge>
                </x-ui.card-header>
                <x-ui.card-content>
                    <x-ui.button-group>
                        <x-ui.button icon="plus">Default</x-ui.button>
                        <x-ui.button variant="outline" icon="arrow-path">Outline</x-ui.button>
                        <x-ui.button variant="secondary">Secondary</x-ui.button>
                        <x-ui.button variant="ghost">Ghost</x-ui.button>
                        <x-ui.button variant="destructive" icon="trash">Delete</x-ui.button>
                    </x-ui.button-group>
                    <div class="flex flex-wrap gap-2">
                        <x-ui.badge variant="success" dot>Live</x-ui.badge>
                        <x-ui.badge variant="warning" dot>Queued</x-ui.badge>
                        <x-ui.badge variant="destructive" dot>Failed</x-ui.badge>
                        <x-ui.badge variant="info">Info</x-ui.badge>
                        <x-ui.badge variant="outline">Outline</x-ui.badge>
                    </div>
                </x-ui.card-content>
            </x-ui.card>

            <x-ui.card>
                <x-ui.card-header>
                    <div>
                        <x-ui.card-title>Form rhythm</x-ui.card-title>
                        <x-ui.card-description>Labels, hints, validation, and control gaps.</x-ui.card-description>
                    </div>
                    <x-ui.badge variant="info">Field</x-ui.badge>
                </x-ui.card-header>
                <x-ui.card-content>
                    <div class="pk-form-grid pk-form-grid-2">
                        <x-ui.field label="Project name" hint="Use the client-facing name.">
                            <x-ui.input value="Spitexzentrum" />
                        </x-ui.field>
                        <x-ui.field label="Stack">
                            <x-ui.select>
                                <option>Static HTML</option>
                                <option>Next.js</option>
                                <option>Laravel</option>
                            </x-ui.select>
                        </x-ui.field>
                    </div>
                    <x-ui.field label="Notes">
                        <x-ui.textarea rows="3">Maintenance window is Sunday 02:00.</x-ui.textarea>
                    </x-ui.field>
                    <div class="flex flex-wrap gap-4">
                        <x-ui.checkbox label="Send client update" checked />
                        <x-ui.switch label="Maintenance mode" />
                    </div>
                </x-ui.card-content>
            </x-ui.card>

            <x-ui.card>
                <x-ui.card-header>
                    <div>
                        <x-ui.card-title>Feedback states</x-ui.card-title>
                        <x-ui.card-description>Alert, progress, skeleton, spinner, empty.</x-ui.card-description>
                    </div>
                    <x-ui.spinner />
                </x-ui.card-header>
                <x-ui.card-content>
                    <x-ui.alert variant="warning" icon="exclamation-triangle" title="Certificate pending">
                        DNS has not propagated yet. Retry after records are visible.
                    </x-ui.alert>
                    <x-ui.progress value="64" />
                    <x-ui.skeleton class="h-8" />
                </x-ui.card-content>
            </x-ui.card>
        </div>

        <x-ui.card padding="flush">
            <x-ui.card-header class="p-[var(--pk-card-pad-y)] px-[var(--pk-card-pad-x)] pb-0">
                <div>
                    <x-ui.card-title>Data table sample</x-ui.card-title>
                    <x-ui.card-description>Dense row spacing with consistent actions and status badges.</x-ui.card-description>
                </div>
                <x-ui.tabs>
                    <x-ui.tab active>All</x-ui.tab>
                    <x-ui.tab>Live</x-ui.tab>
                    <x-ui.tab>Attention</x-ui.tab>
                </x-ui.tabs>
            </x-ui.card-header>
            <x-ui.card-content class="p-[var(--pk-card-pad-y)] px-[var(--pk-card-pad-x)]">
                <x-ui.table>
                    <thead>
                        <tr>
                            <th>Project</th>
                            <th>Client</th>
                            <th>Status</th>
                            <th>Progress</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                <div class="site-name">
                                    <span class="site-dot bg-emerald-400"></span>
                                    <span>Client portal</span>
                                </div>
                            </td>
                            <td>Artho GmbH</td>
                            <td><x-ui.badge variant="success" dot>Live</x-ui.badge></td>
                            <td><x-ui.progress value="92" /></td>
                            <td>
                                <x-ui.button-group align="end">
                                    <x-ui.button variant="ghost" size="xs">Open</x-ui.button>
                                    <x-ui.button variant="outline" size="xs">Deploy</x-ui.button>
                                </x-ui.button-group>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <div class="site-name">
                                    <span class="site-dot bg-amber-400"></span>
                                    <span>Campaign microsite</span>
                                </div>
                            </td>
                            <td>Pixelkraft</td>
                            <td><x-ui.badge variant="warning" dot>Queued</x-ui.badge></td>
                            <td><x-ui.progress value="28" /></td>
                            <td>
                                <x-ui.button-group align="end">
                                    <x-ui.button variant="ghost" size="xs">Open</x-ui.button>
                                    <x-ui.button variant="outline" size="xs">Logs</x-ui.button>
                                </x-ui.button-group>
                            </td>
                        </tr>
                    </tbody>
                </x-ui.table>
            </x-ui.card-content>
        </x-ui.card>

        <div class="grid gap-5 lg:grid-cols-2">
            <x-ui.card>
                <x-ui.card-header>
                    <div>
                        <x-ui.card-title>Accordion and item rows</x-ui.card-title>
                        <x-ui.card-description>Used for settings, diagnostics, and dense grouped content.</x-ui.card-description>
                    </div>
                </x-ui.card-header>
                <x-ui.accordion>
                    <x-ui.accordion-item title="Deployment checks">
                        <x-ui.item icon="check-circle" title="Nginx config" meta="Validated 2 minutes ago">Configuration test passed.</x-ui.item>
                        <x-ui.item icon="clock" title="Queue workers" meta="Horizon">Workers are processing deploy and monitoring queues.</x-ui.item>
                    </x-ui.accordion-item>
                    <x-ui.accordion-item title="Deferred components">
                        Slider, carousel, and resizable panes are only introduced where the product genuinely needs them.
                    </x-ui.accordion-item>
                </x-ui.accordion>
            </x-ui.card>

            <x-ui.card>
                <x-ui.card-header>
                    <div>
                        <x-ui.card-title>Command and keyboard hints</x-ui.card-title>
                        <x-ui.card-description>The app search overlay follows this command palette rhythm.</x-ui.card-description>
                    </div>
                    <div class="flex gap-1"><x-ui.kbd>⌘</x-ui.kbd><x-ui.kbd>K</x-ui.kbd></div>
                </x-ui.card-header>
                <x-ui.empty icon="magnifying-glass" title="Search-ready empty state" description="Use grouped results, selected rows, and keyboard hints for command surfaces.">
                    <x-ui.button variant="outline" size="sm">Open search</x-ui.button>
                </x-ui.empty>
            </x-ui.card>
        </div>

        <div class="grid gap-5 xl:grid-cols-3">
            <x-ui.card>
                <x-ui.card-header>
                    <div>
                        <x-ui.card-title>Menu surfaces</x-ui.card-title>
                        <x-ui.card-description>Dropdown and context-menu visual rhythm.</x-ui.card-description>
                    </div>
                </x-ui.card-header>
                <x-ui.dropdown-menu>
                    <x-ui.dropdown-item icon="eye">Open project</x-ui.dropdown-item>
                    <x-ui.dropdown-item icon="arrow-path">Redeploy</x-ui.dropdown-item>
                    <x-ui.dropdown-item icon="trash" destructive>Delete</x-ui.dropdown-item>
                </x-ui.dropdown-menu>
            </x-ui.card>

            <x-ui.card>
                <x-ui.card-header>
                    <div>
                        <x-ui.card-title>Overlay surfaces</x-ui.card-title>
                        <x-ui.card-description>Dialog, popover, sheet, alert dialog styling.</x-ui.card-description>
                    </div>
                </x-ui.card-header>
                <div class="grid gap-3">
                    <x-ui.dialog title="Confirm rollback" description="This sample mirrors alert dialog spacing.">
                        <div class="pk-action-row">
                            <x-ui.button variant="outline" size="sm">Cancel</x-ui.button>
                            <x-ui.button variant="destructive" size="sm">Rollback</x-ui.button>
                        </div>
                    </x-ui.dialog>
                    <x-ui.popover>
                        Popover content uses the same border, radius, shadow, and text density.
                    </x-ui.popover>
                </div>
            </x-ui.card>

            <x-ui.card>
                <x-ui.card-header>
                    <div>
                        <x-ui.card-title>Command, toast, tooltip</x-ui.card-title>
                        <x-ui.card-description>Search, notification, and hover-help primitives.</x-ui.card-description>
                    </div>
                </x-ui.card-header>
                <div class="grid gap-3">
                    <x-ui.command placeholder="Search projects...">
                        <x-ui.command-item icon="globe-alt" active>Client portal</x-ui.command-item>
                        <x-ui.command-item icon="document-text">Invoices</x-ui.command-item>
                    </x-ui.command>
                    <x-ui.toast variant="success" title="Settings saved">Your changes are ready for the next deploy.</x-ui.toast>
                    <x-ui.tooltip text="Tooltips explain icon-only or uncommon actions.">
                        <x-ui.button variant="outline" size="sm" icon="information-circle">Hover me</x-ui.button>
                    </x-ui.tooltip>
                </div>
            </x-ui.card>
        </div>
    </div>
</x-layouts.app>
