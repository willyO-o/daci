import { onMounted, onBeforeUnmount, reactive, ref } from 'vue';

const CLAVE = 'felcc.tema';
const ANCHO_ESCRITORIO = 1025;

/**
 * Atributos del `<html>` que la plantilla Velzon lee para pintar el layout, con el valor por defecto del sistema
 * anterior (`layouts/app.blade.php`). Son los mismos que cambiaba su «Theme Customizer».
 */
export const AJUSTES_POR_DEFECTO = Object.freeze({
    'data-layout-mode': 'light',
    'data-layout-width': 'fluid',
    'data-layout-position': 'fixed',
    'data-topbar': 'light',
    'data-sidebar-size': 'lg',
    'data-layout-style': 'default',
    'data-sidebar': 'dark',
    'data-sidebar-image': 'none',
    'data-preloader': 'disable',
});

/**
 * Lo guardado en el navegador. Versiones anteriores guardaban sólo `{ modo, sidebar }`: se leen igual.
 * La misma lectura la repite el script de `app.blade.php` para pintar el tema antes de que cargue Vue.
 */
const leer = () => {
    try {
        const tema = JSON.parse(localStorage.getItem(CLAVE) || '{}');
        const atributos = { ...(tema.atributos ?? {}) };
        if (tema.modo && !atributos['data-layout-mode']) {
            atributos['data-layout-mode'] = tema.modo;
        }
        if (tema.sidebar && !atributos['data-sidebar-size']) {
            atributos['data-sidebar-size'] = tema.sidebar;
        }

        return atributos;
    } catch {
        return {};
    }
};

const guardar = (atributos) => {
    try {
        localStorage.setItem(CLAVE, JSON.stringify({ atributos }));
    } catch {
        // Navegación privada o almacenamiento bloqueado: el tema funciona igual, sólo no se recuerda.
    }
};

const html = document.documentElement;

/** Ajustes elegidos (compartidos por todo el layout: barra superior, menú y panel de personalización). */
const ajustes = reactive({ ...AJUSTES_POR_DEFECTO, ...leer() });
const modoOscuro = ref(ajustes['data-layout-mode'] === 'dark');
const menuAbiertoMovil = ref(false);

const esEscritorio = () => window.innerWidth >= ANCHO_ESCRITORIO;

const pintar = (atributo, valor) => {
    // El tamaño del menú elegido rige en el escritorio; en tabletas y móviles manda el ancho de la pantalla.
    if (atributo === 'data-sidebar-size' && !esEscritorio()) {
        return;
    }
    html.setAttribute(atributo, valor);
    if (atributo === 'data-layout-mode') {
        html.setAttribute('data-bs-theme', valor);
        modoOscuro.value = valor === 'dark';
    }
};

/**
 * Comportamiento del layout de Velzon (lo que hacía `assets/js/app.js` de la plantilla) reescrito para Vue:
 * colapsar el menú lateral, abrirlo en el móvil, modo oscuro, pantalla completa y los ajustes del panel de
 * personalización. Se guarda por navegador.
 */
export function useTema() {
    /** Cambia un ajuste del panel de personalización y lo recuerda. */
    const ajustar = (atributo, valor) => {
        ajustes[atributo] = valor;
        pintar(atributo, valor);
        guardar({ ...ajustes });
    };

    /** «Restablecer» del panel: vuelve al aspecto por defecto del sistema. */
    const restablecer = () => {
        Object.entries(AJUSTES_POR_DEFECTO).forEach(([atributo, valor]) => {
            ajustes[atributo] = valor;
            pintar(atributo, valor);
        });
        try {
            localStorage.removeItem(CLAVE);
        } catch {
            // Sin almacenamiento no hay nada que borrar.
        }
    };

    const alternarMenu = () => {
        if (esEscritorio()) {
            const actual = html.getAttribute('data-sidebar-size');

            // «Small Hover View»: el botón fija o suelta el menú abierto sin cambiar la preferencia guardada.
            if (actual === 'sm-hover' || actual === 'sm-hover-active') {
                html.setAttribute('data-sidebar-size', actual === 'sm-hover' ? 'sm-hover-active' : 'sm-hover');

                return;
            }

            ajustar('data-sidebar-size', actual === 'sm' ? 'lg' : 'sm');

            return;
        }

        menuAbiertoMovil.value = !menuAbiertoMovil.value;
        document.body.classList.toggle('vertical-sidebar-enable', menuAbiertoMovil.value);
    };

    const cerrarMenuMovil = () => {
        menuAbiertoMovil.value = false;
        document.body.classList.remove('vertical-sidebar-enable');
    };

    const alternarModo = () => ajustar('data-layout-mode', modoOscuro.value ? 'light' : 'dark');

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
        } else if (esEscritorio()) {
            html.setAttribute('data-sidebar-size', ajustes['data-sidebar-size']);
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
        ajustes,
        modoOscuro,
        menuAbiertoMovil,
        ajustar,
        restablecer,
        alternarMenu,
        cerrarMenuMovil,
        alternarModo,
        alternarPantallaCompleta,
        observarAncho,
    };
}
