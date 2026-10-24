<script setup>
/**
 * Tarjeta de vidrio de las pantallas de acceso: escudo de la FELCC, «FELCC - DACI» y lo que cada página agregue.
 * En el celular la tarjeta pierde la caja y el formulario va directo sobre la foto (ver `acceso.css`).
 */
defineProps({
    /** Texto bajo el título (p. ej. qué hacer en esta pantalla). */
    texto: { type: String, default: '' },
});
</script>

<template>
    <section class="acceso-tarjeta" aria-labelledby="acceso-titulo">
        <img class="acceso-escudo" src="/felcc/img/felcc.png" alt="Escudo de la Fuerza Especial de Lucha Contra el Crimen" width="300" height="300">
        <h1 id="acceso-titulo" class="acceso-titulo">FELCC - DACI</h1>
        <p class="acceso-subtitulo">
            Fuerza Especial de Lucha Contra el Crimen<br>
            Dirección de Análisis de Información Criminal
        </p>
        <p v-if="texto" class="acceso-texto">{{ texto }}</p>

        <slot />
    </section>
</template>
