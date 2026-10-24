<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';

/** Botón «volver arriba» de la plantilla: aparece al bajar 100 px y sube suavemente. */
const visible = ref(false);

const revisar = () => {
    visible.value = window.scrollY > 100;
};

const subir = () => window.scrollTo({ top: 0, behavior: 'smooth' });

onMounted(() => {
    revisar();
    window.addEventListener('scroll', revisar, { passive: true });
});
onBeforeUnmount(() => window.removeEventListener('scroll', revisar));
</script>

<template>
    <button id="back-to-top" type="button" class="btn btn-danger btn-icon" :style="{ display: visible ? 'block' : 'none' }" aria-label="Volver arriba" @click="subir">
        <i class="ri-arrow-up-line" />
    </button>
</template>
