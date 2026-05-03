import { useEffect, useState } from 'react';
import { Link, usePage } from '@inertiajs/react';
import {
    Home, Globe, Mail, ClipboardList, Megaphone, Banknote,
    FileText, Clock, BarChart, ShieldCheck, Image, Settings,
    ChevronDown, ChevronRight, Plus, Search, LogOut, Server,
    X, Menu, Users, Newspaper,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Separator } from '@/components/ui/separator';
import { TooltipProvider } from '@/components/ui/tooltip';
import {
    DropdownMenu, DropdownMenuContent, DropdownMenuItem,
    DropdownMenuSeparator, DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';
import { type NavSite, type PageProps } from '@/types';

interface AppLayoutProps {
    children: React.ReactNode;
    title?: string;
}

interface SiteMenuItem {
    icon: React.ElementType;
    label: string;
    href: string;
    badge?: number;
    badgeVariant?: 'warning';
    native?: boolean;
    comingSoon?: boolean;
}

function siteMenuItems(site: NavSite): SiteMenuItem[] {
    const baseHref = `/dashboard/sites/${site.id}`;

    return [
        { icon: FileText, label: 'Pages & SEO', href: `${baseHref}/pages`, native: true },
        { icon: ClipboardList, label: 'Blog', href: `${baseHref}/blog` },
        { icon: Globe, label: 'Products', href: `${baseHref}/products` },
        { icon: Megaphone, label: 'Campaigns', href: `${baseHref}/campaigns` },
        { icon: Users, label: 'Subscribers', href: `${baseHref}/subscribers` },
        { icon: Newspaper, label: 'Newsletters', href: `${baseHref}/newsletters` },
        { icon: Banknote, label: 'Expenses', href: `${baseHref}/expenses` },
        { icon: FileText, label: 'Invoices', href: `${baseHref}/invoices`, badge: site.unpaid_invoices_count },
        { icon: Clock, label: 'Reminders', href: `${baseHref}/reminders`, badge: site.overdue_reminders_count, badgeVariant: 'warning' },
        { icon: BarChart, label: 'Analytics', href: `${baseHref}/analytics` },
        { icon: ShieldCheck, label: 'Maintenance', href: `${baseHref}/maintenance` },
        { icon: Image, label: 'Media', href: `${baseHref}/files` },
        { icon: Settings, label: 'Site settings', href: `${baseHref}/settings` },
    ];
}

function deployStatusColor(status: string) {
    switch (status) {
        case 'live': return 'bg-emerald-400';
        case 'deploying':
        case 'queued': return 'bg-amber-400';
        case 'failed': return 'bg-red-500';
        default: return 'bg-zinc-500';
    }
}

export default function AppLayout({ children, title }: AppLayoutProps) {
    const { auth, navSites, flash, expandedSiteId: initialExpandedSiteId } = usePage<PageProps>().props;
    const user = auth.user;
    const url = usePage().url;

    const [expandedSiteId, setExpandedSiteId] = useState<string | null>(initialExpandedSiteId ?? null);
    const [sidebarOpen, setSidebarOpen] = useState(false);
    const [searchOpen, setSearchOpen] = useState(false);

    useEffect(() => {
        if (title) document.title = `${title} - pixelkraft`;
    }, [title]);

    useEffect(() => {
        setExpandedSiteId(initialExpandedSiteId ?? null);
    }, [initialExpandedSiteId]);

    useEffect(() => {
        const handler = (event: KeyboardEvent) => {
            if ((event.metaKey || event.ctrlKey) && event.key === 'k') {
                event.preventDefault();
                setSearchOpen((value) => !value);
            }
            if (event.key === 'Escape') setSearchOpen(false);
        };

        window.addEventListener('keydown', handler);

        return () => window.removeEventListener('keydown', handler);
    }, []);

    const isActive = (href: string) => url === href || url.startsWith(href + '/') || url.startsWith(href + '?');
    const closeSidebar = () => setSidebarOpen(false);

    const avatarUrl = user
        ? `https://ui-avatars.com/api/?name=${encodeURIComponent(user.name)}&background=27272a&color=f4f4f5&size=64&font-size=0.4&bold=true&rounded=false`
        : '';

    const SidebarContent = () => (
        <div className="flex h-full flex-col">
            <div className="flex items-center justify-between border-b border-zinc-700 px-4 py-3">
                <Link href="/dashboard" onClick={closeSidebar} className="flex items-center gap-2.5 text-[15px] font-semibold tracking-tight text-white no-underline">
                    <span className="flex size-7 shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-emerald-400 to-cyan-500 text-xs font-bold text-black">P</span>
                    pixelkraft
                </Link>
                <Button variant="ghost" size="icon" className="lg:hidden" onClick={closeSidebar}>
                    <X className="h-4 w-4" />
                </Button>
            </div>

            <nav className="flex-1 overflow-y-auto py-2">
                <div className="px-2 py-1">
                    <Link
                        href="/dashboard"
                        onClick={closeSidebar}
                        className={cn(
                            'flex items-center gap-2.5 rounded-md px-2.5 py-1.5 text-sm text-zinc-300 transition-colors hover:bg-zinc-800 hover:text-zinc-100',
                            isActive('/dashboard') && url === '/dashboard' && 'bg-zinc-800 text-zinc-100',
                        )}
                    >
                        <Home className="h-4 w-4 shrink-0" />
                        Dashboard
                    </Link>
                </div>

                <Separator className="my-1 bg-zinc-700/50" />

                <div className="px-2 py-1">
                    <p className="mb-1 px-2.5 text-[10px] font-semibold uppercase tracking-widest text-zinc-500">Projects</p>

                    <Link
                        href="/dashboard/sites"
                        onClick={closeSidebar}
                        className={cn(
                            'flex items-center gap-2.5 rounded-md px-2.5 py-1.5 text-sm text-zinc-300 transition-colors hover:bg-zinc-800 hover:text-zinc-100',
                            isActive('/dashboard/sites') && url === '/dashboard/sites' && 'bg-zinc-800 text-zinc-100',
                        )}
                    >
                        <Globe className="h-4 w-4 shrink-0" />
                        All sites
                    </Link>

                    {(navSites as NavSite[]).map((site) => {
                        const expanded = expandedSiteId === site.id;
                        const siteActive = url.includes(`/sites/${site.id}`);

                        return (
                            <div key={site.id}>
                                <div className="flex items-center gap-1">
                                    <Link
                                        href={`/dashboard/sites/${site.id}`}
                                        onClick={() => {
                                            setExpandedSiteId(site.id);
                                            closeSidebar();
                                        }}
                                        className={cn(
                                            'flex min-w-0 flex-1 items-center gap-2.5 rounded-md px-2.5 py-1.5 text-sm text-zinc-300 transition-colors hover:bg-zinc-800 hover:text-zinc-100',
                                            siteActive && 'bg-zinc-800 text-zinc-100',
                                        )}
                                    >
                                        <span className={cn('size-2 shrink-0 rounded-full', deployStatusColor(site.deploy_status))} />
                                        <span className="truncate text-left">{site.name}</span>
                                    </Link>
                                    <button
                                        type="button"
                                        onClick={() => setExpandedSiteId(expanded ? null : site.id)}
                                        className={cn(
                                            'flex h-8 w-8 shrink-0 items-center justify-center rounded-md text-zinc-500 transition-colors hover:bg-zinc-800 hover:text-zinc-200',
                                            (expanded || siteActive) && 'bg-zinc-800 text-zinc-300',
                                        )}
                                        aria-label={expanded ? `Collapse ${site.name}` : `Expand ${site.name}`}
                                    >
                                        {expanded ? <ChevronDown className="h-3 w-3" /> : <ChevronRight className="h-3 w-3" />}
                                    </button>
                                </div>

                                {expanded && (
                                    <div className="ml-6 flex flex-col gap-0.5 border-l border-zinc-600/90 pl-2 pb-1">
                                        {siteMenuItems(site).map((item) => {
                                            const itemContent = (
                                                <>
                                                    <item.icon className="h-3.5 w-3.5 shrink-0" />
                                                    <span className="flex-1">{item.label}</span>
                                                    {item.comingSoon && (
                                                        <span className="inline-flex items-center rounded px-1 py-0.5 font-mono text-[9px] font-medium bg-zinc-700/60 text-zinc-500 uppercase tracking-wide">
                                                            Soon
                                                        </span>
                                                    )}
                                                    {item.badge && item.badge > 0 && (
                                                        <span className={cn(
                                                            'inline-flex min-w-[1.25rem] items-center justify-center rounded-md px-1.5 py-0.5 font-mono text-[10px] font-medium',
                                                            item.badgeVariant === 'warning'
                                                                ? 'bg-amber-500/15 text-amber-400'
                                                                : 'bg-white/10 text-zinc-400',
                                                        )}>
                                                            {item.badge > 99 ? '99+' : item.badge}
                                                        </span>
                                                    )}
                                                </>
                                            );

                                            const itemClassName = cn(
                                                'flex items-center gap-2 rounded-md px-2 py-1.5 text-xs text-zinc-400 transition-colors hover:bg-zinc-800 hover:text-zinc-100',
                                                isActive(item.href) && 'bg-zinc-800 text-zinc-100',
                                            );

                                            return item.native ? (
                                                <a key={item.href} href={item.href} onClick={closeSidebar} className={itemClassName}>
                                                    {itemContent}
                                                </a>
                                            ) : (
                                                <Link key={item.href} href={item.href} onClick={closeSidebar} className={itemClassName}>
                                                    {itemContent}
                                                </Link>
                                            );
                                        })}
                                    </div>
                                )}
                            </div>
                        );
                    })}

                    <Link
                        href="/dashboard/sites/create"
                        onClick={closeSidebar}
                        className={cn(
                            'mt-1 flex items-center gap-2.5 rounded-md border border-white/10 bg-white/[0.04] px-2.5 py-1.5 text-sm text-zinc-200 transition-colors hover:bg-white/[0.08]',
                            isActive('/dashboard/sites/create') && 'border-transparent bg-emerald-500 text-zinc-950 hover:bg-emerald-400',
                        )}
                    >
                        <Plus className="h-4 w-4 shrink-0" />
                        New project
                    </Link>
                </div>
            </nav>

            <div className="border-t border-zinc-700 p-2">
                <Link
                    href="/dashboard/settings"
                    onClick={closeSidebar}
                    className={cn(
                        'flex items-center gap-2.5 rounded-md px-2.5 py-1.5 text-sm text-zinc-300 transition-colors hover:bg-zinc-800 hover:text-zinc-100',
                        isActive('/dashboard/settings') && 'bg-zinc-800 text-zinc-100',
                    )}
                >
                    <Settings className="h-4 w-4 shrink-0" />
                    Settings
                </Link>

                <button
                    onClick={() => setSearchOpen(true)}
                    className="mt-1 flex w-full items-center gap-2 rounded-lg border border-white/[0.08] bg-white/[0.03] px-2.5 py-1.5 text-left text-xs text-zinc-500 transition hover:border-white/15 hover:text-zinc-300"
                >
                    <Search className="h-3 w-3 shrink-0 opacity-50" />
                    <span>Search</span>
                    <span className="ml-auto inline-flex items-center rounded bg-white/[0.07] px-1 py-0.5 font-mono text-[9px] text-zinc-400">Ctrl+K</span>
                </button>

                {user && (
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <button className="mt-2 flex w-full items-center gap-2.5 rounded-md px-2 py-1.5 text-sm transition-colors hover:bg-zinc-800">
                                <Avatar className="h-7 w-7">
                                    <AvatarImage src={avatarUrl} alt={user.name} />
                                    <AvatarFallback>{user.name[0]}</AvatarFallback>
                                </Avatar>
                                <span className="flex-1 truncate text-left text-zinc-300">{user.name}</span>
                            </button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent side="top" align="start" className="w-52">
                            <DropdownMenuItem asChild>
                                <Link href="/dashboard/system"><Server className="mr-2 h-4 w-4" />System</Link>
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem asChild>
                                <Link href="/dashboard/settings"><Settings className="mr-2 h-4 w-4" />Settings</Link>
                            </DropdownMenuItem>
                            <DropdownMenuItem asChild>
                                <Link href="/logout" method="post" as="button" className="w-full">
                                    <LogOut className="mr-2 h-4 w-4" />Sign out
                                </Link>
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                )}
            </div>
        </div>
    );

    return (
        <TooltipProvider>
            <div className="flex min-h-screen">
                <aside className="hidden w-64 shrink-0 flex-col border-r border-zinc-700 bg-zinc-900 lg:flex">
                    <SidebarContent />
                </aside>

                {sidebarOpen && (
                    <div className="fixed inset-0 z-50 lg:hidden">
                        <div className="absolute inset-0 bg-black/60" onClick={closeSidebar} />
                        <aside className="absolute left-0 top-0 h-full w-64 border-r border-zinc-700 bg-zinc-900">
                            <SidebarContent />
                        </aside>
                    </div>
                )}

                <div className="flex min-w-0 flex-1 flex-col">
                    <header className="flex items-center gap-3 border-b border-zinc-700 bg-zinc-900 px-4 py-3 lg:hidden">
                        <Button variant="ghost" size="icon" onClick={() => setSidebarOpen(true)}>
                            <Menu className="h-5 w-5" />
                        </Button>
                        <span className="flex-1 text-sm font-semibold text-zinc-100">pixelkraft</span>
                        <Button variant="ghost" size="icon" onClick={() => setSearchOpen(true)}>
                            <Search className="h-5 w-5" />
                        </Button>
                        {user && (
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <Button variant="ghost" size="icon">
                                        <Avatar className="h-7 w-7">
                                            <AvatarImage src={avatarUrl} alt={user.name} />
                                            <AvatarFallback>{user.name[0]}</AvatarFallback>
                                        </Avatar>
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                    <DropdownMenuItem asChild>
                                        <Link href="/dashboard/settings"><Settings className="mr-2 h-4 w-4" />Settings</Link>
                                    </DropdownMenuItem>
                                    <DropdownMenuItem asChild>
                                        <Link href="/logout" method="post" as="button" className="w-full">
                                            <LogOut className="mr-2 h-4 w-4" />Sign out
                                        </Link>
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        )}
                    </header>

                    {(flash.success || flash.error) && (
                        <div className="px-6 pt-4">
                            {flash.success && (
                                <div className="mb-3 flex items-center gap-2 rounded-lg border border-emerald-500/20 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-400">
                                    {flash.success}
                                </div>
                            )}
                            {flash.error && (
                                <div className="mb-3 flex items-center gap-2 rounded-lg border border-red-500/20 bg-red-500/10 px-4 py-3 text-sm text-red-400">
                                    {flash.error}
                                </div>
                            )}
                        </div>
                    )}

                    <main className="flex-1 p-6">
                        {children}
                    </main>
                </div>
            </div>

            {searchOpen && (
                <div
                    className="fixed inset-0 z-[1000] flex items-start justify-center bg-black/60 pt-24 backdrop-blur-sm"
                    onClick={(event) => event.target === event.currentTarget && setSearchOpen(false)}
                >
                    <div className="w-full max-w-xl rounded-xl border border-white/10 bg-zinc-900 shadow-2xl">
                        <div className="flex items-center gap-3 border-b border-white/[0.08] px-5 py-4">
                            <Search className="h-4 w-4 shrink-0 text-zinc-500" />
                            <input
                                autoFocus
                                type="search"
                                placeholder="Search sites and sections..."
                                className="w-full border-0 bg-transparent text-sm text-white outline-none placeholder:text-zinc-600"
                                onKeyDown={(event) => event.key === 'Escape' && setSearchOpen(false)}
                            />
                        </div>
                        <div className="flex gap-4 border-t border-white/[0.06] px-5 py-2.5 text-[11px] text-zinc-600">
                            <span><kbd className="rounded bg-white/[0.08] px-1.5 py-0.5 font-mono text-zinc-400">Esc</kbd> close</span>
                        </div>
                    </div>
                </div>
            )}
        </TooltipProvider>
    );
}
