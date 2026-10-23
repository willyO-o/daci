<script setup>
import PageHeader from '@/Components/Layout/PageHeader.vue';
import PanelImportacion from '@/Components/Importar/PanelImportacion.vue';

const CAMPOS = [
    { nombre: 'NOMBRE', detalle: 'Nombre completo; las dos últimas palabras se toman como apellidos' },
    { nombre: 'CI', detalle: 'Si ya existe una persona con ese CI, se actualiza en lugar de duplicarse' },
    { nombre: 'responsable', detalle: 'Responsable de la persona' },
    { nombre: 'DATOS_SEGIP', detalle: 'Información completa del SEGIP (se guarda tal cual)' },
    { nombre: 'ESTADO', detalle: 'Estado de la investigación' },
];

const NOTAS = [
    'Formatos CSV (separador punto y coma o coma), XLSX o XLS.',
    'La primera fila debe contener los encabezados.',
    'Los registros vacíos se ignoran.',
];
</script>

<template>
    <div>
        <PageHeader titulo="Importar Personas" :migas="[{ etiqueta: 'Personas', ruta: 'personas.index' }, { etiqueta: 'Importar' }]" />
        <PanelImportacion
            titulo="Importación Masiva de Personas"
            :url="route('personas.importar.store')"
            acepta=".csv,.xlsx,.xls"
            formatos="CSV, XLSX, XLS"
            :campos="CAMPOS"
            :notas="NOTAS"
            :plantilla="route('personas.importar.plantilla')"
            ruta-volver="personas.index"
            etiqueta-volver="Ver personas"
        />
    </div>
</template>
