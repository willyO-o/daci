<script setup>
import { router } from '@inertiajs/vue3';
import PageHeader from '@/Components/Layout/PageHeader.vue';
import PanelImportacion from '@/Components/Importar/PanelImportacion.vue';

defineProps({
    total: { type: Number, default: 0 },
});

const CAMPOS = [
    { nombre: 'HOJA DE RUTA O MEMORANDUM', detalle: 'Ruta u origen del mandamiento' },
    { nombre: 'NOMBRE', detalle: 'Nombre completo de la persona aprehendida' },
    { nombre: 'TIPO DE MANDAMIENTO', detalle: 'Tipo de mandamiento (ej: Aprehensión)' },
    { nombre: 'ORIGINAL O FOTOCOPIA', detalle: 'Indica si es original o fotocopia' },
    { nombre: 'DELITO', detalle: 'Descripción del delito' },
    { nombre: 'JUZGADO', detalle: 'Nombre del juzgado o tribunal emisor' },
    { nombre: 'ESTADO', detalle: 'Estado del mandamiento' },
    { nombre: 'DOMICILIO', detalle: 'Dirección del aprehendido' },
    { nombre: 'C.I.', detalle: 'Cédula de Identidad (solo números)' },
    { nombre: 'VEHICULOS', detalle: 'Vehículos involucrados en la aprehensión' },
    { nombre: 'TELEFONO', detalle: 'Teléfono de contacto' },
    { nombre: 'ASIGNADO', detalle: 'Persona a la que se asigna el mandamiento' },
    { nombre: 'ACTIVIDADES REALIZADAS', detalle: 'Actividades realizadas relacionadas con el mandamiento' },
];

const NOTAS = [
    'Los campos NOMBRE, C.I. y DELITO son obligatorios.',
    'El formato debe ser según el modelo o plantilla.',
    'El archivo debe estar en formato CSV (separador: punto y coma o coma).',
    'La primera fila debe contener los encabezados.',
    'Los juzgados, delitos, tipos y personas que no existan se crean solos.',
];

const actualizarTotal = () => router.reload({ only: ['total'] });
</script>

<template>
    <div>
        <PageHeader titulo="Importar Mandamientos" :migas="[{ etiqueta: 'Mandamientos', ruta: 'mandamientos.index' }, { etiqueta: 'Importar' }]" />
        <PanelImportacion
            titulo="Importación Masiva de Mandamientos de Aprehensión"
            :url="route('importar.mandamientos.importar')"
            acepta=".csv"
            formatos="CSV"
            :campos="CAMPOS"
            :notas="NOTAS"
            plantilla="/plantillas/plantilla-importacion-mandamientos.xlsx"
            etiqueta-total="Total Mandamientos"
            :total="total"
            ruta-volver="mandamientos.index"
            etiqueta-volver="Ver mandamientos"
            @importado="actualizarTotal"
        />
    </div>
</template>
