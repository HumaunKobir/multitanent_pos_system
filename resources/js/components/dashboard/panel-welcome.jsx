import { usePage } from '@inertiajs/react';

export function PanelWelcome({ userName, branchName, branchLogoUrl }) {
    const { hasPanelGuide, logo } = usePage().props;
    const displayLogo = branchLogoUrl || logo;

    return (
        <div className="mx-auto max-w-lg border border-border bg-card p-8 text-center shadow-none">
            {displayLogo ? (
                <img
                    src={displayLogo}
                    alt={`${branchName} logo`}
                    className="mx-auto mb-6 h-20 w-20 border border-border object-contain bg-white p-2"
                />
            ) : null}

            <p className="text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">{branchName}</p>
            <h2 className="mt-2 text-xl font-semibold tracking-tight text-foreground">Welcome, {userName}</h2>
            <p className="mt-3 text-sm text-muted-foreground">
                {hasPanelGuide
                    ? 'You are signed in. Use the sidebar to open the modules you can access.'
                    : 'No module access has been assigned yet. Contact your administrator for permissions.'}
            </p>
        </div>
    );
}
