<script setup>
import { computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import CampoError from '@/Components/Ui/CampoError.vue';

/**
 * Formulario de caso (alta y edición). Lo usan el listado y el detalle. El botón de guardar va fuera
 * (`<button type="submit" :form="idFormulario">`). El servidor limpia el código igual que la migración (espacios,
 * guiones sueltos, MAYÚSCULA) y rechaza uno vacío o de relleno («S/N», «-»).
 */
const props = defineProps({
    /** Caso a editar; `null` para registrar. */
    caso: { type: Object, default: null },
    estados: { type: Array, default: () => ['ACTIVO', 'INACTIVO'] },
    idFormulario: { type: String, default: 'formCaso' },
});

const emit = defineEmits(['guardado']);

const VACIO = { codigo: '', descripcion: '', estado: 'ACTIVO' };
const form = useForm({ ...VACIO });
const editando = computed(() => Boolean(props.caso?.id));

const cargar = () => {
    const c = props.caso;
    form.defaults(editando.value ? Object.fromEntries(Object.keys(VACIO).map((campo) => [campo, c[campo] ?? VACIO[campo]])) : { ...VACIO });
    form.reset();
    form.clearErrors();
};

watch(() => props.caso, cargar, { immediate: true });

const guardar = () => {
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

defineExpose({ guardar, procesando: computed(() => form.processing) });
</script>

<template>
    <form :id="idFormulario" novalidate @submit.prevent="guardar">
        <div class="row g-3">
            <div class="col-md-8">
                <label for="caso_codigo" class="form-label">Caso / CUD <span class="text-danger">*</span></label>
                <input id="caso_codigo" v-model="form.codigo" v-mayuscula type="text" maxlength="255" class="form-control" :class="{ 'is-invalid': form.errors.codigo }" placeholder="Ej: 201102012600756 o nombre del caso">
                <CampoError :mensaje="form.errors.codigo" />
                <small v-if="editando" class="text-muted">Cambiar el código lo corrige en todos los registros vinculados a este caso.</small>
            </div>
            <div class="col-md-4">
                <label for="caso_estado" class="form-label">Estado</label>
                <select id="caso_estado" v-model="form.estado" class="form-select" :class="{ 'is-invalid': form.errors.estado }">
                    <option v-for="estado in estados" :key="estado" :value="estado">{{ estado }}</option>
                </select>
                <CampoError :mensaje="form.errors.estado" />
            </div>
            <div class="col-12">
                <label for="caso_descripcion" class="form-label">Descripción</label>
                <textarea id="caso_descripcion" v-model="form.descripcion" rows="3" maxlength="2000" class="form-control" :class="{ 'is-invalid': form.errors.descripcion }" placeholder="(opcional)" />
                <CampoError :mensaje="form.errors.descripcion" />
            </div>
        </div>
    </form>
</template>
