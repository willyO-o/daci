import { onMounted, onBeforeUnmount, ref } from 'vue';

const CLAVE = 'felcc.tema';
const ANCHO_ESCRITORIO = 1025;

const leer = () => {
    try {
        return JSON.parse(localStorage.getItem(CLAVE) || '{}');
    } catch {
        return {};
    }
};

const guardar = (cambios) => {
    try {
        localStorage.setItem(CLAVE, JSON.stringify({ ...leer(), ...cambios }));
    } catch {
        // Navegación privada o almacenamiento bloqueado: el tema funciona igual, sólo no se recuerda.
    }
};

const modoOscuro = ref(document.documentElement.getAttribute('data-layout-mode') === 'dark');
const menuAbiertoMovil = ref(false);

/**
 * Comportamiento del layout de Velzon (lo que hacía `assets/js/app.js` de la plantilla) reescrito para Vue:
 * colapsar el menú lateral, abrirlo en el móvil, modo oscuro y pantalla completa. Se guarda por navegador.
 */
export function useTema() {
    const html = document.documentElement;

    const esEscritorio = () => window.innerWidth >= ANCHO_ESCRITORIO;

    const alternarMenu = () => {
        if (esEscritorio()) {
            const compacto = html.getAttribute('data-sidebar-size') === 'sm';
            html.setAttribute('data-sidebar-size', compacto ? 'lg' : 'sm');
            guardar({ sidebar: compacto ? 'lg' : 'sm' });

            return;
        }

        menuAbiertoMovil.value = !menuAbiertoMovil.value;
        document.body.classList.toggle('vertical-sidebar-enable', menuAbiertoMovil.value);
    };

    const cerrarMenuMovil = () => {
        menuAbiertoMovil.value = false;
        document.body.classList.remove('vertical-sidebar-enable');
    };

    const alternarModo = () => {
        modoOscuro.value = !modoOscuro.value;
        const modo = modoOscuro.value ? 'dark' : 'light';
        html.setAttribute('data-layout-mode', modo);
        html.setAttribute('data-bs-theme', modo);
        guardar({ modo });
    };

    const alternarPantallaCompleta = () => {
        if (!document.fullscreenElement) {
            document.documentElement.requestFullscreen?.();
        } else {
            document.exitFullscreen?.();
        }
    };

    // En tabletas el menú usa el modo compacto de la plantilla; en el móvil se oculta tras el botón.
    const ajustarAlAncho = () => {
        if (window.innerWidth < ANCHO_ESCRITORIO && window.innerWidth >= 768) {
            html.setAttribute('data-sidebar-size', 'sm');
        } else if (window.innerWidth >= ANCHO_ESCRITORIO) {
            html.setAttribute('data-sidebar-size', leer().sidebar === 'sm' ? 'sm' : 'lg');
            cerrarMenuMovil();
        } else {
            html.setAttribute('data-sidebar-size', 'lg');
        }
    };

    /** Lo llama sólo AppLayout: ajusta el menú al ancho de la pantalla y escucha los cambios de tamaño. */
    const observarAncho = () => {
        onMounted(() => {
            ajustarAlAncho();
            window.addEventListener('resize', ajustarAlAncho);
        });
        onBeforeUnmount(() => window.removeEventListener('resize', ajustarAlAncho));
    };

    return {
        modoOscuro,
        menuAbiertoMovil,
        alternarMenu,
        cerrarMenuMovil,
        alternarModo,
        alternarPantallaCompleta,
        observarAncho,
    };
}
