<script setup>
import { onBeforeUnmount, watch } from 'vue';
import { urlArchivo } from '@/Utils/formato';

/**
 * Visor de fotos a pantalla completa: el mismo FsLightbox (3.4.0-2) que usaba el sistema anterior, con su
 * apariencia (fondo oscuro, contador, flechas, zoom, pantalla completa y deslizar en el móvil).
 *
 *   <VisorImagenes v-model="visor" :rutas="['registro-criminal/a.webp', 'registro-criminal/b.webp']" />
 *
 * No dibuja nada: la librería agrega su propio contenedor al `body` al abrir y lo quita al cerrar.
 */
const props = defineProps({
    modelValue: { type: Boolean, default: false },
    /** Rutas del disco `public`. */
    rutas: { type: Array, default: () => [] },
    inicial: { type: Number, default: 0 },
});

const emit = defineEmits(['update:modelValue']);

let visor = null;

const abrir = async () => {
    const fuentes = props.rutas.map(urlArchivo).filter(Boolean);
    if (!fuentes.length) {
        emit('update:modelValue', false);

        return;
    }

    // Se carga sólo cuando hace falta; registra `window.FsLightbox` como en el sistema anterior.
    await import('fslightbox');

    // Una instancia nueva por apertura (como antes): así nunca muestra las fotos de la tarjeta anterior.
    visor = new window.FsLightbox();
    visor.props.sources = fuentes;
    visor.props.onClose = () => {
        visor = null;
        emit('update:modelValue', false);
    };
    visor.open(Math.min(Math.max(props.inicial, 0), fuentes.length - 1));
};

const cerrar = () => visor?.close();

watch(
    () => props.modelValue,
    (abierto) => (abierto ? abrir() : cerrar()),
    { immediate: true },
);

onBeforeUnmount(cerrar);
</script>

<template>
    <span class="d-none" aria-hidden="true" />
</template>
