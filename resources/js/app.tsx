import './bootstrap';
import '../css/app.css';

import { createRoot } from 'react-dom/client';
import { createInertiaApp, router } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { setCurrency, type CurrencyInfo } from './utils/format';
import { setLocale } from './i18n';

const appName = import.meta.env.VITE_APP_NAME || 'AffiliateOS';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.tsx`,
            import.meta.glob('./Pages/**/*.tsx'),
        ),
    setup({ el, App, props }) {
        // Store currency comes from the server's shared props (HandleInertiaRequests).
        setCurrency(props.initialPage.props.currency as CurrencyInfo | undefined);
        setLocale(props.initialPage.props.locale as string | undefined);
        router.on('navigate', (event) => {
            setCurrency(event.detail.page.props.currency as CurrencyInfo | undefined);
            setLocale(event.detail.page.props.locale as string | undefined);
        });

        const root = createRoot(el);
        root.render(<App {...props} />);
    },
    progress: {
        color: '#4F46E5',
    },
});
