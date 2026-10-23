<script setup>
import PageHeader from '@/Components/Layout/PageHeader.vue';
import DetallePersona from '@/Components/Personas/DetallePersona.vue';
import { nombreCompleto } from '@/Utils/formato';

defineProps({
    persona: { type: Object, required: true },
});
</script>

<template>
    <div>
        <PageHeader :titulo="nombreCompleto(persona)" :migas="[{ etiqueta: 'Personas', ruta: 'personas.index' }, { etiqueta: 'Detalle' }]" />
        <div class="card">
            <div class="card-body">
                <DetallePersona :persona="persona" :nueva-pestana="false" />
            </div>
        </div>
    </div>
</template>
