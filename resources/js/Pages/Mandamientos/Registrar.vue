<script setup>
import { ref } from 'vue';
import PageHeader from '@/Components/Layout/PageHeader.vue';
import FormularioMandamiento from '@/Components/Mandamientos/FormularioMandamiento.vue';
import BotonAyuda from '@/Components/Ui/BotonAyuda.vue';
import { useTour } from '@/Composables/useTour';
import { guiaFormularioMandamiento } from '@/Utils/guias';

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

// Guía resumida del formulario: sólo con el ícono de ayuda.
const { iniciar: iniciarGuia } = useTour();
const abrirGuia = () => iniciarGuia(guiaFormularioMandamiento());
</script>

<template>
    <div>
        <PageHeader titulo="Registrar Mandamiento" :migas="[{ etiqueta: 'Registrar Mandamiento' }]">
            <template #acciones>
                <BotonAyuda @click="abrirGuia" />
            </template>
        </PageHeader>

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
                        <button type="submit" form="formMandamiento" class="btn btn-primary" data-tour="f-guardar" :disabled="formulario?.procesando">
                            <i class="align-middle me-1" :class="formulario?.procesando ? 'ri-loader-4-line' : 'ri-save-3-line'" />
                            {{ formulario?.procesando ? 'Guardando…' : 'Guardar' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
