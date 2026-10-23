<script setup>
/**
 * Mensaje dentro del bloque cuando un listado o una búsqueda no trae filas. Dice qué pasó y, si se pasa el slot,
 * qué hacer a continuación.
 */
defineProps({
    titulo: { type: String, default: 'No se encontraron registros' },
    texto: { type: String, default: '' },
    icono: { type: String, default: 'ri-search-line' },
});
</script>

<template>
    <div class="text-center py-5 px-3">
        <div class="avatar-md mx-auto mb-3">
            <div class="avatar-title bg-soft-primary text-primary rounded-circle fs-24">
                <i :class="icono" />
            </div>
        </div>
        <h5 class="fs-15 mb-1">{{ titulo }}</h5>
        <p v-if="texto" class="text-muted mb-0">{{ texto }}</p>
        <div v-if="$slots.default" class="mt-3">
            <slot />
        </div>
    </div>
</template>
