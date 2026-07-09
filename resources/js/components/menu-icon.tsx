import { createElement } from 'react';
import { menuIcon } from '@/lib/menu-icons';

/**
 * Renders a registry menu icon by its lucide name. Uses `createElement` so the
 * resolved (stable) icon component is not treated as one created during render.
 */
export function MenuIcon({
    name,
    className,
}: {
    name: string | null;
    className?: string;
}) {
    return createElement(menuIcon(name), { className });
}
