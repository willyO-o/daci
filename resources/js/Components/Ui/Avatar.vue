<script setup>
import { computed } from 'vue';
import { iniciales, urlArchivo } from '@/Utils/formato';

/** Foto de la persona o sus iniciales en el círculo suave de la plantilla. */
const props = defineProps({
    nombre: { type: String, default: '' },
    /** Ruta en el disco `public` (p. ej. `personas/abc.jpg`). */
    ruta: { type: String, default: null },
    /** xs | sm | md | lg */
    tamano: { type: String, default: 'sm' },
});

const imagen = computed(() => urlArchivo(props.ruta));
</script>

<template>
    <div :class="`avatar-${tamano} flex-shrink-0`">
        <img v-if="imagen" :src="imagen" :alt="`Foto de ${nombre}`" :class="`avatar-${tamano} rounded-circle`" loading="lazy">
        <div v-else class="avatar-title bg-soft-primary text-primary rounded-circle fs-13">{{ iniciales(nombre) || '?' }}</div>
    </div>
</template>
