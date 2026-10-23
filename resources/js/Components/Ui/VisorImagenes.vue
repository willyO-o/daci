<script setup>
import { computed, ref, watch } from 'vue';
import BaseModal from '@/Components/Ui/BaseModal.vue';
import { urlArchivo } from '@/Utils/formato';

/**
 * Visor de fotos a pantalla grande con anterior/siguiente (reemplaza a FsLightbox).
 *
 *   <VisorImagenes v-model="visor" :rutas="['personas/a.jpg', 'personas/b.jpg']" />
 */
const props = defineProps({
    modelValue: { type: Boolean, default: false },
    /** Rutas del disco `public`. */
    rutas: { type: Array, default: () => [] },
    inicial: { type: Number, default: 0 },
});

const emit = defineEmits(['update:modelValue']);

const abierto = computed({
    get: () => props.modelValue,
    set: (valor) => emit('update:modelValue', valor),
});

const indice = ref(0);
watch(
    () => props.modelValue,
    (valor) => valor && (indice.value = props.inicial),
);

const actual = computed(() => urlArchivo(props.rutas[indice.value]));
const mover = (paso) => {
    indice.value = (indice.value + paso + props.rutas.length) % props.rutas.length;
};
</script>

<template>
    <BaseModal v-model="abierto" tamano="xl" centrado cuerpo-clase="p-0 bg-dark">
        <template #titulo>
            Fotografía <span class="text-muted fw-normal cifra">{{ indice + 1 }} de {{ rutas.length }}</span>
        </template>
        <div class="position-relative text-center" tabindex="0" @keydown.left="mover(-1)" @keydown.right="mover(1)">
            <img :src="actual" alt="Fotografía ampliada" class="img-fluid" style="max-height: 75vh">
            <template v-if="rutas.length > 1">
                <button type="button" class="btn btn-light position-absolute top-50 start-0 translate-middle-y ms-2" aria-label="Anterior" @click="mover(-1)">
                    <i class="ri-arrow-left-s-line" />
                </button>
                <button type="button" class="btn btn-light position-absolute top-50 end-0 translate-middle-y me-2" aria-label="Siguiente" @click="mover(1)">
                    <i class="ri-arrow-right-s-line" />
                </button>
            </template>
        </div>
    </BaseModal>
</template>
