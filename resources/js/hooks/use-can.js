import { usePage } from '@inertiajs/react';
import { useCallback, useMemo } from 'react';

/**
 * Check Spatie permissions shared from HandleInertiaRequests (auth.permissions).
 * Super admins receive ['*'] and pass every check.
 */
export function useCan() {
    const permissions = usePage().props.auth?.permissions ?? [];

    const isSuperAdmin = useMemo(() => permissions.includes('*'), [permissions]);

    const can = useCallback(
        (permission) => {
            if (!permission) {
                return true;
            }

            if (isSuperAdmin) {
                return true;
            }

            if (Array.isArray(permission)) {
                return permission.some((name) => permissions.includes(name));
            }

            return permissions.includes(permission);
        },
        [isSuperAdmin, permissions],
    );

    const cannot = useCallback((permission) => !can(permission), [can]);

    return { can, cannot, isSuperAdmin, permissions };
}
