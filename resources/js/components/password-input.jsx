import { Eye, EyeOff } from 'lucide-react';
import { useState } from 'react';
import { jsx as _jsx, jsxs as _jsxs } from "react/jsx-runtime";
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
export default function PasswordInput({ className, ref, ...props }) {
    const [showPassword, setShowPassword] = useState(false);

    return (_jsxs("div", { className: "relative", children: [_jsx(Input, { type: showPassword ? 'text' : 'password', className: cn('pr-10', className), ref: ref, ...props }), _jsx("button", { type: "button", onClick: () => setShowPassword((prev) => !prev), className: "absolute inset-y-0 right-0 flex items-center rounded-none px-3 text-muted-foreground hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring focus-visible:outline-none", "aria-label": showPassword ? 'Hide password' : 'Show password', tabIndex: -1, children: showPassword ? (_jsx(EyeOff, { className: "size-4" })) : (_jsx(Eye, { className: "size-4" })) })] }));
}
