import '../css/app.css';

import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';

const appName = import.meta.env.VITE_APP_NAME || 'Absensi SMP';

createInertiaApp({
    title: (title) => (title ? `${title} · ${appName}` : appName),
    // Tiap halaman dimuat terpisah, jadi HP guru tidak ikut mengunduh halaman admin.
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.jsx');

        return pages[`./Pages/${name}.jsx`]();
    },
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
    },
    progress: {
        color: '#2360e8',
    },
});
