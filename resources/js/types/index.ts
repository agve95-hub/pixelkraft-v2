export interface User {
    id: number;
    name: string;
    email: string;
    role: 'Admin' | 'Editor';
}

export interface NavSite {
    id: number;
    name: string;
    deploy_status: string;
    maintenance_settings: Record<string, unknown> | null;
    unread_inbox_count: number;
    unpaid_invoices_count: number;
    overdue_reminders_count: number;
}

export interface PageProps {
    auth: {
        user: User | null;
    };
    navSites: NavSite[];
    flash: {
        success: string | null;
        error: string | null;
    };
    [key: string]: unknown;
}
