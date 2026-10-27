import { reactive, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { ESPERA_BUSQUEDA } from '@/Utils/espera';

/**
 * Filtros de un listado paginado en el SERVIDOR, sincronizados con la URL (se pueden recargar y compartir).
 * Reemplaza el `cargarX(page)` + `$.ajax` de cada pantalla del sistema anterior.
 *
 *   const { filtros, cargando, irAPagina, recargar } = useListado('personas.index', props.filtros, ['personas']);
 *
 * - Escribir en un campo de texto espera ESPERA_BUSQUEDA (900 ms) desde la última tecla y vuelve a la página 1.
 * - Cambiar un select filtra al instante.
 * - Sólo se piden de nuevo los props indicados en `soloProps`; el resto de la página no se recarga.
 *
 * @param {string} nombreRuta ruta Ziggy del listado
 * @param {Record<string, any>} iniciales filtros que devolvió el servidor
 * @param {string[]} soloProps props a recargar
 * @param {{ camposTexto?: string[], espera?: number }} opciones
 */
export function useListado(nombreRuta, iniciales = {}, soloProps = [], { camposTexto = ['search'], espera = ESPERA_BUSQUEDA } = {}) {
    const filtros = reactive({ ...iniciales });
    const cargando = ref(false);
    let temporizador = null;

    const limpios = () =>
        Object.fromEntries(Object.entries(filtros).filter(([, valor]) => valor !== '' && valor !== null && valor !== undefined));

    const visitar = (extra = {}) => {
        router.get(route(nombreRuta), { ...limpios(), ...extra }, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: soloProps.length ? [...soloProps, 'filtros'] : undefined,
            onStart: () => (cargando.value = true),
            onFinish: () => (cargando.value = false),
        });
    };

    const irAPagina = (pagina) => {
        filtros.page = pagina;
        visitar();
    };

    const recargar = () => visitar();

    let ultimo = JSON.stringify({ ...filtros, page: undefined });

    watch(filtros, () => {
        const actual = JSON.stringify({ ...filtros, page: undefined });
        if (actual === ultimo) {
            return;
        }

        const cambioTexto = camposTexto.some((campo) => JSON.parse(ultimo)[campo] !== filtros[campo]);
        ultimo = actual;
        filtros.page = 1;
        clearTimeout(temporizador);

        if (cambioTexto) {
            temporizador = setTimeout(visitar, espera);
        } else {
            visitar();
        }
    });

    return { filtros, cargando, irAPagina, recargar };
}
