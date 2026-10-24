<script setup>
/**
 * Dibujo en miniatura de un layout (las tarjetas del «Theme Customizer» de Velzon): menú a la izquierda con sus
 * opciones, barra superior y pie. Cada opción del panel cambia sólo las clases que la diferencian.
 */
defineProps({
    /** Fondo del menú lateral. */
    lateral: { type: String, default: 'bg-light' },
    /** Color de las opciones del menú. */
    opciones: { type: String, default: 'bg-soft-primary' },
    /** Fondo de la barra superior y del pie. */
    superior: { type: String, default: 'bg-light' },
    pie: { type: String, default: null },
    /** `normal` | `compacto` (sin margen lateral) | `iconos` (sólo íconos, sin relleno). */
    menu: { type: String, default: 'normal' },
    /** Menú separado bajo una barra superior a todo el ancho (vista «Detached»). */
    separado: { type: Boolean, default: false },
    /** Contenido encajado entre bordes (ancho «Boxed»). */
    encajado: { type: Boolean, default: false },
});
</script>

<template>
    <span v-if="separado" class="d-flex h-100 flex-column">
        <span class="d-flex p-1 gap-1 align-items-center px-2" :class="superior">
            <span class="d-block p-1 rounded me-1" :class="opciones" />
            <span class="d-block p-1 pb-0 px-2 ms-auto" :class="opciones" />
            <span class="d-block p-1 pb-0 px-2" :class="opciones" />
        </span>
        <span class="d-flex gap-1 h-100 p-1 px-2">
            <span class="flex-shrink-0">
                <span class="d-flex h-100 flex-column gap-1 p-1" :class="lateral">
                    <span v-for="n in 3" :key="n" class="d-block p-1 px-2 pb-0" :class="opciones" />
                </span>
            </span>
        </span>
        <span class="d-block p-1 mt-auto px-2" :class="pie ?? superior" />
    </span>
    <span v-else class="d-flex gap-1 h-100" :class="{ 'border-start border-end': encajado }">
        <span class="flex-shrink-0">
            <span class="d-flex h-100 flex-column gap-1" :class="[lateral, { 'p-1': menu !== 'iconos' }]">
                <span class="d-block p-1 mb-2" :class="[opciones, { 'px-2': menu === 'normal', rounded: menu !== 'iconos' }]" />
                <span v-for="n in 3" :key="n" class="d-block p-1 pb-0" :class="[opciones, { 'px-2': menu === 'normal' }]" />
            </span>
        </span>
        <span class="flex-grow-1">
            <span class="d-flex h-100 flex-column">
                <span class="d-block p-1" :class="superior" />
                <span class="d-block p-1 mt-auto" :class="pie ?? (superior === 'bg-dark' ? 'bg-light' : superior)" />
            </span>
        </span>
    </span>
</template>
