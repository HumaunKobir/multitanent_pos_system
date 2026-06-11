import { useIsMobile } from '@/hooks/use-mobile';
import { createContext, useCallback, useContext, useEffect, useState } from 'react';

const SIDEBAR_COOKIE_NAME = 'panel_sidebar_state';
const SIDEBAR_COOKIE_MAX_AGE = 60 * 60 * 24 * 7;

const PanelSidebarContext = createContext(null);

export function usePanelSidebar() {
    const ctx = useContext(PanelSidebarContext);
    if (!ctx) {
        throw new Error('usePanelSidebar must be used within PanelSidebarProvider.');
    }
    return ctx;
}

export function PanelSidebarProvider({ children }) {
    const isMobile = useIsMobile();
    const [collapsed, setCollapsed] = useState(() => {
        if (typeof document === 'undefined') return false;
        const cookie = document.cookie
            .split('; ')
            .find((row) => row.startsWith(`${SIDEBAR_COOKIE_NAME}=`));
        return cookie ? cookie.split('=')[1] === 'collapsed' : false;
    });
    const [mobileOpen, setMobileOpen] = useState(false);

    useEffect(() => {
        setMobileOpen(false);
    }, [isMobile]);

    const toggle = useCallback(() => {
        if (isMobile) {
            setMobileOpen((prev) => !prev);
        } else {
            setCollapsed((prev) => {
                const next = !prev;
                document.cookie = `${SIDEBAR_COOKIE_NAME}=${next ? 'collapsed' : 'expanded'}; path=/; max-age=${SIDEBAR_COOKIE_MAX_AGE}`;
                return next;
            });
        }
    }, [isMobile]);

    const openMobile = useCallback(() => setMobileOpen(true), []);
    const closeMobile = useCallback(() => setMobileOpen(false), []);

    return (
        <PanelSidebarContext.Provider value={{ collapsed, mobileOpen, isMobile, toggle, openMobile, closeMobile }}>
            {children}
        </PanelSidebarContext.Provider>
    );
}
