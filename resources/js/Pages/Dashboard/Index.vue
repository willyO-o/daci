<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import PageHeader from '@/Components/Layout/PageHeader.vue';
import { usePermisos } from '@/Composables/usePermisos';
import { cifra } from '@/Utils/formato';

const props = defineProps({
    totales: { type: Object, required: true },
});

const page = usePage();
const { puede } = usePermisos();

const tarjetas = computed(() => [
    {
        titulo: 'Total Mandamientos',
        valor: props.totales.mandamientos,
        enlace: 'Ver todos',
        ruta: 'mandamientos.index',
        permiso: 'mandamientos.listar',
        icono: 'bx bx-file',
        color: 'primary',
    },
    {
        titulo: 'Registro Criminal',
        valor: props.totales.registros,
        enlace: 'Ver registros',
        ruta: 'registro-criminal.index',
        permiso: 'registro_criminal.listar',
        icono: 'bx bx-hourglass',
        color: 'warning',
    },
    {
        titulo: 'Personas Registradas',
        valor: props.totales.personas,
        enlace: 'Ver personas',
        ruta: 'personas.index',
        permiso: 'personas.listar',
        icono: 'bx bx-user',
        color: 'info',
    },
    {
        titulo: 'Vehículos Registrados',
        valor: props.totales.vehiculos,
        enlace: 'Ver vehículos registrados',
        ruta: 'vehiculos.index',
        permiso: 'vehiculos.listar',
        icono: 'bx bx-check-circle',
        color: 'success',
    },
]);
</script>

<template>
    <div>
        <PageHeader titulo="Dashboard" />

        <div class="row mb-3 pb-1">
            <div class="col-12">
                <div class="d-flex align-items-lg-center flex-lg-row flex-column">
                    <div class="flex-grow-1">
                        <h4 class="fs-16 mb-1">¡Bienvenido, {{ page.props.auth.user?.name }}!</h4>
                        <p class="text-muted mb-0">Sistema de Gestión de Mandamientos de Aprehensión</p>
                    </div>
                    <div v-if="puede('mandamientos.crear') && route().has('mandamientos.create')" class="mt-3 mt-lg-0">
                        <Link :href="route('mandamientos.create')" class="btn btn-soft-success shadow-none">
                            <i class="ri-add-circle-line align-middle me-1" /> Nuevo Mandamiento
                        </Link>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div v-for="tarjeta in tarjetas" :key="tarjeta.titulo" class="col-xl-3 col-md-6">
                <div class="card card-animate">
                    <div class="card-body">
                        <p class="fw-medium text-muted text-truncate mb-0">{{ tarjeta.titulo }}</p>
                        <div class="d-flex align-items-end justify-content-between mt-4">
                            <div>
                                <h4 class="fs-22 fw-semibold ff-secondary mb-4 cifra">{{ cifra(tarjeta.valor) }}</h4>
                                <Link v-if="puede(tarjeta.permiso) && route().has(tarjeta.ruta)" :href="route(tarjeta.ruta)" class="text-decoration-underline">
                                    {{ tarjeta.enlace }}
                                </Link>
                            </div>
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title rounded fs-3" :class="`bg-soft-${tarjeta.color} text-${tarjeta.color}`">
                                    <i :class="tarjeta.icono" />
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
