<script setup>
import { ref } from 'vue';
import PaginaRegistro from '@/Components/Ui/PaginaRegistro.vue';
import FormularioPersona from '@/Components/Personas/FormularioPersona.vue';

/**
 * «Registrar Persona» como página propia: el mismo formulario del listado, sin ver los registros existentes
 * (permiso `personas.crear`). Se llega desde el Dashboard o, si no se ve el listado, desde el menú.
 */
defineProps({
    paises: { type: Array, required: true },
});

const formulario = ref(null);
</script>

<template>
    <PaginaRegistro titulo="Registrar Persona" id-formulario="formPersona" :procesando="Boolean(formulario?.procesando)" ruta-listado="personas.index" permiso-listado="personas.listar">
        <FormularioPersona ref="formulario" :paises="paises" />
    </PaginaRegistro>
</template>
