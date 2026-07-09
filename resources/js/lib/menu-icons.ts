import {
    BarChart3,
    Building2,
    Calendar,
    CalendarCheck,
    Circle,
    Clock,
    Contact,
    GitBranch,
    Inbox,
    LayoutGrid,
    MapPin,
    Network,
    ScrollText,
    Settings,
    ShieldCheck,
    SlidersHorizontal,
    Users,
    Wallet,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';

// Curated map of lucide icons referenced by the seeded menu registry. Kept
// explicit (not a dynamic import of every icon) to keep the bundle small.
const MENU_ICONS: Record<string, LucideIcon> = {
    LayoutGrid,
    Users,
    Network,
    Clock,
    CalendarCheck,
    Wallet,
    Inbox,
    GitBranch,
    Contact,
    Calendar,
    BarChart3,
    ShieldCheck,
    ScrollText,
    Settings,
    SlidersHorizontal,
    Building2,
    MapPin,
};

/**
 * Resolve a menu icon name to its lucide component, falling back to a neutral
 * dot when the name is unknown or absent (e.g. submenu items).
 */
export function menuIcon(name: string | null): LucideIcon {
    return (name && MENU_ICONS[name]) || Circle;
}
