import { Head } from '@inertiajs/react';
import RoleForm from './_form';

export default function RoleCreate({ permissionGroups }) {
    return (
        <>
            <Head title="Create Role" />
            <RoleForm permissionGroups={permissionGroups} />
        </>
    );
}
