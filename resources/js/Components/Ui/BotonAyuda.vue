<script setup>
/**
 * Ícono de ayuda que abre la guía de la pantalla. Al pasar el mouse o llegar con el teclado se ensancha y muestra el
 * texto, que aparece de izquierda a derecha. En pantallas táctiles (sin hover) queda el ícono; el texto siempre está
 * en `aria-label`. Emite `click`.
 *
 *   <BotonAyuda @click="abrirGuia" />
 */
defineProps({
    texto: { type: String, default: '¿Necesita ayuda? Ver la guía' },
    /** Más bajo, para la cabecera de un modal. */
    compacto: { type: Boolean, default: false },
});

defineEmits(['click']);
</script>

<template>
    <button type="button" class="boton-ayuda btn btn-soft-info" :class="{ 'boton-ayuda--compacto': compacto }" data-tour="ayuda" :aria-label="texto" @click="$emit('click')">
        <i class="ri-question-line boton-ayuda__icono" aria-hidden="true" />
        <span class="boton-ayuda__texto" aria-hidden="true">{{ texto }}</span>
    </button>
</template>

<style scoped>
.boton-ayuda {
    display: inline-flex;
    align-items: center;
    height: 2.4rem;
    padding: 0 0.6rem;
    overflow: hidden;
    white-space: nowrap;
}

.boton-ayuda--compacto {
    height: 1.9rem;
    padding: 0 0.45rem;
    font-size: 0.8125rem;
}

.boton-ayuda__icono {
    font-size: 1.15rem;
    line-height: 1;
    transition: transform 250ms cubic-bezier(0.22, 1, 0.36, 1);
}

/* El texto ocupa 0 de ancho en reposo y se descubre de izquierda a derecha (clip-path) mientras el botón crece. */
.boton-ayuda__texto {
    display: inline-block;
    max-width: 0;
    margin-left: 0;
    opacity: 0;
    clip-path: inset(0 100% 0 0);
    font-weight: 500;
    transition:
        max-width 250ms cubic-bezier(0.22, 1, 0.36, 1),
        margin-left 250ms cubic-bezier(0.22, 1, 0.36, 1),
        clip-path 300ms cubic-bezier(0.22, 1, 0.36, 1) 40ms,
        opacity 200ms ease-out 40ms;
}

@media (hover: hover) {
    .boton-ayuda:hover .boton-ayuda__texto {
        max-width: 16rem;
        margin-left: 0.4rem;
        opacity: 1;
        clip-path: inset(0 0 0 0);
    }

    .boton-ayuda:hover .boton-ayuda__icono {
        transform: rotate(-12deg);
    }
}

/* Con el teclado se muestra igual que con el mouse, también en pantallas táctiles. */
.boton-ayuda:focus-visible .boton-ayuda__texto {
    max-width: 16rem;
    margin-left: 0.4rem;
    opacity: 1;
    clip-path: inset(0 0 0 0);
}

@media (prefers-reduced-motion: reduce) {
    .boton-ayuda__texto,
    .boton-ayuda__icono {
        transition: none;
    }

    .boton-ayuda:hover .boton-ayuda__icono {
        transform: none;
    }
}
</style>
