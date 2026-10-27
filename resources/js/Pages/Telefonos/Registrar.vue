<script setup>
import { ref } from 'vue';
import PaginaRegistro from '@/Components/Ui/PaginaRegistro.vue';
import { guiaFormularioTelefono } from '@/Utils/guias';
import FormularioTelefono from '@/Components/Telefonos/FormularioTelefono.vue';

/**
 * «Registrar Teléfono» como página propia: el mismo formulario del listado, sin ver los registros existentes
 * (permiso `telefonos.crear`). Se llega desde el Dashboard o, si no se ve el listado, desde el menú.
 */

const formulario = ref(null);
</script>

<template>
    <PaginaRegistro :guia="guiaFormularioTelefono" titulo="Registrar Teléfono" id-formulario="formTelefono" :procesando="Boolean(formulario?.procesando)" ruta-listado="telefonos.index" permiso-listado="telefonos.listar" ancho="col-xl-9">
        <FormularioTelefono ref="formulario" />
    </PaginaRegistro>
</template>
