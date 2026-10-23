import { onBeforeUnmount, ref, shallowRef } from 'vue';
import { esCancelacion, http } from '@/Utils/http';

/**
 * Listado que se va cargando al desplazarse (el `scrollPagination` del sistema anterior). El servidor responde
 * `{ datos, total, page, last_page }`.
 *
 *   const lista = useCargaInfinita(() => route('mandamientos.index'), () => ({ ...filtros }));
 *   lista.reiniciar();                       // primera página con los filtros actuales
 *   <div ref="centinela" />  + lista.observar(centinela, contenedor)
 *
 * @param {() => string} url
 * @param {() => Record<string, any>} parametros
 * @param {{ tamano?: number }} opciones
 */
export function useCargaInfinita(url, parametros, { tamano = 6 } = {}) {
    const elementos = ref([]);
    const total = ref(0);
    const pagina = ref(0);
    const ultimaPagina = ref(1);
    const cargando = ref(false);
    const error = ref(null);
    const cargado = ref(false);
    const observador = shallowRef(null);
    let controlador = null;
    let extra = {};

    const hayMas = () => pagina.value < ultimaPagina.value;

    const pedir = async (numero) => {
        controlador?.abort();
        controlador = new AbortController();
        cargando.value = true;
        error.value = null;

        try {
            const { data } = await http.get(url(), {
                params: { ...parametros(), ...extra, page: numero, size: tamano },
                signal: controlador.signal,
            });
            const nuevos = data.datos ?? [];
            elementos.value = numero === 1 ? nuevos : [...elementos.value, ...nuevos];
            total.value = data.total ?? nuevos.length;
            pagina.value = data.page ?? numero;
            ultimaPagina.value = data.last_page ?? (nuevos.length < tamano ? numero : numero + 1);
            cargado.value = true;
        } catch (err) {
            if (!esCancelacion(err)) {
                error.value = err;
            }
        } finally {
            cargando.value = false;
        }
    };

    /** Vuelve a la primera página. `extraUnaVez` viaja sólo en esta petición (p. ej. `nuevo_filtro`). */
    const reiniciar = (extraUnaVez = {}) => {
        extra = extraUnaVez;
        pagina.value = 0;
        ultimaPagina.value = 1;

        return pedir(1).finally(() => (extra = {}));
    };

    const cargarMas = () => {
        if (!cargando.value && hayMas()) {
            return pedir(pagina.value + 1);
        }

        return Promise.resolve();
    };

    /** Carga la siguiente página cuando el centinela entra en el área visible del contenedor. */
    const observar = (centinela, contenedor = null) => {
        observador.value?.disconnect();
        if (!centinela) {
            return;
        }
        observador.value = new IntersectionObserver(
            (entradas) => entradas.some((entrada) => entrada.isIntersecting) && cargarMas(),
            { root: contenedor, rootMargin: '200px' },
        );
        observador.value.observe(centinela);
    };

    /** Reemplaza o agrega un elemento sin recargar (tras guardar). */
    const actualizar = (elemento) => {
        const indice = elementos.value.findIndex((actual) => actual.id === elemento.id);
        if (indice === -1) {
            elementos.value = [elemento, ...elementos.value];
            total.value += 1;
        } else {
            elementos.value.splice(indice, 1, elemento);
        }
    };

    const quitar = (id) => {
        elementos.value = elementos.value.filter((actual) => actual.id !== id);
        total.value = Math.max(0, total.value - 1);
    };

    const vaciar = () => {
        controlador?.abort();
        elementos.value = [];
        total.value = 0;
        pagina.value = 0;
        ultimaPagina.value = 1;
        cargado.value = false;
    };

    onBeforeUnmount(() => {
        controlador?.abort();
        observador.value?.disconnect();
    });

    return { elementos, total, cargando, error, cargado, hayMas, reiniciar, cargarMas, observar, actualizar, quitar, vaciar };
}
