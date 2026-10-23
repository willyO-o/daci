import Swal from 'sweetalert2';

const COLOR_CONFIRMAR = '#0ab39c';
const COLOR_PELIGRO = '#f06548';
const COLOR_CANCELAR = '#878a99';

const toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true,
});

/**
 * Mensajes y confirmaciones con SweetAlert2 (los mismos que usaba el sistema anterior: `notification`,
 * `confirmarEnvio`, `processError`).
 */
export function useNotificacion() {
    const exito = (mensaje, titulo = '¡Éxito!') => toast.fire({ icon: 'success', title: titulo, text: mensaje });

    const aviso = (mensaje, titulo = 'Atención') => toast.fire({ icon: 'warning', title: titulo, text: mensaje });

    const error = (mensaje, titulo = 'Error') =>
        Swal.fire({ icon: 'error', title: titulo, text: mensaje, confirmButtonColor: COLOR_CONFIRMAR });

    /**
     * @param {{ titulo?: string, texto?: string, confirmar?: string, cancelar?: string, icono?: string, peligro?: boolean }} opciones
     * @returns {Promise<boolean>}
     */
    const confirmar = async ({
        titulo = '¿Está seguro?',
        texto = '',
        confirmar: textoConfirmar = 'Sí, continuar',
        cancelar = 'Cancelar',
        icono = 'warning',
        peligro = false,
    } = {}) => {
        const resultado = await Swal.fire({
            title: titulo,
            text: texto,
            icon: icono,
            showCancelButton: true,
            confirmButtonText: textoConfirmar,
            cancelButtonText: cancelar,
            confirmButtonColor: peligro ? COLOR_PELIGRO : COLOR_CONFIRMAR,
            cancelButtonColor: COLOR_CANCELAR,
            reverseButtons: true,
            focusCancel: peligro,
        });

        return resultado.isConfirmed;
    };

    /** Mensaje legible de un error de axios (422 con errores de validación, 403, 500 con `error`/`message`). */
    const mensajeDeError = (err) => {
        const respuesta = err?.response;
        const datos = respuesta?.data ?? {};

        if (respuesta?.status === 422 && datos.errors) {
            return Object.values(datos.errors).flat()[0] ?? 'Revise los datos ingresados.';
        }
        if (respuesta?.status === 403) {
            return datos.message || 'No tiene permiso para realizar esta acción.';
        }
        if (respuesta?.status === 419) {
            return 'La sesión expiró. Recargue la página e intente de nuevo.';
        }

        return datos.error || datos.message || 'Ocurrió un error inesperado. Intente nuevamente.';
    };

    const errorDeRespuesta = (err) => error(mensajeDeError(err));

    return { exito, aviso, error, confirmar, mensajeDeError, errorDeRespuesta };
}
