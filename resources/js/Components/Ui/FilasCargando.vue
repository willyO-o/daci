<script setup>
/** Filas de espera de una tabla: misma altura que las reales, sin animación, para que nada salte al llegar los datos. */
defineProps({
    columnas: { type: Number, required: true },
    filas: { type: Number, default: 5 },
});
</script>

<template>
    <tr v-for="fila in filas" :key="fila" class="fila-esqueleto" aria-hidden="true">
        <td v-for="columna in columnas" :key="columna">
            <span :class="{ corto: columna === 1 || columna === columnas }" />
        </td>
    </tr>
</template>
