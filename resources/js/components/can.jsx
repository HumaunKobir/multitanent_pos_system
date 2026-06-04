import { useCan } from '@/hooks/use-can';

/**
 * Render children only when the user has the given permission(s).
 */
export function Can({ permission, children, fallback = null }) {
    const { can } = useCan();

    if (!can(permission)) {
        return fallback;
    }

    return children;
}
