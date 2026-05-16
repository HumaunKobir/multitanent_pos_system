import { jsx as _jsx } from "react/jsx-runtime";
import { cn } from '@/lib/utils';
export default function InputError({ message, className = '', ...props }) {
    return message ? (_jsx("p", { ...props, className: cn('text-sm text-red-600 dark:text-red-400', className), children: message })) : null;
}
