<script setup>
import { computed, ref, watch } from 'vue';
import BaseModal from '@/Components/Ui/BaseModal.vue';
import CampoError from '@/Components/Ui/CampoError.vue';
import { useFormularioJson } from '@/Composables/useFormularioJson';
import { useNotificacion } from '@/Composables/useNotificacion';
import { colorEstado, esEjecutado } from '@/Utils/mandamiento';
import { fechaInput, guion, hoyIso, urlArchivo } from '@/Utils/formato';

/**
 * Cambio del estado de ejecución desde Consultas › Mandamientos (permiso `mandamientos.estado`). Si el estado
 * contiene «EJECUTADO» pide fecha, quién lo ejecutó, detalle y acta, como el formulario de edición; con otro estado
 * sólo envía el estado y el servidor conserva los demás datos de ejecución. Emite `guardado` con la tarjeta nueva.
 */
const props = defineProps({
    modelValue: { type: Boolean, default: false },
    /** Tarjeta del mandamiento (la misma del listado). */
    mandamiento: { type: Object, default: null },
    estados: { type: Array, default: () => [] },
});

const emit = defineEmits(['update:modelValue', 'guardado']);

const { exito, error, errorDeRespuesta } = useNotificacion();

const VACIO = { estado: '', fecha_ejecucion: '', ejecutado_por: '', detalle_ejecucion: '', acta_ejecucion: null };
const form = useFormularioJson(VACIO);
const entradaActa = ref(null);
const hoy = hoyIso();

const abierto = computed({
    get: () => props.modelValue,
    set: (valor) => emit('update:modelValue', valor),
});

const ejecutado = computed(() => esEjecutado(form.datos.estado));
const actaActual = computed(() => props.mandamiento?.acta_ejecucion ?? null);
const actaEsPdf = computed(() => (actaActual.value?.tipo_archivo ?? '').toLowerCase() === 'pdf');
const tieneDatosEjecucion = computed(() => Boolean(props.mandamiento?.fecha_ejecucion || props.mandamiento?.ejecutado_por || actaActual.value));
const badge = (estado) => `bg-soft-${colorEstado(estado)} text-${colorEstado(estado)}`;

watch(
    () => [props.modelValue, props.mandamiento],
    ([visible, m]) => {
        if (!visible || !m) {
            return;
        }
        form.establecer({
            estado: m.estado ?? '',
            fecha_ejecucion: fechaInput(m.fecha_ejecucion),
            ejecutado_por: m.ejecutado_por ?? '',
            detalle_ejecucion: m.detalle_ejecucion ?? '',
            acta_ejecucion: null,
        });
        if (entradaActa.value) {
            entradaActa.value.value = '';
        }
    },
    { immediate: true },
);

const elegirActa = (evento) => {
    form.datos.acta_ejecucion = evento.target.files?.[0] ?? null;
};

const guardar = async () => {
    if (!form.datos.estado?.trim()) {
        form.errores.value = { estado: 'Ingrese el estado.' };

        return;
    }

    if (ejecutado.value) {
        const faltantes = {};
        if (!form.datos.fecha_ejecucion) {
            faltantes.fecha_ejecucion = 'Indique la fecha de ejecución.';
        }
        if (!form.datos.ejecutado_por?.trim()) {
            faltantes.ejecutado_por = 'Indique quién ejecutó el mandamiento.';
        }
        if (!form.datos.acta_ejecucion && !actaActual.value) {
            faltantes.acta_ejecucion = 'Suba el acta de ejecución.';
        }
        if (Object.keys(faltantes).length) {
            form.errores.value = faltantes;

            return;
        }
    }

    try {
        const { data } = await form.enviar(
            route('consultas.mandamientos.estado', props.mandamiento.id),
            'patch',
            // Con otro estado sólo viaja el estado: lo demás queda como está en la base.
            (valores) => (ejecutado.value ? valores : { estado: valores.estado }),
        );
        exito(data.success, 'Estado Actualizado');
        emit('guardado', data.datos);
        abierto.value = false;
    } catch (err) {
        const estado = err.response?.status;
        if (estado === 422) {
            return;
        }
        if (estado === 413) {
            error('El acta es demasiado pesada. Reduzca su tamaño e intente nuevamente.');
        } else if (!err.response) {
            error('No se pudo conectar con el servidor. Verifique su conexión e intente nuevamente.');
        } else {
            errorDeRespuesta(err);
        }
    }
};
</script>

<template>
    <BaseModal v-model="abierto" titulo="Estado de Ejecución" tamano="lg" centrado estatico>
        <form v-if="mandamiento" id="formEstadoMandamiento" novalidate @submit.prevent="guardar">
            <div class="bg-light rounded p-3 mb-3">
                <div class="d-flex flex-wrap justify-content-between gap-2">
                    <div>
                        <h6 class="mb-1">{{ mandamiento.nombre_completo }}</h6>
                        <p class="text-muted mb-0 small">
                            HR: <span class="cifra">{{ guion(mandamiento.hoja_ruta) }}</span> ·
                            C.I.: <span class="cifra">{{ guion(mandamiento.ci) }}</span> ·
                            {{ guion(mandamiento.tipo_mandamiento) }}
                        </p>
                    </div>
                    <div class="text-sm-end small">
                        <span class="text-muted d-block">Estado actual</span>
                        <span class="badge" :class="badge(mandamiento.estado)">{{ guion(mandamiento.estado) }}</span>
                    </div>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-12">
                    <label for="estado_ejecucion" class="form-label">Estado <span class="text-danger">*</span></label>
                    <input
                        id="estado_ejecucion"
                        v-model="form.datos.estado"
                        v-mayuscula
                        list="estadosConsulta"
                        type="text"
                        maxlength="200"
                        class="form-control"
                        :class="{ 'is-invalid': form.errores.value.estado }"
                        placeholder="Ingrese el estado (p. ej. POR EJECUTAR, EJECUTADO…)"
                        autocomplete="off"
                    >
                    <datalist id="estadosConsulta">
                        <option v-for="estado in estados" :key="estado" :value="estado" />
                    </datalist>
                    <CampoError :mensaje="form.errores.value.estado" />
                    <div class="form-text">Al escribir <b>EJECUTADO</b> se piden los datos de la ejecución.</div>
                </div>

                <template v-if="ejecutado">
                    <div class="col-md-6">
                        <label for="fecha_ejecucion_estado" class="form-label">Fecha de Ejecución <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input id="fecha_ejecucion_estado" v-model="form.datos.fecha_ejecucion" type="date" class="form-control" :class="{ 'is-invalid': form.errores.value.fecha_ejecucion }" :max="hoy">
                            <button type="button" class="btn btn-soft-secondary" @click="form.datos.fecha_ejecucion = hoy">Hoy</button>
                        </div>
                        <CampoError :mensaje="form.errores.value.fecha_ejecucion" />
                    </div>
                    <div class="col-md-6">
                        <label for="ejecutado_por_estado" class="form-label">Ejecutado Por <span class="text-danger">*</span></label>
                        <input id="ejecutado_por_estado" v-model="form.datos.ejecutado_por" v-mayuscula type="text" maxlength="200" class="form-control" :class="{ 'is-invalid': form.errores.value.ejecutado_por }" placeholder="Nombre de quien ejecutó el mandamiento">
                        <CampoError :mensaje="form.errores.value.ejecutado_por" />
                    </div>
                    <div class="col-12">
                        <label for="detalle_ejecucion_estado" class="form-label">Detalle de la Ejecución</label>
                        <textarea id="detalle_ejecucion_estado" v-model="form.datos.detalle_ejecucion" rows="2" class="form-control" :class="{ 'is-invalid': form.errores.value.detalle_ejecucion }" placeholder="Lugar, circunstancias u observaciones (opcional)" />
                        <CampoError :mensaje="form.errores.value.detalle_ejecucion" />
                    </div>
                    <div class="col-12">
                        <label for="acta_ejecucion_estado" class="form-label">
                            Acta de Ejecución <span v-if="!actaActual" class="text-danger">*</span>
                        </label>
                        <input
                            id="acta_ejecucion_estado"
                            ref="entradaActa"
                            type="file"
                            class="form-control"
                            :class="{ 'is-invalid': form.errores.value.acta_ejecucion }"
                            accept="image/jpeg,image/png,image/webp,.pdf"
                            @change="elegirActa"
                        >
                        <CampoError :mensaje="form.errores.value.acta_ejecucion" />
                        <div class="form-text">
                            Imagen o PDF, hasta 5 MB.
                            <template v-if="actaActual">
                                Ya tiene un acta:
                                <a :href="urlArchivo(actaActual.ruta)" target="_blank" rel="noopener">
                                    <i :class="actaEsPdf ? 'ri-file-pdf-line' : 'ri-image-line'" /> ver acta actual
                                </a>. Si sube otra, se mostrará la nueva (la anterior se conserva).
                            </template>
                        </div>
                    </div>
                </template>

                <div v-else-if="tieneDatosEjecucion" class="col-12">
                    <div class="alert alert-info mb-0 small">
                        <i class="ri-information-line me-1 align-middle" />
                        Sólo se cambiará el estado. La fecha, quién lo ejecutó y el acta ya registrados se conservan.
                    </div>
                </div>
            </div>
        </form>

        <template #pie="{ cerrar }">
            <button type="button" class="btn btn-light" @click="cerrar">Cancelar</button>
            <button type="submit" form="formEstadoMandamiento" class="btn btn-primary" :disabled="form.procesando.value">
                <i class="align-middle me-1" :class="form.procesando.value ? 'ri-loader-4-line' : 'ri-save-3-line'" />
                {{ form.procesando.value ? 'Guardando…' : 'Guardar Estado' }}
            </button>
        </template>
    </BaseModal>
</template>
