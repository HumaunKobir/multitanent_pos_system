import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { cn } from '@/lib/utils';
import { Link, usePage } from '@inertiajs/react';
import {
    BarChart2,
    BookOpen,
    Building2,
    ChevronDown,
    CircleDollarSign,
    Contact,
    Globe,
    HandCoins,
    LayoutDashboard,
    Settings,
    Shield,
    User,
    UserCog,
    UsersRound,
    Wallet,
} from 'lucide-react';
import { useState } from 'react';

const ICONS = {
    'layout-dashboard': LayoutDashboard,
    'circle-dollar-sign': CircleDollarSign,
    'hand-coins': HandCoins,
    user: User,
    'users-round': UsersRound,
    contact: Contact,
    'building-2': Building2,
    'user-cog': UserCog,
    shield: Shield,
    globe: Globe,
    settings: Settings,
    wallet: Wallet,
    'bar-chart-2': BarChart2,
};

function SectionIcon({ name, className }) {
    const Icon = ICONS[name] ?? BookOpen;

    return <Icon className={className} />;
}

function GuideItem({ item, defaultOpen = false }) {
    const [open, setOpen] = useState(defaultOpen);

    return (
        <Collapsible open={open} onOpenChange={setOpen} className="border border-border bg-card">
            <CollapsibleTrigger className="flex w-full items-center justify-between gap-3 px-4 py-3 text-left hover:bg-muted/40">
                <div className="min-w-0 flex-1">
                    <p className="text-sm font-semibold text-foreground">{item.title}</p>
                    <p className="mt-0.5 line-clamp-2 text-xs text-muted-foreground">{item.summary}</p>
                </div>
                <ChevronDown
                    className={cn('size-4 shrink-0 text-muted-foreground transition-transform', open && 'rotate-180')}
                />
            </CollapsibleTrigger>
            <CollapsibleContent className="border-t border-border px-4 py-3">
                {item.steps?.length > 0 ? (
                    <ol className="list-decimal space-y-2 pl-4 text-sm text-foreground/90">
                        {item.steps.map((step, index) => (
                            <li key={index}>{step}</li>
                        ))}
                    </ol>
                ) : null}
                {item.tips?.length > 0 ? (
                    <ul className="mt-3 space-y-1 border-t border-border pt-3 text-xs text-muted-foreground">
                        {item.tips.map((tip, index) => (
                            <li key={index}>Tip: {tip}</li>
                        ))}
                    </ul>
                ) : null}
                {item.href ? (
                    <Link
                        href={item.href}
                        className="mt-3 inline-flex items-center gap-1 text-xs font-medium text-blue-700 hover:underline dark:text-blue-400"
                    >
                        Open {item.title} →
                    </Link>
                ) : null}
            </CollapsibleContent>
        </Collapsible>
    );
}

export function PanelGuideSection({ className, embedded = false, guide: guideProp }) {
    const { panelGuide: pageGuide, panelType: sharedPanelType, auth, branchSubscription } = usePage().props;
    const panelGuide = guideProp ?? pageGuide;
    const sections = panelGuide?.sections ?? [];
    const panelType = panelGuide?.panelType ?? sharedPanelType;

    if (sections.length === 0) {
        return null;
    }

    const branchName = auth?.user?.branch?.name || branchSubscription?.branch_name || '';
    const panelLabel = panelType === 'branch' ? (branchName || 'Branch') : 'Admin Panel';
    const userName = auth?.user?.name ?? 'User';

    return (
        <section
            className={cn(
                embedded ? '' : 'border-b border-border bg-muted/20 px-2 py-3 sm:px-4',
                className,
            )}
        >
            <div className={cn(embedded ? '' : 'mx-auto max-w-6xl')}>
                <div className="mb-3 flex items-start gap-3 border border-blue-950 bg-blue-950 px-4 py-3 text-white">
                    <div className="flex size-9 shrink-0 items-center justify-center border border-white/20 bg-white/10">
                        <BookOpen className="size-4" />
                    </div>
                    <div>
                        <h2 className="text-sm font-semibold tracking-tight">How to use this panel</h2>
                        <p className="mt-0.5 text-xs text-white/70">
                            {panelLabel} guide for {userName}. Only features you can access are listed below.
                        </p>
                    </div>
                </div>

                <div className="space-y-4">
                    {sections.map((section) => (
                        <div key={section.title}>
                            <div className="mb-2 flex items-center gap-2">
                                <SectionIcon name={section.icon} className="size-4 text-blue-950 dark:text-blue-400" />
                                <h3 className="text-xs font-semibold uppercase tracking-widest text-muted-foreground">
                                    {section.title}
                                </h3>
                            </div>
                            <div className="space-y-2">
                                {section.items.map((item, index) => (
                                    <GuideItem key={`${section.title}-${item.title}`} item={item} defaultOpen={index === 0} />
                                ))}
                            </div>
                        </div>
                    ))}
                </div>
            </div>
        </section>
    );
}
