<script setup>
import PageHeader from '@/Components/Layout/PageHeader.vue';
import { cifra } from '@/Utils/formato';

defineProps({
    reportes: { type: Array, required: true },
});

const urlCsv = (tipo) => route('reportes.exportar', { tipo, formato: 'csv' });
</script>

<template>
    <div>
        <PageHeader titulo="Reportes" :migas="[{ etiqueta: 'Reportes' }]" />

        <div class="card">
            <div class="card-body">
                <h5 class="card-title mb-2">Exporta tus datos</h5>
                <p class="card-text text-muted mb-0">Selecciona un tipo de reporte para descargar todos sus registros en formato CSV (se abre en Excel).</p>
            </div>
        </div>

        <div class="row">
            <div v-for="reporte in reportes" :key="reporte.tipo" class="col-xl-3 col-md-6">
                <div class="card card-height-100">
                    <div class="card-header border-0 text-center py-4" :class="`bg-soft-${reporte.color}`">
                        <i class="mdi" :class="[reporte.icono, `text-${reporte.color}`]" style="font-size: 3rem" />
                    </div>
                    <div class="card-body d-flex flex-column text-center">
                        <h5 class="card-title mb-1">{{ reporte.titulo }}</h5>
                        <p class="text-muted small mb-2">{{ reporte.descripcion }}</p>
                        <p class="mb-3"><span class="fs-18 fw-semibold cifra">{{ cifra(reporte.total) }}</span> <span class="text-muted small">registros</span></p>
                        <a :href="urlCsv(reporte.tipo)" class="btn btn-success btn-sm mt-auto">
                            <i class="mdi mdi-file-delimited-outline align-middle me-1" /> Descargar CSV
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
