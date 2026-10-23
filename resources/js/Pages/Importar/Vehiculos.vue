<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import PageHeader from '@/Components/Layout/PageHeader.vue';
import PanelImportacion from '@/Components/Importar/PanelImportacion.vue';

defineProps({
    totalVehiculos: { type: Number, default: 0 },
    totalCargios: { type: Number, default: 0 },
    totalInspecciones: { type: Number, default: 0 },
});

const pestana = ref('vehiculos');
const actualizar = () => router.reload({ only: ['totalVehiculos', 'totalCargios', 'totalInspecciones'] });

const COLUMNAS_VEHICULO = ['PLACA', 'CARACTERISTICAS', 'RESPONSABLE', 'CASO RELACIONADO', 'RUAT', 'CI RUAT', 'BSISA', 'CI BSISA', 'SOAT', 'CI SOAT'];
const COLUMNAS_CARGIO = ['EESS', 'NIT ESTACION', 'DEPARTAMENTO', 'PRODUCTO', 'RAZON SOCIAL', 'NIT CONSUMIDOR', 'FACTURA', 'NRO AUTORIZACION', 'CODIGO CONTROL', 'CANTIDAD', 'MONTO BS.', 'FECHA VENTA', 'PLACA'];
</script>

<template>
    <div>
        <PageHeader titulo="Importar Vehículos" :migas="[{ etiqueta: 'Vehículos', ruta: 'vehiculos.index' }, { etiqueta: 'Importar' }]" />

        <ul class="nav nav-tabs nav-tabs-custom nav-success mb-3" role="tablist">
            <li class="nav-item">
                <button type="button" class="nav-link" :class="{ active: pestana === 'vehiculos' }" @click="pestana = 'vehiculos'"><i class="mdi mdi-car me-1" /> Vehículos</button>
            </li>
            <li class="nav-item">
                <button type="button" class="nav-link" :class="{ active: pestana === 'carguios' }" @click="pestana = 'carguios'"><i class="mdi mdi-gas-station me-1" /> Carguíos</button>
            </li>
            <li class="nav-item">
                <button type="button" class="nav-link" :class="{ active: pestana === 'inspecciones' }" @click="pestana = 'inspecciones'"><i class="mdi mdi-clipboard-check-outline me-1" /> Inspecciones Técnicas</button>
            </li>
        </ul>

        <PanelImportacion
            v-if="pestana === 'vehiculos'"
            titulo="Importar Vehículos"
            :url="route('vehiculos.importar.store')"
            acepta=".csv"
            formatos="SOLO CSV"
            :campos="[{ nombre: 'PLACA', detalle: 'Columna obligatoria; se normaliza (sin espacios ni guiones)' }]"
            :columnas="COLUMNAS_VEHICULO"
            :notas="['Las personas de RUAT, BSISA y SOAT se buscan por C.I. o nombre y se vinculan.', 'Una placa que ya existe no se duplica.', 'Los registros sin placa se ignoran.']"
            plantilla="/plantillas/plantilla-importacion-vehiculos.xlsx"
            etiqueta-total="Total Vehículos"
            :total="totalVehiculos"
            ruta-volver="vehiculos.index"
            etiqueta-volver="Ver vehículos"
            @importado="actualizar"
        />
        <PanelImportacion
            v-else-if="pestana === 'carguios'"
            titulo="Importar Carguíos"
            :url="route('vehiculos.carguios.importar.store')"
            acepta=".csv,.xlsx"
            formatos="CSV o XLSX"
            :campos="[{ nombre: 'PLACA' }, { nombre: 'EESS' }, { nombre: 'NIT ESTACION' }, { nombre: 'FECHA VENTA' }]"
            :columnas="COLUMNAS_CARGIO"
            :notas="['En Excel se leen todas las hojas.', 'Un carguío idéntico (vehículo, estación, producto, NIT, factura y fecha) no se repite.']"
            plantilla="/plantillas/plantilla-importacion-cargios.xlsx"
            etiqueta-total="Total Carguíos"
            :total="totalCargios"
            ruta-volver="vehiculos.index"
            etiqueta-volver="Ver vehículos"
            @importado="actualizar"
        />
        <PanelImportacion
            v-else
            titulo="Importar Inspecciones Técnicas"
            :url="route('vehiculos.inspecciones.importar.store')"
            acepta=".csv,.xlsx"
            formatos="CSV o XLSX"
            :campos="[{ nombre: 'AÑO' }, { nombre: 'PLACA' }]"
            :columnas="['AÑO', 'NOMBRE', 'C.I.', 'DETALLE', 'PLACA']"
            :notas="['En Excel se leen todas las hojas.', 'Una inspección del mismo vehículo y año no se repite.']"
            plantilla="/plantillas/plantilla-importacion-itv.xlsx"
            etiqueta-total="Total Inspecciones"
            :total="totalInspecciones"
            ruta-volver="vehiculos.index"
            etiqueta-volver="Ver vehículos"
            @importado="actualizar"
        />
    </div>
</template>
