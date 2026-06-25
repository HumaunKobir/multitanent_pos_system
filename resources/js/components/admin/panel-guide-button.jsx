import { route } from '@/lib/route';
import { BookOpen, ExternalLink } from 'lucide-react';
import { usePage } from '@inertiajs/react';

export function PanelGuideButton() {
    const { showPanelGuideButton, hasPanelGuide } = usePage().props;

    if (!showPanelGuideButton || !hasPanelGuide) {
        return null;
    }

    return (
        <div className="flex justify-end border-b border-border bg-muted/30 px-2 py-2 sm:px-4">
            <a
                href={route('panel-guide')}
                target="_blank"
                rel="noopener noreferrer"
                className="inline-flex items-center gap-2 border border-blue-950 bg-blue-950 px-4 py-2 text-xs font-semibold text-white transition-colors hover:bg-blue-900"
            >
                <BookOpen className="size-4" />
                How to use this panel
                <ExternalLink className="size-3.5 opacity-70" />
            </a>
        </div>
    );
}
