import { jsx as _jsx } from "react/jsx-runtime";
import * as React from "react";
import { cn } from "@/lib/utils";
function Input({ className, type, onWheel, ...props }) {
    const inputRef = React.useRef(null);
    React.useEffect(() => {
        if (type !== 'number') {
            return undefined;
        }
        const element = inputRef.current;
        if (!element) {
            return undefined;
        }
        function handleWheel(event) {
            event.preventDefault();
        }
        element.addEventListener('wheel', handleWheel, { passive: false });
        return () => element.removeEventListener('wheel', handleWheel);
    }, [type]);
    return (_jsx("input", { ref: inputRef, type: type, "data-slot": "input", onWheel: onWheel, className: cn("border-input file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground flex h-9 w-full min-w-0 rounded-none border bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm", "focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]", "aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive", className), ...props }));
}
export { Input };
