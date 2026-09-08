import * as React from 'react';
import { cn } from '@/lib/utils';

const TabsContext = React.createContext(null);

function useTabsContext() {
    const context = React.useContext(TabsContext);
    if (!context) {
        throw new Error('Tabs compound components must be rendered inside a Tabs component');
    }
    return context;
}

function Tabs({ value: controlledValue, defaultValue, onValueChange, className, children, ...props }) {
    const [uncontrolledValue, setUncontrolledValue] = React.useState(defaultValue);
    const isControlled = controlledValue !== undefined;
    const value = isControlled ? controlledValue : uncontrolledValue;

    const setValue = React.useCallback(
        (newValue) => {
            if (!isControlled) {
                setUncontrolledValue(newValue);
            }
            onValueChange?.(newValue);
        },
        [isControlled, onValueChange]
    );

    return (
        <TabsContext.Provider value={{ value, setValue }}>
            <div data-slot="tabs" className={cn('flex flex-col gap-2', className)} {...props}>
                {children}
            </div>
        </TabsContext.Provider>
    );
}

function TabsList({ className, ...props }) {
    return (
        <div
            data-slot="tabs-list"
            className={cn(
                'inline-flex h-9 items-center justify-center rounded-lg bg-muted p-1 text-muted-foreground',
                className
            )}
            role="tablist"
            {...props}
        />
    );
}

function TabsTrigger({ value: triggerValue, disabled = false, className, children, ...props }) {
    const { value, setValue } = useTabsContext();
    const isSelected = value === triggerValue;

    return (
        <button
            type="button"
            role="tab"
            aria-selected={isSelected}
            disabled={disabled}
            data-slot="tabs-trigger"
            data-state={isSelected ? 'active' : 'inactive'}
            tabIndex={isSelected ? 0 : -1}
            onClick={() => {
                if (!disabled) {
                    setValue(triggerValue);
                }
            }}
            className={cn(
                'inline-flex items-center justify-center whitespace-nowrap rounded-md px-3 py-1 text-sm font-medium ring-offset-background transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50',
                isSelected
                    ? 'bg-background text-foreground shadow-sm font-semibold'
                    : 'text-muted-foreground hover:bg-background/50 hover:text-foreground',
                className
            )}
            {...props}
        >
            {children}
        </button>
    );
}

function TabsContent({ value: contentValue, className, children, ...props }) {
    const { value } = useTabsContext();
    const isSelected = value === contentValue;

    if (!isSelected) {
        return null;
    }

    return (
        <div
            role="tabpanel"
            data-slot="tabs-content"
            data-state={isSelected ? 'active' : 'inactive'}
            tabIndex={0}
            className={cn('ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2', className)}
            {...props}
        >
            {children}
        </div>
    );
}

export { Tabs, TabsList, TabsTrigger, TabsContent };
