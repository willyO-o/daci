<script setup>
/** Mensaje de validación bajo un campo (estilo `invalid-feedback` de la plantilla). */
defineProps({
    mensaje: { type: [String, Array], default: null },
});
</script>

<template>
    <div v-if="mensaje" class="invalid-feedback d-block">
        {{ Array.isArray(mensaje) ? mensaje[0] : mensaje }}
    </div>
</template>
