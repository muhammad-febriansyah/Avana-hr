import type { Auth } from './auth';

/**
 * A resolved sidebar menu node (see MenuService). `url` is null for group
 * headers and not-yet-built placeholder items; `children` nests one level.
 */
export type MenuNode = {
    code: string;
    label: string;
    icon: string | null;
    url: string | null;
    children: MenuNode[];
};

/**
 * Props shared with every Inertia page (see HandleInertiaRequests::share).
 */
export type SharedData = {
    name: string;
    auth: Auth;
    menu: MenuNode[];
    sidebarOpen: boolean;
    [key: string]: unknown;
};
