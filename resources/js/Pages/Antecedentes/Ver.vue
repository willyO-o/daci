<script setup>
import PageHeader from '@/Components/Layout/PageHeader.vue';
import DetalleRegistro from '@/Components/RegistroCriminal/DetalleRegistro.vue';

/** Detalle de un registro de Faltas y Contravenciones por código (enlace para compartir o imprimir). */
defineProps({
    modulo: { type: Object, required: true },
    registro: { type: Object, required: true },
    identificador: { type: String, default: null },
});

const imprimir = () => window.print();
</script>

<template>
    <div>
        <PageHeader :titulo="`${modulo.titulo} · Detalles del Registro`" :migas="[{ etiqueta: modulo.titulo }, { etiqueta: 'Detalle' }]">
            <template #acciones>
                <button type="button" class="btn btn-soft-secondary no-imprimir" @click="imprimir"><i class="ri-printer-line align-middle me-1" /> Imprimir</button>
            </template>
        </PageHeader>
        <div class="card">
            <div class="card-body">
                <DetalleRegistro :registro="registro" :identificador="identificador" :nueva-pestana="false" :ruta-registros="modulo.rutas.showByCodigo" :titulo-registros="`Otras ${modulo.titulo}`" />
            </div>
        </div>
    </div>
</template>
