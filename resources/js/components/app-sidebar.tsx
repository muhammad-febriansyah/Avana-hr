import { Link, usePage } from '@inertiajs/react';
import { Building2, SlidersHorizontal } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMenuTree } from '@/components/nav-menu-tree';
import {
    Sidebar,
    SidebarContent,
    SidebarGroup,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { dashboard } from '@/routes';
import { index as menusIndex } from '@/routes/platform/menus';
import { index as tenantsIndex } from '@/routes/platform/tenants';
import type { NavItem, SharedData } from '@/types';

// The platform panel keeps a small hard-coded nav — it is not tenant-driven.
const platformNavItems: NavItem[] = [
    { title: 'Tenant', href: tenantsIndex(), icon: Building2 },
    { title: 'Registry Menu', href: menusIndex(), icon: SlidersHorizontal },
];

export function AppSidebar() {
    const { auth, menu } = usePage<SharedData>().props;

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
                {auth.isSuperAdmin ? (
                    <PlatformNav />
                ) : (
                    <NavMenuTree items={menu} />
                )}
            </SidebarContent>
        </Sidebar>
    );
}

function PlatformNav() {
    const { isCurrentUrl } = useCurrentUrl();

    return (
        <SidebarGroup className="px-2 py-0">
            <SidebarGroupLabel>Platform</SidebarGroupLabel>
            <SidebarMenu>
                {platformNavItems.map((item) => (
                    <SidebarMenuItem key={item.title}>
                        <SidebarMenuButton
                            asChild
                            isActive={isCurrentUrl(item.href)}
                            tooltip={{ children: item.title }}
                        >
                            <Link href={item.href} prefetch>
                                {item.icon && <item.icon />}
                                <span>{item.title}</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                ))}
            </SidebarMenu>
        </SidebarGroup>
    );
}
