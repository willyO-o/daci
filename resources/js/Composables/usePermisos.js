import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

/**
 * Permisos del usuario para mostrar u ocultar menús y botones (`auth.can`, slug => true, compartido por
 * `HandleInertiaRequests`; el superadmin los trae todos). Es sólo presentación: la autorización real la hace el
 * servidor en cada ruta (`->middleware('permission:...')`), así que ocultar un botón nunca es la única barrera.
 *
 *   const { puede, puedeAlguno } = usePermisos();
 *   <button v-if="puede('personas.crear')">Nueva persona</button>
 */
export function usePermisos() {
    const page = usePage();
    const permisos = computed(() => page.props?.auth?.can ?? {});

    /** @param {string} permiso p. ej. `personas.crear` */
    const puede = (permiso) => Boolean(permisos.value?.[permiso]);

    /** Al menos uno. @param {string[]} lista */
    const puedeAlguno = (lista) => lista.some(puede);

    /** Todos. @param {string[]} lista */
    const puedeTodos = (lista) => lista.every(puede);

    /**
     * Regla de visibilidad de un ítem del menú: string = ese permiso; array = cualquiera de ellos;
     * función = recibe `puede` y decide (casos como «registrar sin listar»).
     */
    const cumple = (regla) => {
        if (!regla) {
            return true;
        }
        if (typeof regla === 'function') {
            return Boolean(regla(puede));
        }

        return Array.isArray(regla) ? puedeAlguno(regla) : puede(regla);
    };

    return { puede, puedeAlguno, puedeTodos, cumple };
}
