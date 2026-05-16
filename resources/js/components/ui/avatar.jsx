import { jsx as _jsx } from "react/jsx-runtime";
import * as AvatarPrimitive from "@radix-ui/react-avatar";
import * as React from "react";
import { cn } from "@/lib/utils";
function Avatar({ className, ...props }) {
    return (_jsx(AvatarPrimitive.Root, { "data-slot": "avatar", className: cn("relative flex size-8 shrink-0 overflow-hidden rounded-none", className), ...props }));
}
function AvatarImage({ className, ...props }) {
    return (_jsx(AvatarPrimitive.Image, { "data-slot": "avatar-image", className: cn("aspect-square size-full", className), ...props }));
}
function AvatarFallback({ className, ...props }) {
    return (_jsx(AvatarPrimitive.Fallback, { "data-slot": "avatar-fallback", className: cn("bg-muted flex size-full items-center justify-center rounded-none", className), ...props }));
}
export { Avatar, AvatarImage, AvatarFallback };
