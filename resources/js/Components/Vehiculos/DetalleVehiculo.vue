<script setup>
import { computed } from 'vue';
import { cifra, fechaHora, guion, monto, urlArchivo } from '@/Utils/formato';

/**
 * Ficha del vehículo (`vehiculos/detalles.blade.php`): placa, fotos, personas por tipo de registro, caso y
 * responsable, carguíos (el último resaltado) e inspecciones técnicas (ITV).
 */
const props = defineProps({
    vehiculo: { type: Object, required: true },
});

const cargios = computed(() => props.vehiculo.cargios ?? []);
const inspecciones = computed(() => props.vehiculo.inspecciones ?? []);
</script>

<template>
    <div>
        <div class="bg-soft-success rounded text-center py-3 mb-4">
            <h1 class="fs-24 fw-semibold mb-0 text-body" style="letter-spacing: 2px">{{ vehiculo.placa }}</h1>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-md-6">
                <div class="row g-2">
                    <div v-for="foto in vehiculo.multimedia" :key="foto.id" class="col-6">
                        <a :href="urlArchivo(foto.ruta)" target="_blank" rel="noopener">
                            <img :src="urlArchivo(foto.ruta)" alt="Foto del vehículo" class="img-fluid rounded border w-100" style="height: 200px; object-fit: cover">
                        </a>
                    </div>
                    <div v-if="!vehiculo.multimedia?.length" class="col-12">
                        <div class="border rounded bg-light d-flex flex-column align-items-center justify-content-center text-muted" style="min-height: 200px">
                            <i class="ri-image-2-line fs-2" /><small class="mt-2">Sin fotos</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <p class="mb-2">Placa: <strong>{{ vehiculo.placa }}</strong></p>
                <p style="white-space: pre-line">{{ vehiculo.descripcion || 'Sin descripción disponible' }}</p>
            </div>
        </div>

        <div class="table-responsive mb-4">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr><th class="text-center">Tipo de Registro</th><th class="text-center">Nombre</th><th class="text-center">C.I</th><th class="text-center">Teléfono</th></tr>
                </thead>
                <tbody>
                    <tr v-for="persona in vehiculo.personas" :key="`${persona.id}-${persona.pivot?.tipo}`">
                        <th class="text-center">{{ persona.pivot?.tipo }}</th>
                        <td>{{ persona.nombres }} {{ persona.apellidos }}</td>
                        <td class="cifra">{{ guion(persona.ci) }}</td>
                        <td class="cifra">{{ guion(persona.telefono) }}</td>
                    </tr>
                    <tr v-if="!vehiculo.personas?.length"><td colspan="4" class="text-center text-muted">No hay personas vinculadas a este vehículo.</td></tr>
                </tbody>
            </table>
        </div>

        <div class="row g-3 mb-4 pb-3 border-bottom">
            <div class="col-md-6">
                <h5 class="fs-13 bg-soft-success text-body rounded px-2 py-2 mb-2">Caso Relacionado</h5>
                <div class="bg-light rounded px-3 py-2 fw-semibold">{{ guion(vehiculo.caso_relacionado) }}</div>
            </div>
            <div class="col-md-6">
                <h5 class="fs-13 bg-soft-success text-body rounded px-2 py-2 mb-2">Responsable</h5>
                <div class="bg-light rounded px-3 py-2">{{ guion(vehiculo.responsable) }}</div>
            </div>
        </div>

        <h5 class="fs-13 bg-soft-success text-body rounded px-2 py-2 text-center mb-2">
            CARGUIOS <span class="badge bg-primary ms-1 cifra">{{ cifra(cargios.length) }}</span>
        </h5>
        <div class="table-responsive mb-4" style="max-height: 320px">
            <table class="table table-sm align-middle mb-0 small">
                <thead class="table-light">
                    <tr><th>NIT CONSUMIDOR</th><th>RAZÓN SOCIAL</th><th>ESTACIÓN</th><th>DEPARTAMENTO</th><th class="text-end">MONTO BS</th><th class="text-end">LITROS</th><th>FECHA</th></tr>
                </thead>
                <tbody>
                    <tr v-for="(cargio, indice) in cargios" :key="cargio.id" :class="{ 'table-warning': indice === 0 }">
                        <td class="cifra">{{ guion(cargio.nit_consumidor) }}</td>
                        <td>{{ guion(cargio.razon_social) }}</td>
                        <td>{{ guion(cargio.estacion_servicio?.eess) }}</td>
                        <td>{{ guion(cargio.departamento) }}</td>
                        <td class="text-end cifra">{{ monto(cargio.monto) }}</td>
                        <td class="text-end cifra">{{ monto(cargio.cantidad) }}</td>
                        <td class="cifra text-nowrap">
                            {{ fechaHora(cargio.fecha_venta) }}
                            <template v-if="indice === 0">
                                <span class="badge bg-soft-warning text-warning ms-1">Último Carguío</span>
                                <span v-if="vehiculo.ultimo_cargio_hace" class="d-block small text-muted">{{ vehiculo.ultimo_cargio_hace }}</span>
                            </template>
                        </td>
                    </tr>
                    <tr v-if="!cargios.length"><td colspan="7" class="text-center text-muted py-3">No hay carguíos registrados para este vehículo.</td></tr>
                </tbody>
            </table>
        </div>

        <h5 class="fs-13 bg-soft-success text-body rounded px-2 py-2 text-center mb-2">
            ITV <span class="badge bg-primary ms-1 cifra">{{ cifra(inspecciones.length) }}</span>
        </h5>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0 small">
                <thead class="table-light">
                    <tr><th>AÑO</th><th>NOMBRE</th><th>C.I.</th><th>TELÉFONO</th><th>DETALLE</th></tr>
                </thead>
                <tbody>
                    <tr v-for="(inspeccion, indice) in inspecciones" :key="inspeccion.id" :class="{ 'table-warning': indice === 0 }">
                        <td class="cifra">{{ guion(inspeccion.anio) }}</td>
                        <td>{{ inspeccion.persona ? `${inspeccion.persona.nombres} ${inspeccion.persona.apellidos ?? ''}` : '—' }}</td>
                        <td class="cifra">{{ guion(inspeccion.persona?.ci) }}</td>
                        <td class="cifra">{{ guion(inspeccion.persona?.telefono) }}</td>
                        <td>{{ guion(inspeccion.resultado) }}</td>
                    </tr>
                    <tr v-if="!inspecciones.length"><td colspan="5" class="text-center text-muted py-3">No hay inspecciones técnicas vinculadas a este vehículo.</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
