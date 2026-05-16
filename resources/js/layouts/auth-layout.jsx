import { jsx as _jsx } from "react/jsx-runtime";
import AuthLayoutTemplate from '@/layouts/auth/auth-simple-layout';
export default function AuthLayout({ title = '', description = '', children, }) {
    return (_jsx(AuthLayoutTemplate, { title: title, description: description, children: children }));
}
