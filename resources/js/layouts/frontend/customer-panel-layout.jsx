import { Head } from '@inertiajs/react';

import { CustomerPanelNav } from '@/components/frontend/customer-panel/customer-panel-nav';
import FrontendLayout from '@/layouts/frontend/frontend-layout';

export function CustomerPanelLayout({ children, title }) {
    return (
        <FrontendLayout hideTrustStrip>
            {title && <Head title={title} />}
            <div className="store-container py-4 pb-24 lg:pb-6">
                <CustomerPanelNav variant="mobile" className="lg:hidden" />

                <div className="mt-3 flex flex-col gap-4 lg:flex-row lg:items-start">
                    <aside className="hidden shrink-0 lg:block lg:w-[220px]" aria-label="Account menu">
                        <div className="sticky top-[calc(4rem+3.25rem)] overflow-hidden rounded-xl border border-gray-200/80 bg-white shadow-sm">
                            <CustomerPanelNav variant="sidebar" />
                        </div>
                    </aside>

                    <div className="min-w-0 flex-1">{children}</div>
                </div>
            </div>
        </FrontendLayout>
    );
}
