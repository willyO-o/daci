<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import PageHeader from '@/Components/Layout/PageHeader.vue';
import PanelImportacion from '@/Components/Importar/PanelImportacion.vue';

defineProps({
    totalTelefonos: { type: Number, default: 0 },
    totalImeis: { type: Number, default: 0 },
});

const pestana = ref('telefonos');
const actualizar = () => router.reload({ only: ['totalTelefonos', 'totalImeis'] });

const COLUMNAS_TELEFONO = ['NUMERO DE CELULAR', 'PERSONA DEL CASO', 'CASO', 'EMPRESA', 'RESP A REQ', 'C.I.', 'INFO', 'CALLAPP', 'TRUECALL', 'UNINET'];
</script>

<template>
    <div>
        <PageHeader titulo="Importar Teléfonos" :migas="[{ etiqueta: 'Telefonos', ruta: 'telefonos.index' }, { etiqueta: 'Importar' }]" />

        <ul class="nav nav-tabs nav-tabs-custom nav-success mb-3" role="tablist">
            <li class="nav-item">
                <button type="button" class="nav-link" :class="{ active: pestana === 'telefonos' }" @click="pestana = 'telefonos'"><i class="mdi mdi-phone me-1" /> Teléfonos</button>
            </li>
            <li class="nav-item">
                <button type="button" class="nav-link" :class="{ active: pestana === 'imeis' }" @click="pestana = 'imeis'"><i class="mdi mdi-numeric me-1" /> IMEIs</button>
            </li>
        </ul>

        <PanelImportacion
            v-if="pestana === 'telefonos'"
            titulo="Importar Teléfonos"
            :url="route('telefonos.importar.store')"
            acepta=".csv"
            formatos="SOLO CSV"
            :campos="[{ nombre: 'NUMERO DE CELULAR', detalle: 'Columna obligatoria' }]"
            :columnas="COLUMNAS_TELEFONO"
            :notas="['Los C.I. y la respuesta a requerimiento se enlazan con la persona del teléfono.', 'Los registros sin número se ignoran.']"
            plantilla="/plantillas/plantilla-importacion-telefonos.xlsx"
            etiqueta-total="Total Teléfonos"
            :total="totalTelefonos"
            ruta-volver="telefonos.index"
            etiqueta-volver="Ver teléfonos"
            @importado="actualizar"
        />
        <PanelImportacion
            v-else
            titulo="Importar IMEIs"
            :url="route('imeis.importar.store')"
            acepta=".csv"
            formatos="SOLO CSV"
            :campos="[{ nombre: 'NUMERO ASOCIADO', detalle: 'Número de teléfono (se crea si no existe)' }, { nombre: 'NUMERO IMEI', detalle: 'IMEI del equipo (se crea si no existe)' }]"
            :notas="['El IMEI queda vinculado al teléfono; un vínculo existente no se repite.']"
            plantilla="/plantillas/plantilla-importacion-imeis.xlsx"
            etiqueta-total="Total IMEIs"
            :total="totalImeis"
            ruta-volver="imeis.index"
            etiqueta-volver="Ver IMEIs"
            @importado="actualizar"
        />
    </div>
</template>
