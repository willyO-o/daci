<script setup>
import { ref } from 'vue';
import PaginaRegistro from '@/Components/Ui/PaginaRegistro.vue';
import { guiaFormularioImei } from '@/Utils/guias';
import FormularioImei from '@/Components/Imeis/FormularioImei.vue';

/**
 * «Registrar IMEI» como página propia: el mismo formulario del listado, sin ver los registros existentes
 * (permiso `imeis.crear`). Se llega desde el Dashboard o, si no se ve el listado, desde el menú.
 */

const formulario = ref(null);
</script>

<template>
    <PaginaRegistro :guia="guiaFormularioImei" titulo="Registrar IMEI" id-formulario="formImei" :procesando="Boolean(formulario?.procesando)" ruta-listado="imeis.index" permiso-listado="imeis.listar" ancho="col-xl-6 col-lg-8">
        <FormularioImei ref="formulario" />
    </PaginaRegistro>
</template>
