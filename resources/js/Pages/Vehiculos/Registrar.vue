<script setup>
import { ref } from 'vue';
import PaginaRegistro from '@/Components/Ui/PaginaRegistro.vue';
import FormularioVehiculo from '@/Components/Vehiculos/FormularioVehiculo.vue';

/**
 * «Registrar Vehículo» como página propia: el mismo formulario del listado, sin ver los registros existentes
 * (permiso `vehiculos.crear`). Se llega desde el Dashboard o, si no se ve el listado, desde el menú.
 */

const formulario = ref(null);
</script>

<template>
    <PaginaRegistro titulo="Registrar Vehículo" id-formulario="formVehiculo" :procesando="Boolean(formulario?.procesando)" ruta-listado="vehiculos.index" permiso-listado="vehiculos.listar" ancho="col-xl-9">
        <FormularioVehiculo ref="formulario" />
    </PaginaRegistro>
</template>
