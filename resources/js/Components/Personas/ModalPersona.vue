<script setup>
import { computed, ref } from 'vue';
import BaseModal from '@/Components/Ui/BaseModal.vue';
import FormularioPersona from '@/Components/Personas/FormularioPersona.vue';

/**
 * Alta y edición de una persona (el modal «Registrar / Editar Persona» del sistema anterior). El formulario es
 * `FormularioPersona`, el mismo de la página «Registrar Persona».
 */
const props = defineProps({
    modelValue: { type: Boolean, default: false },
    /** Persona a editar; `null` para registrar una nueva. */
    persona: { type: Object, default: null },
    paises: { type: Array, required: true },
});

const emit = defineEmits(['update:modelValue', 'guardado', 'fotos-cambiadas']);

const formulario = ref(null);
const editando = computed(() => Boolean(props.persona?.id));

const abierto = computed({
    get: () => props.modelValue,
    set: (valor) => emit('update:modelValue', valor),
});

const guardado = ({ registrarOtra }) => {
    emit('guardado');
    if (!registrarOtra) {
        abierto.value = false;
    }
};
</script>

<template>
    <BaseModal v-model="abierto" :titulo="editando ? 'Editar Persona' : 'Registrar Persona'" tamano="xl" estatico>
        <FormularioPersona ref="formulario" :persona="persona" :paises="paises" @guardado="guardado" @fotos-cambiadas="emit('fotos-cambiadas')" />

        <template #pie="{ cerrar }">
            <button type="button" class="btn btn-light" @click="cerrar">Cancelar</button>
            <button v-if="!editando" type="button" class="btn btn-info" :disabled="formulario?.procesando" @click="formulario?.guardar(true)">
                <i class="ri-save-3-line align-middle me-1" /> Guardar y Registrar Nuevo
            </button>
            <button type="submit" form="formPersona" class="btn btn-primary" :disabled="formulario?.procesando">
                <i class="align-middle me-1" :class="formulario?.procesando ? 'ri-loader-4-line' : 'ri-save-3-line'" />
                {{ formulario?.procesando ? 'Guardando…' : 'Guardar' }}
            </button>
        </template>
    </BaseModal>
</template>
