import '../css/app.css';
import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ConfigProvider, App as AntApp } from 'antd';
import enUS from 'antd/locale/en_US';
import dayjs from 'dayjs';
import 'dayjs/locale/en';
import type { ReactNode } from 'react';

dayjs.locale('en');
import { ThemeProvider } from './providers/ThemeProvider';
import { useTheme } from './Hooks/useTheme';
import { pmbTheme } from './theme/pmbTheme';

const appName = import.meta.env.VITE_APP_NAME || 'Plant Budget Tracker';

function ThemedApp({ children }: { children: ReactNode }) {
    const { isDark } = useTheme();

    return (
        <ConfigProvider
            locale={enUS}
            theme={pmbTheme(isDark)}
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
        color: '#4f92ff',
    },
});
