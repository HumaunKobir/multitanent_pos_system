import { AlertCircleIcon } from 'lucide-react';
import { jsx as _jsx, jsxs as _jsxs } from "react/jsx-runtime";
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
export default function AlertError({ errors, title, }) {
    return (_jsxs(Alert, { variant: "destructive", children: [_jsx(AlertCircleIcon, {}), _jsx(AlertTitle, { children: title || 'Something went wrong.' }), _jsx(AlertDescription, { children: _jsx("ul", { className: "list-inside list-disc text-sm", children: Array.from(new Set(errors)).map((error, index) => (_jsx("li", { children: error }, index))) }) })] }));
}
