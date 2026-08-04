import '../css/app.css';
import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ConfigProvider, App as AntApp } from 'antd';
import idID from 'antd/locale/id_ID';

const appName = import.meta.env.VITE_APP_NAME || 'Plant Budget Tracker';

createInertiaApp({
    title: (title) => (title ? `${title} — ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(`./Pages/${name}.tsx`, import.meta.glob('./Pages/**/*.tsx')),
    setup({ el, App, props }) {
        createRoot(el).render(
            <ConfigProvider
                locale={idID}
                theme={{
                    token: {
                        colorPrimary: '#1677ff',
                        borderRadius: 6,
                    },
                }}
            >
                <AntApp>
                    <App {...props} />
                </AntApp>
            </ConfigProvider>
        );
    },
    progress: {
        color: '#1677ff',
    },
});
