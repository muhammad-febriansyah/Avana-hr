import { Link } from '@inertiajs/react';
import {
    BarChart3,
    Calendar,
    CalendarCheck,
    Clock,
    Contact,
    LayoutGrid,
    Network,
    Settings,
    Users,
    Wallet,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

// Modul Wave 1. Rute yang belum dibangun sementara diarahkan ke '#'
// (menu dinamis dari DB menyusul — Addendum M10).
const mainNavItems: NavItem[] = [
    { title: 'Dashboard', href: dashboard(), icon: LayoutGrid },
    { title: 'Karyawan', href: '#', icon: Users },
    { title: 'Struktur Organisasi', href: '#', icon: Network },
    { title: 'Kehadiran', href: '#', icon: Clock },
    { title: 'Cuti', href: '#', icon: CalendarCheck },
    { title: 'Payroll', href: '#', icon: Wallet },
    { title: 'CRM', href: '#', icon: Contact },
    { title: 'Kalender', href: '#', icon: Calendar },
    { title: 'Laporan', href: '#', icon: BarChart3 },
    { title: 'Pengaturan', href: '/settings', icon: Settings },
];

export function AppSidebar() {
    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
