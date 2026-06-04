import { Can } from '@/components/can';
import { Button } from '@/components/ui/button';
import { useCan } from '@/hooks/use-can';
import { route } from '@/lib/route';
import { Link } from '@inertiajs/react';
import { Edit, Eye, Trash2 } from 'lucide-react';

/**
 * Standard view / edit / delete action buttons for admin resource tables.
 *
 * @param {string} prefix - Permission prefix, e.g. "inventory.sell"
 * @param {number|string} id - Resource id for route params
 * @param {string} [showRoute] - Named route for view (Eye)
 * @param {string} [editRoute] - Named route for edit
 * @param {() => void} [onDelete] - Called when delete clicked
 */
export function AdminRowActions({ prefix, id, showRoute, editRoute, onDelete }) {
    const { can } = useCan();

    return (
        <div className="flex justify-end gap-2">
            {showRoute && can(`${prefix}.view`) && (
                <Button size="sm" variant="outline" asChild>
                    <Link href={route(showRoute, id)}>
                        <Eye className="size-3.5" />
                    </Link>
                </Button>
            )}
            {editRoute && can(`${prefix}.update`) && (
                <Button size="sm" variant="outline" asChild>
                    <Link href={route(editRoute, id)}>
                        <Edit className="size-3.5" />
                    </Link>
                </Button>
            )}
            {onDelete && can(`${prefix}.delete`) && (
                <Button size="sm" variant="destructive" onClick={onDelete}>
                    <Trash2 className="size-3.5" />
                </Button>
            )}
        </div>
    );
}

export function AdminCreateLink({ permission, href, label, icon: Icon, className }) {
    return (
        <Can permission={permission}>
            <Button size="sm" asChild className={className}>
                <Link href={href}>
                    {Icon && <Icon className="size-3.5" />}
                    {label}
                </Link>
            </Button>
        </Can>
    );
}

/** Header button that opens a create dialog (not a link). */
export function AdminCreateButton({ permission, onClick, label, icon: Icon, className, ...props }) {
    return (
        <Can permission={permission}>
            <Button size="sm" onClick={onClick} className={className} {...props}>
                {Icon && <Icon className="size-3.5" />}
                {label}
            </Button>
        </Can>
    );
}

/** Edit / delete buttons for inline dialog-based resources (no view link). */
export function AdminInlineActions({ prefix, onEdit, onDelete }) {
    const { can } = useCan();

    return (
        <div className="flex justify-end gap-2">
            {can(`${prefix}.update`) && onEdit && (
                <Button size="sm" variant="outline" onClick={onEdit}>
                    <Edit className="size-3.5" />
                </Button>
            )}
            {can(`${prefix}.delete`) && onDelete && (
                <Button size="sm" variant="destructive" onClick={onDelete}>
                    <Trash2 className="size-3.5" />
                </Button>
            )}
        </div>
    );
}
