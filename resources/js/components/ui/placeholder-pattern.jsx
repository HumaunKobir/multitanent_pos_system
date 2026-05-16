import { jsx as _jsx, jsxs as _jsxs } from "react/jsx-runtime";
import { useId } from 'react';
export function PlaceholderPattern({ className }) {
    const patternId = useId();
    return (_jsxs("svg", { className: className, fill: "none", children: [_jsx("defs", { children: _jsx("pattern", { id: patternId, x: "0", y: "0", width: "10", height: "10", patternUnits: "userSpaceOnUse", children: _jsx("path", { d: "M-3 13 15-5M-5 5l18-18M-1 21 17 3" }) }) }), _jsx("rect", { stroke: "none", fill: `url(#${patternId})`, width: "100%", height: "100%" })] }));
}
