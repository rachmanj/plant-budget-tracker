import '../css/app.css';
import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ConfigProvider, App as AntApp, theme } from 'antd';
import idID from 'antd/locale/id_ID';
import type { ReactNode } from 'react';
import { ThemeProvider } from './providers/ThemeProvider';
import { useTheme } from './Hooks/useTheme';

const { darkAlgorithm, defaultAlgorithm } = theme;

const appName = import.meta.env.VITE_APP_NAME || 'Plant Budget Tracker';

function ThemedApp({ children }: { children: ReactNode }) {
    const { isDark } = useTheme();

    return (
        <ConfigProvider
            locale={idID}
            theme={{
                algorithm: isDark ? darkAlgorithm : defaultAlgorithm,
                token: {
                    colorPrimary: isDark ? '#2dd4bf' : '#0d9488',
                    colorSuccess: '#10b981',
                    colorWarning: '#f59e0b',
                    colorError: '#ef4444',
                    colorInfo: '#0ea5e9',
                    borderRadius: 8,
                },
            }}
        >
            <AntApp>{children}</AntApp>
        </ConfigProvider>
    );
}

createInertiaApp({
    title: (title) => (title ? `${title} — ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(`./Pages/${name}.tsx`, import.meta.glob('./Pages/**/*.tsx')),
    setup({ el, App, props }) {
        createRoot(el).render(
            <ThemeProvider>
                <ThemedApp>
                    <App {...props} />
                </ThemedApp>
            </ThemeProvider>,
        );
    },
    progress: {
        color: '#0d9488',
    },
});
