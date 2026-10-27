<script setup>
import { Link } from '@inertiajs/vue3';
import PageHeader from '@/Components/Layout/PageHeader.vue';
import BotonAyuda from '@/Components/Ui/BotonAyuda.vue';
import { useTour } from '@/Composables/useTour';
import { usePermisos } from '@/Composables/usePermisos';

/**
 * Marco de las páginas «Registrar …» (persona, teléfono, IMEI, vehículo): encabezado, tarjeta con el formulario del
 * módulo en el slot y botón Guardar, que envía el `<form>` con id `idFormulario`. Quien también ve el listado tiene
 * un acceso a él.
 */
const props = defineProps({
    titulo: { type: String, required: true },
    idFormulario: { type: String, required: true },
    procesando: { type: Boolean, default: false },
    /** Ruta y permiso del listado del módulo, para el enlace «Ver listado». */
    rutaListado: { type: String, default: null },
    permisoListado: { type: String, default: null },
    ancho: { type: String, default: 'col-xl-10' },
    /** Pasos de la guía del formulario (`useTour`): muestra el ícono de ayuda en el encabezado. */
    guia: { type: [Array, Function], default: null },
});

const { puede } = usePermisos();
const { iniciar: iniciarGuia } = useTour();
const abrirGuia = () => iniciarGuia(typeof props.guia === 'function' ? props.guia() : props.guia);
</script>

<template>
    <div>
        <PageHeader :titulo="titulo" :migas="[{ etiqueta: titulo }]">
            <template v-if="guia || (rutaListado && permisoListado && puede(permisoListado))" #acciones>
                <BotonAyuda v-if="guia" @click="abrirGuia" />
                <Link v-if="rutaListado && permisoListado && puede(permisoListado)" :href="route(rutaListado)" class="btn btn-soft-secondary"><i class="ri-list-check align-middle me-1" /> Ver listado</Link>
            </template>
        </PageHeader>

        <div class="row">
            <div :class="ancho">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title mb-0">{{ titulo }}</h4>
                        <p class="text-muted small mb-0 mt-1">Al guardar, el formulario queda limpio para registrar otro.</p>
                    </div>
                    <div class="card-body">
                        <slot />
                    </div>
                    <div class="card-footer text-end">
                        <button type="submit" :form="idFormulario" class="btn btn-primary" data-tour="f-guardar" :disabled="procesando">
                            <i class="align-middle me-1" :class="procesando ? 'ri-loader-4-line' : 'ri-save-3-line'" />
                            {{ procesando ? 'Guardando…' : 'Guardar' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
