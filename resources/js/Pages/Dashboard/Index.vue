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

// Registro rápido: una tarjeta por cada módulo que el usuario puede registrar (permiso `*.crear`).
const registros = computed(() =>
    [
        { titulo: 'Mandamiento', texto: 'Registrar un mandamiento nuevo', ruta: 'mandamientos.create', permiso: 'mandamientos.crear', icono: 'ri-file-add-line', color: 'primary' },
        { titulo: 'Persona', texto: 'Registrar una persona con sus fotos', ruta: 'personas.create', permiso: 'personas.crear', icono: 'ri-user-add-line', color: 'info' },
        { titulo: 'Teléfono', texto: 'Registrar un número celular', ruta: 'telefonos.create', permiso: 'telefonos.crear', icono: 'ri-phone-line', color: 'success' },
        { titulo: 'IMEI', texto: 'Registrar un IMEI y sus teléfonos', ruta: 'imeis.create', permiso: 'imeis.crear', icono: 'ri-sim-card-2-line', color: 'warning' },
        { titulo: 'Vehículo', texto: 'Registrar un vehículo y sus personas', ruta: 'vehiculos.create', permiso: 'vehiculos.crear', icono: 'ri-car-line', color: 'danger' },
    ].filter((registro) => puede(registro.permiso) && route().has(registro.ruta)),
);
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
                </div>
            </div>
        </div>

        <template v-if="registros.length">
            <h5 class="fs-15 mb-3"><i class="ri-add-circle-line align-middle me-1 text-muted" /> Registro rápido</h5>
            <div class="row">
                <div v-for="registro in registros" :key="registro.ruta" class="col-xxl col-lg-4 col-sm-6">
                    <Link :href="route(registro.ruta)" class="card card-animate registro-rapido text-reset" :aria-label="`Registrar ${registro.titulo}`">
                        <div class="card-body d-flex align-items-center gap-3">
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title rounded fs-3" :class="`bg-soft-${registro.color} text-${registro.color}`">
                                    <i :class="registro.icono" />
                                </span>
                            </div>
                            <div class="flex-grow-1 overflow-hidden">
                                <h6 class="mb-1 text-truncate">Registrar {{ registro.titulo }}</h6>
                                <p class="text-muted small mb-0 text-truncate">{{ registro.texto }}</p>
                            </div>
                            <i class="ri-arrow-right-s-line fs-4 text-muted" />
                        </div>
                    </Link>
                </div>
            </div>
        </template>

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

<style scoped>
.registro-rapido {
    display: block;
    border: 1px dashed var(--vz-border-color);
}

.registro-rapido:hover,
.registro-rapido:focus-visible {
    border-color: var(--vz-primary);
}
</style>
