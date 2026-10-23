<script setup>
import { onMounted, ref } from 'vue';
import EstadoVacio from '@/Components/Ui/EstadoVacio.vue';
import { cifra } from '@/Utils/formato';

/**
 * Contenedor de altura fija con scroll infinito para una lista de `useCargaInfinita`. Cada elemento se dibuja con
 * el slot por defecto (`#default="{ elemento }"`).
 */
const props = defineProps({
    lista: { type: Object, required: true },
    textoVacio: { type: String, default: 'No se encontraron registros' },
    ayudaVacio: { type: String, default: '' },
    altura: { type: String, default: '60vh' },
});

const contenedor = ref(null);
const centinela = ref(null);

onMounted(() => props.lista.observar(centinela.value, contenedor.value));
</script>

<template>
    <div>
        <div class="px-3 pt-2 small text-muted" aria-live="polite">
            <span v-if="lista.cargado.value && lista.total.value">
                Listando <b class="cifra">{{ cifra(lista.elementos.value.length) }}</b> elementos de
                <b class="cifra">{{ cifra(lista.total.value) }}</b> encontrados
            </span>
        </div>
        <div ref="contenedor" class="card-body overflow-auto" :style="{ height: altura }">
            <div class="row">
                <template v-for="elemento in lista.elementos.value" :key="elemento.id">
                    <slot :elemento="elemento" />
                </template>
            </div>

            <EstadoVacio v-if="lista.cargado.value && !lista.cargando.value && !lista.elementos.value.length" :titulo="textoVacio" :texto="ayudaVacio" />

            <div v-if="lista.error.value" class="alert alert-danger d-flex align-items-center justify-content-between">
                <span>No se pudo cargar la lista.</span>
                <button type="button" class="btn btn-sm btn-light" @click="lista.cargarMas()">Reintentar</button>
            </div>

            <div ref="centinela" class="py-3 text-center">
                <span v-if="lista.cargando.value" class="text-muted"><i class="mdi mdi-loading mdi-spin fs-20 align-middle me-2" />Cargando...</span>
                <h6 v-else-if="lista.cargado.value && lista.elementos.value.length && !lista.hayMas()" class="text-danger mb-0">No se encontraron más Resultados</h6>
            </div>
        </div>
    </div>
</template>
