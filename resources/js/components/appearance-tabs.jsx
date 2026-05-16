import { Monitor, Moon, Sun } from 'lucide-react';
import { jsx as _jsx, jsxs as _jsxs } from "react/jsx-runtime";
import { useAppearance } from '@/hooks/use-appearance';
import { cn } from '@/lib/utils';
export default function AppearanceToggleTab({ className = '', ...props }) {
    const { appearance, updateAppearance } = useAppearance();
    const tabs = [
        { value: 'light', icon: Sun, label: 'Light' },
        { value: 'dark', icon: Moon, label: 'Dark' },
        { value: 'system', icon: Monitor, label: 'System' },
    ];

    return (_jsx("div", { className: cn('inline-flex gap-1 rounded-none bg-neutral-100 p-1 dark:bg-neutral-800', className), ...props, children: tabs.map(({ value, icon: Icon, label }) => (_jsxs("button", { onClick: () => updateAppearance(value), className: cn('flex items-center rounded-none px-3.5 py-1.5 transition-colors', appearance === value
                ? 'bg-white shadow-xs dark:bg-neutral-700 dark:text-neutral-100'
                : 'text-neutral-500 hover:bg-neutral-200/60 hover:text-black dark:text-neutral-400 dark:hover:bg-neutral-700/60'), children: [_jsx(Icon, { className: "-ml-1 h-4 w-4" }), _jsx("span", { className: "ml-1.5 text-sm", children: label })] }, value))) }));
}
