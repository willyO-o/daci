import '../css/app.css';
import 'bootstrap';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import AppLayout from './Layouts/AppLayout.vue';
import AuthLayout from './Layouts/AuthLayout.vue';
import Directivas from './Utils/directivas';
import { ocultarPreloader } from './Utils/preloader';

const appName = import.meta.env.VITE_APP_NAME || 'FELCC DACI';

createInertiaApp({
    title: (title) => (title ? `${title} · ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')).then((module) => {
            // Layout PERSISTENTE según el árbol de páginas: el menú lateral y la barra superior no se vuelven a
            // montar al navegar. Una página puede declarar otro con `defineOptions({ layout })`; `layout: null`
            // significa "sin layout" (por eso se comprueba con `in`).
            if (!('layout' in module.default)) {
                module.default.layout = name.startsWith('Auth/') ? AuthLayout : AppLayout;
            }

            return module;
        }),
    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .use(Directivas)
            .mount(el);

        // Como la plantilla: la pantalla de carga se retira cuando el sistema ya está listo.
        ocultarPreloader();

        return app;
    },
    progress: {
        color: '#0ab39c',
        showSpinner: false,
    },
});
