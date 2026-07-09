import { usePage } from '@inertiajs/react';
import type { SharedData } from '@/types';

/**
 * Read the authenticated user's permissions from Inertia shared props.
 * Super Admins implicitly pass every check.
 */
export function usePermissions() {
    const { auth } = usePage<SharedData>().props;

    const can = (permission: string): boolean =>
        auth.isSuperAdmin || auth.permissions.includes(permission);

    return { can, isSuperAdmin: auth.isSuperAdmin };
}
