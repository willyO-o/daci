<script setup>
import PageHeader from '@/Components/Layout/PageHeader.vue';
import DetalleRegistro from '@/Components/RegistroCriminal/DetalleRegistro.vue';

defineProps({
    registro: { type: Object, required: true },
    identificador: { type: String, default: null },
});

const imprimir = () => window.print();
</script>

<template>
    <div>
        <PageHeader titulo="Detalles del Registro" :migas="[{ etiqueta: 'Registro Criminal' }, { etiqueta: 'Detalle' }]">
            <template #acciones>
                <button type="button" class="btn btn-soft-secondary no-imprimir" @click="imprimir"><i class="ri-printer-line align-middle me-1" /> Imprimir</button>
            </template>
        </PageHeader>
        <div class="card">
            <div class="card-body">
                <DetalleRegistro :registro="registro" :identificador="identificador" :nueva-pestana="false" />
            </div>
        </div>
    </div>
</template>
