import { cn } from '@/lib/utils';
import { Coins } from 'lucide-react';

export function CustomerCoinBalance({ balance = 0, loading = false, variant = 'default', className }) {
    const onHeader = variant === 'header';

    return (
        <div
            className={cn(
                'flex shrink-0 items-center gap-1 whitespace-nowrap text-[10px] lg:text-[11px]',
                onHeader ? 'text-violet-200' : 'text-violet-800',
                className,
            )}
        >
            <Coins className={cn('size-3 shrink-0', onHeader ? 'text-violet-300' : 'text-violet-700')} />
            <span className={onHeader ? 'text-white/70' : 'text-muted-foreground'}>Coins:</span>
            {loading ? (
                <span className={onHeader ? 'text-white/80' : 'text-muted-foreground'}>Loading...</span>
            ) : (
                <span className={cn('font-semibold tabular-nums', onHeader ? 'text-white' : 'text-violet-950')}>
                    {balance.toFixed(2)}
                </span>
            )}
        </div>
    );
}
