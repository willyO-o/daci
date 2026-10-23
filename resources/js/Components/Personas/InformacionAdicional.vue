<script setup>
import { computed, ref } from 'vue';
import { fechaNum, guion } from '@/Utils/formato';

/**
 * Pestañas «Información Adicional» (el parcial `personas/partials/_tab-adicional.blade.php`): otros registros,
 * mandamientos, vehículos y teléfonos de la persona, con enlaces a sus fichas por código.
 */
const props = defineProps({
    registros: { type: Array, default: () => [] },
    mandamientos: { type: Array, default: () => [] },
    vehiculos: { type: Array, default: () => [] },
    telefonos: { type: Array, default: () => [] },
    /** Identificador de la consulta en curso, para la auditoría de accesos. */
    identificador: { type: String, default: null },
    /** Abrir las fichas en otra pestaña (cuando se ve dentro de un modal). */
    nuevaPestana: { type: Boolean, default: true },
});

const pestana = ref('registros');

const pestanas = computed(() => [
    { clave: 'registros', titulo: 'Otros Registros', cantidad: props.registros.length },
    { clave: 'mandamientos', titulo: 'Mandamientos', cantidad: props.mandamientos.length },
    { clave: 'vehiculos', titulo: 'Vehiculos', cantidad: props.vehiculos.length },
    { clave: 'telefonos', titulo: 'Telefonos', cantidad: props.telefonos.length },
]);

const enlace = (nombreRuta, codigo) =>
    route().has(nombreRuta) ? route(nombreRuta, { codigo, identificador: props.identificador ?? undefined }) : null;
</script>

<template>
    <div>
        <h5 class="fs-14 mb-3 mt-5"><i class="ri-file-list-line me-1 align-middle" /> Información Adicional:</h5>
        <ul class="nav nav-tabs nav-tabs-custom nav-success" role="tablist">
            <li v-for="item in pestanas" :key="item.clave" class="nav-item" role="presentation">
                <button type="button" class="nav-link" :class="{ active: pestana === item.clave }" role="tab" :aria-selected="pestana === item.clave" @click="pestana = item.clave">
                    {{ item.titulo }} <span class="badge bg-danger cifra">{{ item.cantidad }}</span>
                </button>
            </li>
        </ul>
        <div class="tab-content border border-top-0 p-3">
            <div v-if="pestana === 'registros'" class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Nro</th><th>Fecha</th><th>Edad Aproximada</th><th>Especialidad</th><th /></tr></thead>
                    <tbody>
                        <tr v-for="registro in registros" :key="registro.id">
                            <th scope="row" class="cifra">{{ registro.nro_registro }}</th>
                            <td class="cifra">{{ fechaNum(registro.fecha_registro) }}</td>
                            <td>{{ guion(registro.edad_aproximada) }}</td>
                            <td>{{ guion(registro.especialidad) }}</td>
                            <td class="text-end">
                                <a v-if="enlace('registro-criminal.showByCodigo', registro.codigo)" :href="enlace('registro-criminal.showByCodigo', registro.codigo)" :target="nuevaPestana ? '_blank' : undefined" class="btn btn-sm btn-primary">Ver Detalles</a>
                            </td>
                        </tr>
                        <tr v-if="!registros.length"><td colspan="5" class="text-center text-muted py-4">No se encontraron más registros.</td></tr>
                    </tbody>
                </table>
            </div>

            <div v-else-if="pestana === 'mandamientos'" class="table-responsive">
                <table class="table table-sm mb-0 small">
                    <thead><tr><th>Nro</th><th>Delito</th><th>Tipo</th><th>Estado</th><th /></tr></thead>
                    <tbody>
                        <tr v-for="mandamiento in mandamientos" :key="mandamiento.id">
                            <th scope="row">{{ guion(mandamiento.hoja_ruta) }}</th>
                            <td>{{ guion(mandamiento.delito) }}</td>
                            <td>{{ guion(mandamiento.tipo) }}</td>
                            <td>{{ guion(mandamiento.estado) }}</td>
                            <td class="text-end">
                                <a v-if="enlace('mandamientos.showByCodigo', mandamiento.codigo)" :href="enlace('mandamientos.showByCodigo', mandamiento.codigo)" :target="nuevaPestana ? '_blank' : undefined" class="btn btn-sm btn-primary">Ver Detalles</a>
                            </td>
                        </tr>
                        <tr v-if="!mandamientos.length"><td colspan="5" class="text-center text-muted py-4">No se encontraron mandamientos.</td></tr>
                    </tbody>
                </table>
            </div>

            <div v-else-if="pestana === 'vehiculos'" class="table-responsive">
                <table class="table table-sm mb-0 small">
                    <thead><tr><th>Nro de Placa</th><th>Responsable</th><th>Caso Relacionado</th><th>Descripción</th></tr></thead>
                    <tbody>
                        <tr v-for="vehiculo in vehiculos" :key="vehiculo.id">
                            <th scope="row">{{ vehiculo.placa }}</th>
                            <td>{{ guion(vehiculo.responsable) }}</td>
                            <td>{{ guion(vehiculo.caso_relacionado) }}</td>
                            <td>{{ guion(vehiculo.descripcion) }}</td>
                        </tr>
                        <tr v-if="!vehiculos.length"><td colspan="4" class="text-center text-muted py-4">No se encontraron vehículos.</td></tr>
                    </tbody>
                </table>
            </div>

            <div v-else class="table-responsive">
                <table class="table table-sm mb-0 small">
                    <thead><tr><th>Nro de Celular</th><th>Persona Caso</th><th>Caso</th><th>Empresa</th></tr></thead>
                    <tbody>
                        <tr v-for="telefono in telefonos" :key="telefono.id">
                            <th scope="row" class="cifra">{{ telefono.numero_celular }}</th>
                            <td>{{ guion(telefono.persona_caso) }}</td>
                            <td>{{ guion(telefono.caso) }}</td>
                            <td>{{ guion(telefono.empresa) }}</td>
                        </tr>
                        <tr v-if="!telefonos.length"><td colspan="4" class="text-center text-muted py-4">No se encontraron teléfonos.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
