<script setup>
import { ref } from 'vue';
import PageHeader from '@/Components/Layout/PageHeader.vue';
import FormularioMandamiento from '@/Components/Mandamientos/FormularioMandamiento.vue';

/**
 * «Registrar Mandamiento» como página propia (el técnico FELCC registra sin ver el listado). Tras guardar, el
 * formulario queda limpio para el siguiente, como en el sistema anterior.
 */
defineProps({
    estados: { type: Array, default: () => [] },
    juzgados: { type: Array, required: true },
    delitos: { type: Array, required: true },
    tiposMandamiento: { type: Array, required: true },
});

const formulario = ref(null);
</script>

<template>
    <div>
        <PageHeader titulo="Registrar Mandamiento" :migas="[{ etiqueta: 'Registrar Mandamiento' }]" />

        <div class="row">
            <div class="col-xl-10">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title mb-0">Registrar Mandamiento</h4>
                    </div>
                    <div class="card-body">
                        <FormularioMandamiento
                            ref="formulario"
                            :estados="estados"
                            :juzgados="juzgados"
                            :delitos="delitos"
                            :tipos-mandamiento="tiposMandamiento"
                        />
                    </div>
                    <div class="card-footer text-end">
                        <button type="submit" form="formMandamiento" class="btn btn-primary" :disabled="formulario?.procesando">
                            <i class="align-middle me-1" :class="formulario?.procesando ? 'ri-loader-4-line' : 'ri-save-3-line'" />
                            {{ formulario?.procesando ? 'Guardando…' : 'Guardar' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
