import { onBeforeUnmount } from 'vue';

/**
 * Guía paso a paso de una pantalla con driver.js (versión fija en package.json). Sólo se abre cuando el usuario la
 * pide (ícono de ayuda); la librería y su CSS se cargan recién en ese momento.
 *
 *   const { iniciar } = useTour();
 *   iniciar([
 *       { titulo: 'Bienvenido', texto: '…' },                                   // sin elemento: ventana centrada
 *       { elemento: '[data-tour="buscar"]', titulo: 'Buscar', texto: '…', lado: 'bottom' },
 *       { elemento: '[data-tour="estado"]', titulo: '…', texto: '…', siNoExiste: { titulo, texto } },
 *   ]);
 *
 * Un paso cuyo elemento no existe o no se ve en ese momento (p. ej. botones que dependen de un permiso, o resultados
 * antes de buscar) se salta; si trae `siNoExiste`, se muestra ese texto centrado en su lugar. Se cierra solo al salir
 * de la pantalla.
 */
export function useTour() {
    let guia = null;

    const visible = (selector) => {
        const elemento = document.querySelector(selector);

        return elemento && elemento.getClientRects().length > 0 ? elemento : null;
    };

    const aPasoDriver = (paso) => {
        if (!paso.elemento) {
            return { popover: { title: paso.titulo, description: paso.texto } };
        }
        const elemento = visible(paso.elemento);
        if (elemento) {
            return { element: elemento, popover: { title: paso.titulo, description: paso.texto, side: paso.lado ?? 'bottom', align: paso.alineacion ?? 'start' } };
        }

        return paso.siNoExiste ? { popover: { title: paso.siNoExiste.titulo, description: paso.siNoExiste.texto } } : null;
    };

    /**
     * @param {Array<{ elemento?: string, titulo: string, texto: string, lado?: string, alineacion?: string, siNoExiste?: { titulo: string, texto: string } }>} pasos
     * @param {{ alCerrar?: () => void }} opciones `alCerrar` corre cuando la guía se cierra por cualquier motivo
     */
    const iniciar = async (pasos, { alCerrar } = {}) => {
        const [{ driver }] = await Promise.all([import('driver.js'), import('driver.js/dist/driver.css')]);

        guia?.destroy();
        guia = driver({
            steps: pasos.map(aPasoDriver).filter(Boolean),
            showProgress: true,
            progressText: 'Paso {{current}} de {{total}}',
            nextBtnText: 'Siguiente',
            prevBtnText: 'Anterior',
            doneBtnText: 'Finalizar',
            popoverClass: 'guia-felcc',
            allowClose: true,
            allowKeyboardControl: true,
            smoothScroll: true,
            stagePadding: 6,
            stageRadius: 6,
            overlayOpacity: 0.55,
            onDestroyed: () => {
                guia = null;
                alCerrar?.();
            },
        });
        guia.drive();
    };

    /** Cierra la guía si está abierta (p. ej. al cerrar el modal que la contiene). */
    const cerrar = () => guia?.destroy();

    onBeforeUnmount(cerrar);

    return { iniciar, cerrar };
}
