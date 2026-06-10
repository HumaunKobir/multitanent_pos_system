const staffSteps = [
    { number: 1, label: 'Email' },
    { number: 2, label: 'Verify code' },
    { number: 3, label: 'New password' },
];

export function StaffPasswordResetSteps({ currentStep }) {
    return (
        <div className="mb-8">
            <div className="flex items-center justify-between gap-2">
                {staffSteps.map((step, index) => {
                    const isComplete = currentStep > step.number;
                    const isCurrent = currentStep === step.number;

                    return (
                        <div key={step.number} className="flex flex-1 items-center gap-2">
                            <div className="flex min-w-0 flex-1 flex-col items-center gap-2">
                                <div
                                    className={[
                                        'flex size-8 shrink-0 items-center justify-center rounded-full text-xs font-bold transition-colors',
                                        isComplete || isCurrent
                                            ? 'bg-indigo-600 text-white'
                                            : 'bg-zinc-100 text-zinc-400 dark:bg-zinc-800',
                                    ].join(' ')}
                                >
                                    {isComplete ? '✓' : step.number}
                                </div>
                                <span
                                    className={[
                                        'text-center text-[10px] font-semibold uppercase tracking-wide',
                                        isCurrent ? 'text-indigo-600' : 'text-muted-foreground',
                                    ].join(' ')}
                                >
                                    {step.label}
                                </span>
                            </div>
                            {index < staffSteps.length - 1 ? (
                                <div
                                    className={[
                                        'mb-5 h-px flex-1',
                                        isComplete ? 'bg-indigo-600' : 'bg-zinc-200 dark:bg-zinc-700',
                                    ].join(' ')}
                                />
                            ) : null}
                        </div>
                    );
                })}
            </div>
        </div>
    );
}

const customerSteps = [
    { number: 1, label: 'Email' },
    { number: 2, label: 'Verify' },
    { number: 3, label: 'Password' },
];

export function CustomerPasswordResetSteps({ currentStep }) {
    return (
        <div className="mb-6">
            <div className="flex items-center justify-between gap-1">
                {customerSteps.map((step, index) => {
                    const isComplete = currentStep > step.number;
                    const isCurrent = currentStep === step.number;

                    return (
                        <div key={step.number} className="flex flex-1 items-center gap-1">
                            <div className="flex min-w-0 flex-1 flex-col items-center gap-1.5">
                                <div
                                    className={[
                                        'flex size-7 shrink-0 items-center justify-center rounded-full text-[11px] font-bold',
                                        isComplete || isCurrent
                                            ? 'store-gradient text-white shadow-md shadow-[#667eea]/25'
                                            : 'bg-gray-100 text-store-muted',
                                    ].join(' ')}
                                >
                                    {isComplete ? '✓' : step.number}
                                </div>
                                <span
                                    className={[
                                        'text-center text-[9px] font-semibold uppercase tracking-wide',
                                        isCurrent ? 'text-store-accent' : 'text-store-muted',
                                    ].join(' ')}
                                >
                                    {step.label}
                                </span>
                            </div>
                            {index < customerSteps.length - 1 ? (
                                <div
                                    className={[
                                        'mb-4 h-px flex-1',
                                        isComplete ? 'bg-store-accent/60' : 'bg-gray-200',
                                    ].join(' ')}
                                />
                            ) : null}
                        </div>
                    );
                })}
            </div>
        </div>
    );
}
