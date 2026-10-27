<script setup>
import { onBeforeUnmount, ref, watch } from 'vue';
import { ESPERA_BUSQUEDA } from '@/Utils/espera';
import { http } from '@/Utils/http';

/**
 * Al escribir C.I. y complemento en un alta (Registro Criminal, Faltas y Contravenciones), busca en la base si la
 * persona ya existe (`personas.check-ci`, que compara con COALESCE) y avisa antes de guardar. «Usar esta persona»
 * emite `usar` con sus datos para completar el formulario.
 */
const props = defineProps({
    ci: { type: String, default: '' },
    complemento: { type: String, default: '' },
    /** Buscar sólo mientras se registra y no hay una persona elegida. */
    activo: { type: Boolean, default: true },
    /** Módulo cuyos registros se cuentan (`registro_criminal`, `faltas_contravenciones`). */
    contar: { type: String, default: null },
    /** Texto del conteo, p. ej. «registro(s) criminal(es)». */
    etiquetaRegistros: { type: String, default: 'registro(s)' },
});

const emit = defineEmits(['usar']);

const MINIMO = 4;

const estado = ref('inactivo'); // inactivo | buscando | encontrada | libre | error
const persona = ref(null);
let temporizador = null;
let controlador = null;

const limpiar = () => {
    clearTimeout(temporizador);
    controlador?.abort();
    estado.value = 'inactivo';
    persona.value = null;
};

const buscar = async (ci, complemento) => {
    controlador?.abort();
    controlador = new AbortController();
    estado.value = 'buscando';
    try {
        const { data } = await http.get(route('personas.check-ci'), {
            params: { ci, complemento: complemento || undefined, contar: props.contar ?? undefined },
            signal: controlador.signal,
        });
        persona.value = data.data;
        estado.value = data.data ? 'encontrada' : 'libre';
    } catch (err) {
        if (err?.code === 'ERR_CANCELED' || err?.name === 'CanceledError') {
            return;
        }
        estado.value = 'error';
    }
};

watch(
    () => [props.ci?.trim() ?? '', props.complemento?.trim() ?? '', props.activo],
    ([ci, complemento, activo]) => {
        limpiar();
        if (!activo || ci.length < MINIMO) {
            return;
        }
        estado.value = 'buscando';
        temporizador = setTimeout(() => buscar(ci, complemento), ESPERA_BUSQUEDA);
    },
    { immediate: true },
);

onBeforeUnmount(limpiar);

const ciCompleto = (p) => `${p.ci}${p.complemento ? `-${p.complemento}` : ''}`;
</script>

<template>
    <div v-if="estado !== 'inactivo'" aria-live="polite">
        <p v-if="estado === 'buscando'" class="text-muted small mb-0">
            <span class="spinner-border spinner-border-sm me-1" role="status" /> Buscando el C.I. en la base de datos…
        </p>

        <div v-else-if="estado === 'encontrada'" class="alert alert-warning mb-0 py-2">
            <div class="d-flex flex-wrap align-items-center gap-2">
                <i class="ri-user-search-line fs-4" />
                <div class="flex-grow-1 small">
                    Ya existe una persona con C.I. <strong class="cifra">{{ ciCompleto(persona) }}</strong>:
                    <strong>{{ persona.nombres }} {{ persona.apellidos }}</strong>.
                    <template v-if="persona.registros !== null && persona.registros !== undefined">
                        Tiene <strong class="cifra">{{ persona.registros }}</strong> {{ etiquetaRegistros }}.
                    </template>
                    <span class="d-block text-muted">Si guarda sin elegirla, el registro se asociará igual a esta persona y no se crea otra.</span>
                </div>
                <button type="button" class="btn btn-sm btn-warning" @click="emit('usar', persona)">
                    <i class="ri-user-follow-line align-bottom me-1" /> Usar esta persona
                </button>
            </div>
        </div>

        <p v-else-if="estado === 'libre'" class="text-success small mb-0">
            <i class="ri-checkbox-circle-line align-bottom me-1" /> No hay ninguna persona con este C.I. y complemento: se registrará una nueva.
        </p>

        <p v-else class="text-danger small mb-0">
            <i class="ri-error-warning-line align-bottom me-1" /> No se pudo verificar el C.I. Puede continuar; al guardar se vuelve a buscar.
        </p>
    </div>
</template>
