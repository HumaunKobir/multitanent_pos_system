import { Breadcrumbs } from '@/components/breadcrumbs';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { cn } from '@/lib/utils';

/**
 * @param {{ breadcrumbs?: { title: string; href: string }[] }} props
 */
export function AppSidebarHeader({ breadcrumbs = [] }) {
    return (
        <header
            className={cn(
                'flex h-14 shrink-0 items-center gap-3 border-b border-border/80 bg-muted/20 px-4 transition-[width,height] ease-linear',
                'md:px-5',
                'group-has-data-[collapsible=icon]/sidebar-wrapper:h-12',
            )}
        >
            <SidebarTrigger
                className={cn(
                    'size-8 shrink-0 border border-border bg-background shadow-none',
                    'hover:bg-muted/80',
                    'focus-visible:ring-2 focus-visible:ring-ring',
                )}
            />
            <div className="min-w-0 flex-1">
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>
        </header>
    );
}
