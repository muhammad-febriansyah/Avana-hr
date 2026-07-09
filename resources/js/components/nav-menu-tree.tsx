import { Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { MenuIcon } from '@/components/menu-icon';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import type { MenuNode } from '@/types';

/**
 * Renders the DB-driven sidebar menu (see MenuService), including one level of
 * collapsible submenus. Items without a URL render as non-navigable labels.
 */
export function NavMenuTree({ items }: { items: MenuNode[] }) {
    return (
        <SidebarGroup className="px-2 py-0">
            <SidebarGroupLabel>Menu Utama</SidebarGroupLabel>
            <SidebarMenu>
                {items.map((item) =>
                    item.children.length > 0 ? (
                        <MenuGroup key={item.code} item={item} />
                    ) : (
                        <MenuLeaf key={item.code} item={item} />
                    ),
                )}
            </SidebarMenu>
        </SidebarGroup>
    );
}

function MenuLeaf({ item }: { item: MenuNode }) {
    const { isCurrentUrl } = useCurrentUrl();

    // Not-yet-built modules have no url; render as a non-navigable, dimmed row.
    if (item.url === null) {
        return (
            <SidebarMenuItem>
                <SidebarMenuButton
                    disabled
                    tooltip={{ children: item.label }}
                    className="cursor-default opacity-60 hover:bg-transparent"
                >
                    <MenuIcon name={item.icon} />
                    <span>{item.label}</span>
                </SidebarMenuButton>
            </SidebarMenuItem>
        );
    }

    return (
        <SidebarMenuItem>
            <SidebarMenuButton
                asChild
                isActive={isCurrentUrl(item.url)}
                tooltip={{ children: item.label }}
            >
                <Link href={item.url} prefetch>
                    <MenuIcon name={item.icon} />
                    <span>{item.label}</span>
                </Link>
            </SidebarMenuButton>
        </SidebarMenuItem>
    );
}

function MenuGroup({ item }: { item: MenuNode }) {
    const { isCurrentUrl } = useCurrentUrl();

    const hasActiveChild = item.children.some(
        (child) => child.url && isCurrentUrl(child.url),
    );

    return (
        <Collapsible
            asChild
            defaultOpen={hasActiveChild}
            className="group/collapsible"
        >
            <SidebarMenuItem>
                <CollapsibleTrigger asChild>
                    <SidebarMenuButton tooltip={{ children: item.label }}>
                        <MenuIcon name={item.icon} />
                        <span>{item.label}</span>
                        <ChevronRight className="ml-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90" />
                    </SidebarMenuButton>
                </CollapsibleTrigger>
                <CollapsibleContent>
                    <SidebarMenuSub>
                        {item.children.map((child) =>
                            child.url === null ? (
                                <SidebarMenuSubItem key={child.code}>
                                    <SidebarMenuSubButton
                                        aria-disabled
                                        className="cursor-default opacity-60 hover:bg-transparent"
                                    >
                                        <span>{child.label}</span>
                                    </SidebarMenuSubButton>
                                </SidebarMenuSubItem>
                            ) : (
                                <SidebarMenuSubItem key={child.code}>
                                    <SidebarMenuSubButton
                                        asChild
                                        isActive={isCurrentUrl(child.url)}
                                    >
                                        <Link href={child.url} prefetch>
                                            <span>{child.label}</span>
                                        </Link>
                                    </SidebarMenuSubButton>
                                </SidebarMenuSubItem>
                            ),
                        )}
                    </SidebarMenuSub>
                </CollapsibleContent>
            </SidebarMenuItem>
        </Collapsible>
    );
}
