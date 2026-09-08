import {
    createContext,
    useCallback,
    useEffect,
    useMemo,
    useState,
    type ReactNode,
} from 'react';

export const THEME_STORAGE_KEY = 'pmb-theme';

export type ThemeMode = 'dark' | 'light';

export interface ThemeContextValue {
    isDark: boolean;
    toggleTheme: () => void;
}

export const ThemeContext = createContext<ThemeContextValue | null>(null);

export function getInitialTheme(): ThemeMode {
    if (typeof window === 'undefined') {
        return 'dark';
    }

    try {
        const stored = localStorage.getItem(THEME_STORAGE_KEY);
        if (stored === 'dark' || stored === 'light') {
            return stored;
        }
    } catch {
        // localStorage unavailable
    }

    if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
        return 'dark';
    }

    return 'dark';
}

export function ThemeProvider({ children }: { children: ReactNode }) {
    const [mode, setMode] = useState<ThemeMode>(getInitialTheme);
    const isDark = mode === 'dark';

    useEffect(() => {
        document.documentElement.setAttribute('data-theme', isDark ? 'dark' : 'light');

        try {
            localStorage.setItem(THEME_STORAGE_KEY, mode);
        } catch {
            // localStorage unavailable
        }
    }, [isDark, mode]);

    const toggleTheme = useCallback(() => {
        setMode((prev) => (prev === 'dark' ? 'light' : 'dark'));
    }, []);

    const value = useMemo(
        () => ({
            isDark,
            toggleTheme,
        }),
        [isDark, toggleTheme],
    );

    return <ThemeContext.Provider value={value}>{children}</ThemeContext.Provider>;
}
