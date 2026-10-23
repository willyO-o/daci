import { ref, watch } from 'vue';

/**
 * Preferencia de vista (cuadrícula / lista) recordada en el navegador, como `mandamientosViewMode` del sistema
 * anterior. Si el almacenamiento no está disponible, funciona igual sin recordarla.
 */
export function useVistaGuardada(clave, porDefecto = 'grid') {
    let inicial = porDefecto;
    try {
        inicial = localStorage.getItem(clave) || porDefecto;
    } catch {
        // Navegación privada o almacenamiento bloqueado.
    }

    const vista = ref(inicial);

    watch(vista, (valor) => {
        try {
            localStorage.setItem(clave, valor);
        } catch {
            // Sin almacenamiento: la vista vale sólo para esta visita.
        }
    });

    return vista;
}
