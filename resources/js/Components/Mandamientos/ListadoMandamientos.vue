<script setup>
import { onMounted, ref } from 'vue';
import EstadoVacio from '@/Components/Ui/EstadoVacio.vue';
import TarjetaMandamiento from '@/Components/Mandamientos/TarjetaMandamiento.vue';
import { cifra } from '@/Utils/formato';

/**
 * Contenedor con scroll infinito de tarjetas de mandamientos (listado y consulta). Recibe la lista de
 * `useCargaInfinita` y reenvía las acciones de cada tarjeta.
 */
const props = defineProps({
    lista: { type: Object, required: true },
    vista: { type: String, default: 'grid' },
    puedeEditar: { type: Boolean, default: false },
    puedeEliminar: { type: Boolean, default: false },
    puedeCambiarEstado: { type: Boolean, default: false },
    textoVacio: { type: String, default: 'No se encontraron mandamientos' },
    ayudaVacio: { type: String, default: '' },
});

defineEmits(['ver', 'editar', 'eliminar', 'ver-imagenes', 'cambiar-estado']);

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
        <div ref="contenedor" class="card-body overflow-auto" style="height: 60vh">
            <div class="row">
                <TarjetaMandamiento
                    v-for="mandamiento in lista.elementos.value"
                    :key="mandamiento.id"
                    :mandamiento="mandamiento"
                    :vista="vista"
                    :puede-editar="puedeEditar"
                    :puede-eliminar="puedeEliminar"
                    :puede-cambiar-estado="puedeCambiarEstado"
                    @ver="$emit('ver', $event)"
                    @editar="$emit('editar', $event)"
                    @eliminar="$emit('eliminar', $event)"
                    @ver-imagenes="$emit('ver-imagenes', $event)"
                    @cambiar-estado="$emit('cambiar-estado', $event)"
                />
            </div>

            <EstadoVacio v-if="lista.cargado.value && !lista.cargando.value && !lista.elementos.value.length" :titulo="textoVacio" :texto="ayudaVacio" />

            <div v-if="lista.error.value" class="alert alert-danger d-flex align-items-center justify-content-between">
                <span>No se pudieron cargar los mandamientos.</span>
                <button type="button" class="btn btn-sm btn-light" @click="lista.cargarMas()">Reintentar</button>
            </div>

            <div ref="centinela" class="py-3 text-center">
                <span v-if="lista.cargando.value" class="text-muted"><i class="mdi mdi-loading mdi-spin fs-20 align-middle me-2" />Cargando...</span>
                <h6 v-else-if="lista.cargado.value && lista.elementos.value.length && !lista.hayMas()" class="text-danger mb-0">No se encontraron más Resultados</h6>
            </div>
        </div>
    </div>
</template>
