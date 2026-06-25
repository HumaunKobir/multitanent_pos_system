import { PanelGuideSection } from '@/components/admin/panel-guide-section';
import { Head } from '@inertiajs/react';

export default function PanelGuidePage({ panelGuide }) {
    const panelLabel = panelGuide?.panelType === 'branch' ? 'Branch Panel' : 'Admin Panel';

    return (
        <>
            <Head title={`${panelLabel} Guide`} />

            <div className="min-h-full bg-background px-2 py-4 sm:px-4">
                <PanelGuideSection guide={panelGuide} embedded />
            </div>
        </>
    );
}
