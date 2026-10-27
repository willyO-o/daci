<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { router } from '@inertiajs/vue3';

/**
 * Pantalla «Cargando…» con el logo al navegar entre páginas (va en el layout persistente). Sólo para cambios de
 * página reales: GET sin `preserveState`, sin recarga parcial (`only`), sin precarga ni petición asíncrona. Los filtros
 * y la paginación de los listados, las recargas parciales y los formularios (que muestran «Guardando…») no la abren.
 *
 * Sin parpadeo: aparece si la navegación tarda más de RETRASO y, una vez visible, se queda al menos MINIMO. Lleva la
 * cuenta de las navegaciones en curso (una nueva cancela a la anterior) y se oculta en `finish`, que Inertia emite
 * también cuando la visita se cancela o falla.
 */
const RETRASO = 250;
const MINIMO = 400;

const visible = ref(false);
const enCurso = new Set();
let temporizadorMostrar = null;
let temporizadorOcultar = null;
let mostradoDesde = 0;
const quitarEscuchas = [];

const esCambioDePagina = (visita) =>
    visita?.method === 'get' && visita.preserveState === false && !visita.only?.length && !visita.prefetch && !visita.async;

const alEmpezar = (evento) => {
    const visita = evento.detail.visit;
    if (!esCambioDePagina(visita)) {
        return;
    }
    enCurso.add(visita);
    clearTimeout(temporizadorOcultar);
    temporizadorOcultar = null;

    if (visible.value || temporizadorMostrar) {
        return;
    }
    temporizadorMostrar = setTimeout(() => {
        temporizadorMostrar = null;
        if (enCurso.size) {
            visible.value = true;
            mostradoDesde = Date.now();
        }
    }, RETRASO);
};

const alTerminar = (evento) => {
    if (!enCurso.delete(evento.detail.visit) || enCurso.size) {
        return;
    }
    clearTimeout(temporizadorMostrar);
    temporizadorMostrar = null;
    if (!visible.value) {
        return;
    }
    temporizadorOcultar = setTimeout(() => {
        temporizadorOcultar = null;
        visible.value = false;
    }, Math.max(0, MINIMO - (Date.now() - mostradoDesde)));
};

onMounted(() => {
    quitarEscuchas.push(router.on('start', alEmpezar), router.on('finish', alTerminar));
});

onBeforeUnmount(() => {
    quitarEscuchas.forEach((quitar) => quitar());
    clearTimeout(temporizadorMostrar);
    clearTimeout(temporizadorOcultar);
    enCurso.clear();
});
</script>

<template>
    <Transition name="carga-navegacion">
        <div v-if="visible" class="carga-navegacion" role="status" aria-live="polite" aria-busy="true">
            <div class="carga-navegacion__caja">
                <img src="/felcc/img/felcc.png" alt="" class="carga-navegacion__logo" width="88" height="88">
                <p class="carga-navegacion__texto">Cargando…</p>
                <div class="carga-navegacion__barra" aria-hidden="true"><span /></div>
            </div>
        </div>
    </Transition>
</template>

<style scoped>
.carga-navegacion {
    position: fixed;
    inset: 0;
    z-index: 1060;
    display: grid;
    place-items: center;
    background: color-mix(in srgb, var(--vz-body-bg, #f3f3f9) 78%, transparent);
    backdrop-filter: blur(2px);
    cursor: progress;
}

.carga-navegacion__caja {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.75rem;
    padding: 1.75rem 2.5rem;
    border-radius: 0.75rem;
    background: var(--vz-card-bg-custom, var(--vz-card-bg, #fff));
    box-shadow: 0 0.5rem 1.5rem rgba(0, 0, 0, 0.12);
}

.carga-navegacion__logo {
    width: 88px;
    height: 88px;
    object-fit: contain;
    animation: latido 1.4s ease-in-out infinite;
}

.carga-navegacion__texto {
    margin: 0;
    font-weight: 600;
    letter-spacing: 0.04em;
    color: var(--vz-body-color, #212529);
}

.carga-navegacion__barra {
    position: relative;
    width: 140px;
    height: 4px;
    overflow: hidden;
    border-radius: 2px;
    background: var(--vz-border-color, #e9ebec);
}

.carga-navegacion__barra span {
    position: absolute;
    inset: 0 auto 0 0;
    width: 40%;
    border-radius: 2px;
    background: var(--vz-primary, #405189);
    animation: avance 1.1s ease-in-out infinite;
}

@keyframes latido {
    0%,
    100% {
        transform: scale(1);
        opacity: 1;
    }
    50% {
        transform: scale(0.94);
        opacity: 0.8;
    }
}

@keyframes avance {
    from {
        transform: translateX(-100%);
    }
    to {
        transform: translateX(250%);
    }
}

.carga-navegacion-enter-active,
.carga-navegacion-leave-active {
    transition: opacity 150ms ease-out;
}

.carga-navegacion-enter-from,
.carga-navegacion-leave-to {
    opacity: 0;
}

@media (prefers-reduced-motion: reduce) {
    .carga-navegacion__logo,
    .carga-navegacion__barra span {
        animation: none;
    }

    .carga-navegacion__barra span {
        width: 100%;
        opacity: 0.6;
    }
}
</style>
