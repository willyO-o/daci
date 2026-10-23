import { reactive, ref } from 'vue';
import { http } from '@/Utils/http';

/**
 * Formulario que se envía por JSON (multipart si lleva archivos) y recibe la respuesta del servidor sin navegar,
 * para los módulos que actualizan la lista en el lugar (mandamientos, teléfonos, vehículos…). Mismo contrato que
 * el sistema anterior: 200 `{ success, datos }`, 422 `{ errors }`, 500 `{ error }`.
 *
 *   const form = useFormularioJson({ hoja_ruta: '', imagen: null });
 *   const { data } = await form.enviar(route('mandamientos.store'));
 *   await form.enviar(route('mandamientos.update', id), 'put');   // viaja como POST + _method
 */
export function useFormularioJson(valoresIniciales) {
    const datos = reactive({ ...valoresIniciales });
    const errores = ref({});
    const procesando = ref(false);
    let base = { ...valoresIniciales };

    const aFormData = (valores, metodo) => {
        const formulario = new FormData();
        const agregar = (clave, valor) => {
            if (valor === null || valor === undefined) {
                return;
            }
            if (valor instanceof File || valor instanceof Blob) {
                formulario.append(clave, valor);
            } else if (Array.isArray(valor)) {
                valor.forEach((item, indice) => agregar(`${clave}[${item instanceof File ? '' : indice}]`, item));
            } else if (typeof valor === 'boolean') {
                formulario.append(clave, valor ? '1' : '0');
            } else if (typeof valor === 'object') {
                formulario.append(clave, JSON.stringify(valor));
            } else {
                formulario.append(clave, valor);
            }
        };

        Object.entries(valores).forEach(([clave, valor]) => agregar(clave, valor));
        if (metodo !== 'post') {
            formulario.append('_method', metodo.toUpperCase());
        }

        return formulario;
    };

    /**
     * @param {string} url
     * @param {'post'|'put'|'patch'} metodo
     * @param {(valores: object) => object} transformar
     */
    const enviar = async (url, metodo = 'post', transformar = (valores) => valores) => {
        procesando.value = true;
        errores.value = {};
        try {
            return await http.post(url, aFormData(transformar({ ...datos }), metodo));
        } catch (err) {
            if (err.response?.status === 422) {
                errores.value = Object.fromEntries(
                    Object.entries(err.response.data.errors ?? {}).map(([campo, mensajes]) => [campo, mensajes[0]]),
                );
            }
            throw err;
        } finally {
            procesando.value = false;
        }
    };

    /** Fija los valores de partida (al abrir para editar) y los aplica. */
    const establecer = (valores) => {
        base = { ...valoresIniciales, ...valores };
        Object.assign(datos, base);
        errores.value = {};
    };

    const reiniciar = () => {
        Object.assign(datos, { ...valoresIniciales });
        base = { ...valoresIniciales };
        errores.value = {};
    };

    return { datos, errores, procesando, enviar, establecer, reiniciar };
}
