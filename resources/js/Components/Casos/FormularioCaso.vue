<script setup>
import { computed, nextTick, onMounted, reactive, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import CampoError from '@/Components/Ui/CampoError.vue';
import { useNotificacion } from '@/Composables/useNotificacion';
import { http } from '@/Utils/http';

/**
 * Formulario de caso, reutilizable en dos modos:
 *
 * - Normal (módulo Casos): `<form>` con Inertia; el botón de guardar va fuera (`<button type="submit" :form="idFormulario">`).
 * - `rapido` (dentro del formulario de un teléfono, vehículo o registro, desde `SelectorCaso`): NO es un `<form>` (no
 *   puede ir dentro de otro), guarda por JSON sin recargar la página y emite `guardado` con el caso creado. Enter en
 *   el código crea el caso en vez de enviar el formulario de afuera. Si el código ya existe, el servidor devuelve ese.
 *
 * El servidor limpia el código igual que la migración (espacios, guiones sueltos, MAYÚSCULA) y rechaza uno vacío o de
 * relleno («S/N», «-»).
 */
const props = defineProps({
    /** Caso a editar; `null` para registrar. */
    caso: { type: Object, default: null },
    estados: { type: Array, default: () => ['ACTIVO', 'INACTIVO'] },
    idFormulario: { type: String, default: 'formCaso' },
    rapido: { type: Boolean, default: false },
    /** Código con el que empieza el alta rápida (lo que se escribió en el buscador). */
    codigoInicial: { type: String, default: '' },
});

const emit = defineEmits(['guardado', 'cancelar']);

const { exito, aviso, errorDeRespuesta } = useNotificacion();

const VACIO = { codigo: '', descripcion: '', estado: 'ACTIVO' };
const editando = computed(() => Boolean(props.caso?.id));
const campoCodigo = ref(null);
// Ids únicos: puede haber más de un formulario rápido en la misma pantalla (p. ej. vehículo y persona asociada).
const sufijo = Math.random().toString(36).slice(2, 8);
const id = (campo) => `caso_${campo}_${sufijo}`;

// ---- Modo normal (Inertia)
const form = useForm({ ...VACIO });

const cargar = () => {
    const c = props.caso;
    form.defaults(editando.value ? Object.fromEntries(Object.keys(VACIO).map((campo) => [campo, c[campo] ?? VACIO[campo]])) : { ...VACIO });
    form.reset();
    form.clearErrors();
};

watch(() => props.caso, cargar, { immediate: true });

// ---- Modo rápido (JSON)
const rapido = reactive({ codigo: props.codigoInicial, descripcion: '', errores: {}, procesando: false });

const guardarRapido = async () => {
    if (rapido.procesando) {
        return;
    }
    rapido.procesando = true;
    rapido.errores = {};
    try {
        const { data } = await http.post(route('casos.store-rapido'), { codigo: rapido.codigo, descripcion: rapido.descripcion || null });
        if (data.existente) {
            aviso(`El caso ${data.codigo} ya existía: se seleccionó.`);
        } else {
            exito(`Caso ${data.codigo} registrado.`);
        }
        rapido.codigo = '';
        rapido.descripcion = '';
        emit('guardado', data);
    } catch (err) {
        if (err.response?.status === 422) {
            rapido.errores = Object.fromEntries(Object.entries(err.response.data.errors ?? {}).map(([campo, mensajes]) => [campo, mensajes[0]]));
        } else {
            errorDeRespuesta(err);
        }
    } finally {
        rapido.procesando = false;
    }
};

const guardar = () => {
    if (props.rapido) {
        guardarRapido();

        return;
    }
    const opciones = {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            if (!editando.value) {
                cargar();
            }
            emit('guardado');
        },
    };
    if (editando.value) {
        form.put(route('casos.update', props.caso.id), opciones);
    } else {
        form.post(route('casos.store'), opciones);
    }
};

onMounted(() => {
    if (props.rapido) {
        nextTick(() => campoCodigo.value?.focus());
    }
});

defineExpose({ guardar, procesando: computed(() => (props.rapido ? rapido.procesando : form.processing)) });
</script>

<template>
    <!-- Alta rápida: un bloque dentro del formulario de afuera (no un <form>). -->
    <div v-if="rapido" class="border rounded p-3 bg-light" role="group" aria-label="Nuevo caso">
        <div class="d-flex align-items-center mb-2">
            <h6 class="mb-0 flex-grow-1"><i class="ri-folder-add-line align-middle me-1" /> Nuevo caso</h6>
            <button type="button" class="btn-close" aria-label="Cerrar" @click="emit('cancelar')" />
        </div>
        <div class="row g-2">
            <div class="col-md-5">
                <label :for="id('codigo')" class="form-label">Caso / CUD <span class="text-danger">*</span></label>
                <input
                    :id="id('codigo')"
                    ref="campoCodigo"
                    v-model="rapido.codigo"
                    v-mayuscula
                    type="text"
                    maxlength="255"
                    class="form-control"
                    :class="{ 'is-invalid': rapido.errores.codigo }"
                    placeholder="Ej: 201102012600756"
                    @keydown.enter.prevent.stop="guardarRapido"
                >
                <CampoError :mensaje="rapido.errores.codigo" />
            </div>
            <div class="col-md-7">
                <label :for="id('descripcion')" class="form-label">Descripción</label>
                <input
                    :id="id('descripcion')"
                    v-model="rapido.descripcion"
                    type="text"
                    maxlength="2000"
                    class="form-control"
                    :class="{ 'is-invalid': rapido.errores.descripcion }"
                    placeholder="(opcional)"
                    @keydown.enter.prevent.stop="guardarRapido"
                >
                <CampoError :mensaje="rapido.errores.descripcion" />
            </div>
        </div>
        <div class="d-flex justify-content-end gap-2 mt-2">
            <button type="button" class="btn btn-sm btn-light" @click="emit('cancelar')">Cancelar</button>
            <button type="button" class="btn btn-sm btn-primary" :disabled="rapido.procesando || !rapido.codigo.trim()" @click="guardarRapido">
                <i class="align-middle me-1" :class="rapido.procesando ? 'ri-loader-4-line' : 'ri-save-3-line'" /> {{ rapido.procesando ? 'Guardando…' : 'Crear y seleccionar' }}
            </button>
        </div>
    </div>

    <form v-else :id="idFormulario" novalidate @submit.prevent="guardar">
        <div class="row g-3">
            <div class="col-md-8">
                <label :for="id('codigo')" class="form-label">Caso / CUD <span class="text-danger">*</span></label>
                <input :id="id('codigo')" v-model="form.codigo" v-mayuscula type="text" maxlength="255" class="form-control" :class="{ 'is-invalid': form.errors.codigo }" placeholder="Ej: 201102012600756 o nombre del caso">
                <CampoError :mensaje="form.errors.codigo" />
                <small v-if="editando" class="text-muted">Cambiar el código lo corrige en todos los registros vinculados a este caso.</small>
            </div>
            <div class="col-md-4">
                <label :for="id('estado')" class="form-label">Estado</label>
                <select :id="id('estado')" v-model="form.estado" class="form-select" :class="{ 'is-invalid': form.errors.estado }">
                    <option v-for="estado in estados" :key="estado" :value="estado">{{ estado }}</option>
                </select>
                <CampoError :mensaje="form.errors.estado" />
            </div>
            <div class="col-12">
                <label :for="id('descripcion')" class="form-label">Descripción</label>
                <textarea :id="id('descripcion')" v-model="form.descripcion" rows="3" maxlength="2000" class="form-control" :class="{ 'is-invalid': form.errors.descripcion }" placeholder="(opcional)" />
                <CampoError :mensaje="form.errors.descripcion" />
            </div>
        </div>
    </form>
</template>
