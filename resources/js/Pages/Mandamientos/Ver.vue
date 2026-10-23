<script setup>
import PageHeader from '@/Components/Layout/PageHeader.vue';
import DetalleMandamiento from '@/Components/Mandamientos/DetalleMandamiento.vue';

const imprimir = () => window.print();

defineProps({
    mandamiento: { type: Object, required: true },
    identificador: { type: String, default: null },
});
</script>

<template>
    <div>
        <PageHeader titulo="Detalles del Mandamiento" :migas="[{ etiqueta: 'Mandamientos' }, { etiqueta: 'Detalle' }]">
            <template #acciones>
                <button type="button" class="btn btn-soft-secondary no-imprimir" @click="imprimir">
                    <i class="ri-printer-line align-middle me-1" /> Imprimir
                </button>
            </template>
        </PageHeader>
        <div class="card">
            <div class="card-body">
                <DetalleMandamiento :mandamiento="mandamiento" />
            </div>
        </div>
    </div>
</template>
