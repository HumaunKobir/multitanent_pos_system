import { Head } from '@inertiajs/react';
import RoleForm from './_form';

export default function RoleEdit({ role, permissionGroups }) {
    return (
        <>
            <Head title={`Edit Role — ${role.name}`} />
            <RoleForm role={role} permissionGroups={permissionGroups} />
        </>
    );
}
