import { Link } from '@inertiajs/react';
import { ArrowLeft, ChevronDown, Layers, LayoutDashboard, LayoutTemplate } from 'lucide-react';
import { useEffect, useState } from 'react';

import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn } from '@/lib/utils';
import { dashboard as appDashboard } from '@/routes';
import { dashboard as adminDashboard, uiShowcase } from '@/routes/admin';

/**
 * @typedef {{ title: string; href: string; icon: import('react').ComponentType<{ className?: string; 'aria-hidden'?: boolean }> }} AdminNavLeaf
 */

/**
 * @typedef {{ title: string; icon: import('react').ComponentType<{ className?: string; 'aria-hidden'?: boolean }>; children: AdminNavLeaf[] }} AdminNavSection
 */

/** @type {AdminNavSection[]} */
const navSections = [
    {
        title: 'Workspace',
        icon: LayoutTemplate,
        children: [
            { title: 'Overview', href: adminDashboard.url(), icon: LayoutDashboard },
            { title: 'UI showcase', href: uiShowcase.url(), icon: Layers },
        ],
    },
];

/** Selected nav: saturated accent + lift shadow (not theme black/gray). */
const navSelectedClass =
    'border-indigo-500/90 bg-indigo-600 font-semibold text-white shadow-[0_6px_22px_-6px_rgba(79,70,229,0.55),0_2px_8px_-2px_rgba(67,56,202,0.35)] dark:border-indigo-400/70 dark:bg-indigo-700 dark:shadow-[0_6px_22px_-6px_rgba(67,56,202,0.5),0_2px_8px_-2px_rgba(55,48,163,0.4)]';

function navLinkClass(active) {
    return cn(
        'group flex items-center gap-2.5 border px-2.5 py-2 text-[0.8125rem] leading-tight tracking-tight transition-[border-color,background-color,color,font-weight,box-shadow]',
        'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-400/60 focus-visible:ring-offset-2 focus-visible:ring-offset-sidebar',
        active
            ? cn(navSelectedClass, 'hover:bg-indigo-500 dark:hover:bg-indigo-600')
            : cn(
                  'border-transparent font-medium text-sidebar-foreground/90 shadow-none',
                  'hover:border-border/80 hover:bg-sidebar-accent/60 hover:text-sidebar-foreground',
              ),
    );
}

export function AdminSidebar() {
    const { currentUrl, isCurrentUrl } = useCurrentUrl();

    const [expanded, setExpanded] = useState(() => {
        const initial = new Set(
            navSections.filter((s) => s.children.some((c) => isCurrentUrl(c.href))).map((s) => s.title),
        );
        if (initial.size === 0) {
            navSections.forEach((s) => initial.add(s.title));
        }
        return initial;
    });

    useEffect(() => {
        setExpanded((prev) => {
            const next = new Set(prev);
            let changed = false;
            for (const s of navSections) {
                const hasActive = s.children.some((c) => isCurrentUrl(c.href));
                if (hasActive && !next.has(s.title)) {
                    next.add(s.title);
                    changed = true;
                }
            }
            return changed ? next : prev;
        });
    }, [currentUrl]);

    const toggleSection = (title) => {
        setExpanded((prev) => {
            const next = new Set(prev);
            if (next.has(title)) {
                next.delete(title);
            } else {
                next.add(title);
            }
            return next;
        });
    };

    return (
        <aside className="flex w-68 shrink-0 flex-col border-r border-border bg-sidebar text-sidebar-foreground">
            <div className="relative border-b border-sidebar-border bg-linear-to-b from-muted/40 to-transparent px-4 py-5">
                <div
                    className="pointer-events-none absolute inset-x-0 top-0 h-px bg-linear-to-r from-transparent via-primary/40 to-transparent"
                    aria-hidden
                />
                <p className="font-mono text-[0.65rem] font-medium uppercase tracking-[0.28em] text-muted-foreground">
                    Administration
                </p>
                <p className="mt-2 font-semibold tracking-tight text-foreground">Control center</p>
            </div>

            <nav className="flex flex-1 flex-col gap-2 p-3" aria-label="Admin navigation">
                <p className="mb-0.5 flex items-center gap-2 px-2 pb-1 font-mono text-[0.6rem] font-semibold uppercase tracking-[0.22em] text-muted-foreground">
                    <span className="h-px flex-1 bg-border" aria-hidden />
                    <span className="shrink-0">Menu</span>
                    <span className="h-px flex-1 bg-border" aria-hidden />
                </p>

                <ul className="flex flex-col gap-0.5">
                    {navSections.map((section) => {
                        const SectionIcon = section.icon;
                        const isOpen = expanded.has(section.title);
                        const sectionActive = section.children.some((c) => isCurrentUrl(c.href));
                        const submenuId = `admin-submenu-${section.title.replace(/\s+/g, '-')}`;

                        return (
                            <li key={section.title}>
                                <button
                                    type="button"
                                    id={`admin-menu-${section.title.replace(/\s+/g, '-')}`}
                                    className={cn(
                                        'flex w-full items-center gap-2.5 border border-transparent px-2.5 py-1.5 text-left text-[0.8125rem] font-semibold tracking-tight transition-[border-color,background-color,color]',
                                        'hover:border-border/80 hover:bg-sidebar-accent/50',
                                        sectionActive && isOpen && 'text-foreground',
                                        !sectionActive && 'text-sidebar-foreground',
                                    )}
                                    aria-expanded={isOpen}
                                    aria-controls={submenuId}
                                    onClick={() => toggleSection(section.title)}
                                >
                                    <SectionIcon
                                        className={cn(
                                            'size-4 shrink-0',
                                            sectionActive ? 'text-primary' : 'text-muted-foreground',
                                        )}
                                        strokeWidth={2}
                                        aria-hidden
                                    />
                                    <span className="min-w-0 flex-1 truncate">{section.title}</span>
                                    <ChevronDown
                                        className={cn(
                                            'size-4 shrink-0 text-muted-foreground transition-transform duration-200',
                                            isOpen && 'rotate-180',
                                            sectionActive && 'text-indigo-600 dark:text-indigo-400',
                                        )}
                                        aria-hidden
                                    />
                                </button>

                                <ul
                                    id={submenuId}
                                    aria-labelledby={`admin-menu-${section.title.replace(/\s+/g, '-')}`}
                                    className={cn(
                                        'mt-0.5 space-y-0.5 border-l-2 border-border pl-2 ml-3',
                                        !isOpen && 'hidden',
                                    )}
                                >
                                    {section.children.map((child) => {
                                        const active = isCurrentUrl(child.href);
                                        const ChildIcon = child.icon;

                                        return (
                                            <li key={child.href}>
                                                <Link
                                                    href={child.href}
                                                    prefetch
                                                    className={navLinkClass(active)}
                                                    aria-current={active ? 'page' : undefined}
                                                >
                                                    <ChildIcon
                                                        className={cn(
                                                            'size-4 shrink-0',
                                                            active
                                                                ? 'text-white'
                                                                : 'text-muted-foreground group-hover:text-foreground',
                                                        )}
                                                        strokeWidth={active ? 2.25 : 2}
                                                        aria-hidden
                                                    />
                                                    <span className="min-w-0 truncate">{child.title}</span>
                                                </Link>
                                            </li>
                                        );
                                    })}
                                </ul>
                            </li>
                        );
                    })}
                </ul>
            </nav>

            <div className="border-t border-sidebar-border p-3">
                <Link
                    href={appDashboard.url()}
                    prefetch
                    className="flex items-center gap-2 border border-dashed border-sidebar-border px-3 py-2.5 text-xs font-medium text-muted-foreground transition-colors hover:border-border hover:bg-sidebar-accent/40 hover:text-sidebar-foreground"
                >
                    <ArrowLeft className="size-3.5 shrink-0" aria-hidden />
                    Back to app dashboard
                </Link>
            </div>
        </aside>
    );
}
