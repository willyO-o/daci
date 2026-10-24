<script setup>
import { computed, ref } from 'vue';

/**
 * Campo de las pantallas de acceso: ícono a la izquierda, rótulo accesible (oculto a la vista, como en la propuesta)
 * y, para contraseñas, el botón de mostrar / ocultar.
 *
 *   <CampoAcceso id="email" v-model="form.email" tipo="email" icono="ri-user-3-fill" rotulo="Correo electrónico" />
 */
const props = defineProps({
    modelValue: { type: String, default: '' },
    id: { type: String, required: true },
    rotulo: { type: String, required: true },
    tipo: { type: String, default: 'text' },
    icono: { type: String, required: true },
    autocompletar: { type: String, default: undefined },
    error: { type: String, default: '' },
    enfocar: { type: Boolean, default: false },
});

defineEmits(['update:modelValue']);

const verClave = ref(false);
const esClave = computed(() => props.tipo === 'password');
const tipoReal = computed(() => (esClave.value && verClave.value ? 'text' : props.tipo));
const idError = computed(() => `${props.id}-error`);
</script>

<template>
    <div class="acceso-campo">
        <label :for="id" class="visually-hidden">{{ rotulo }}</label>
        <span class="acceso-campo-icono" aria-hidden="true"><i :class="icono" /></span>
        <input
            :id="id"
            :value="modelValue"
            :type="tipoReal"
            class="acceso-campo-control"
            :class="{ 'pe-3': !esClave }"
            :placeholder="rotulo"
            :autocomplete="autocompletar"
            :autofocus="enfocar"
            :aria-invalid="error ? 'true' : undefined"
            :aria-describedby="error ? idError : undefined"
            required
            @input="$emit('update:modelValue', $event.target.value)"
        >
        <button
            v-if="esClave"
            type="button"
            class="acceso-campo-ojo"
            :aria-label="verClave ? 'Ocultar contraseña' : 'Mostrar contraseña'"
            :aria-pressed="verClave"
            @click="verClave = !verClave"
        >
            <i :class="verClave ? 'ri-eye-line' : 'ri-eye-off-line'" aria-hidden="true" />
        </button>
        <p v-if="error" :id="idError" class="acceso-campo-error" role="alert">
            <i class="ri-error-warning-line" aria-hidden="true" />{{ error }}
        </p>
    </div>
</template>
