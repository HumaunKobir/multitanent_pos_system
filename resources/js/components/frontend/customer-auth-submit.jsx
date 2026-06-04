import { ArrowRight, Loader2 } from 'lucide-react';
import { storeCn } from '@/lib/store-cn';

export function CustomerAuthSubmit({ children, className, ...props }) {
    return (
        <button
            type="submit"
            className={storeCn(
                'store-gradient auth-btn-shine group relative flex w-full items-center justify-center gap-2 rounded-xl px-4 py-3.5 text-sm font-semibold text-white',
                'shadow-lg shadow-[#667eea]/25 transition-all hover:-translate-y-0.5 hover:opacity-95 hover:shadow-xl',
                'active:translate-y-0 disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:translate-y-0',
                className,
            )}
            {...props}
        >
            <span>{children}</span>
            {props.disabled ? (
                <Loader2 className="size-4 animate-spin" />
            ) : (
                <ArrowRight className="size-4 transition-transform group-hover:translate-x-1" />
            )}
        </button>
    );
}
