<script setup>
import { computed } from 'vue';
import TablaDatosPersona from '@/Components/Personas/TablaDatosPersona.vue';
import { colorEstado } from '@/Utils/mandamiento';
import { fechaNum, guion, urlArchivo } from '@/Utils/formato';

/**
 * Ficha de un mandamiento (el parcial `mandamientos/partials/_datos.blade.php`): fotos del imputado, archivo del
 * mandamiento, datos, persona y estado de ejecución.
 */
const props = defineProps({
    mandamiento: { type: Object, required: true },
});

const persona = computed(() => props.mandamiento.persona);
const archivo = computed(() => props.mandamiento.archivo_mandamiento);
const acta = computed(() => props.mandamiento.acta_ejecucion);
const esPdf = (adjunto) => (adjunto?.tipo_archivo ?? '').toLowerCase() === 'pdf';
</script>

<template>
    <div v-if="persona" class="row gx-lg-5">
        <div class="col-xl-4 col-md-8 mx-auto">
            <div class="p-2 rounded bg-light">
                <h6>Fotos del imputado</h6>
                <template v-if="persona.multimedia?.length">
                    <a v-for="foto in persona.multimedia" :key="foto.id" :href="urlArchivo(foto.ruta)" target="_blank" rel="noopener">
                        <img :src="urlArchivo(foto.ruta)" alt="Foto del imputado" class="img-fluid d-block rounded mb-1">
                    </a>
                </template>
                <img v-else src="/assets/images/users/user-dummy-img.jpg" alt="Sin foto" class="img-fluid d-block rounded">
                <hr>
                <h6>Imagen/PDF del Mandamiento</h6>
                <div v-if="archivo" class="text-center">
                    <a v-if="esPdf(archivo)" :href="urlArchivo(archivo.ruta)" target="_blank" rel="noopener" class="btn btn-link btn-sm">
                        <i class="ri-file-pdf-line" /> Ver PDF
                    </a>
                    <a v-else :href="urlArchivo(archivo.ruta)" target="_blank" rel="noopener">
                        <img :src="urlArchivo(archivo.ruta)" alt="Imagen del mandamiento" class="img-fluid d-block rounded">
                    </a>
                </div>
                <p v-else class="text-center text-muted py-4 mb-0">No hay imagen del mandamiento</p>
            </div>
        </div>

        <div class="col-xl-8">
            <div class="mt-xl-0 mt-4">
                <h2 class="fs-22">Nro HR: {{ guion(mandamiento.hoja_ruta) }}</h2>
                <h4 class="fs-17">{{ mandamiento.nombre_completo }}</h4>
                <div class="hstack gap-3 flex-wrap">
                    <div class="text-primary"><span class="text-muted fw-medium">C.I.:</span> {{ guion(persona.ci) }}</div>
                    <div class="vr" />
                    <div class="text-muted">TIPO: <span class="text-body fw-medium">{{ guion(mandamiento.tipo_documento) }}</span></div>
                    <div class="vr" />
                    <div class="text-muted">
                        ESTADO:
                        <span class="badge" :class="`bg-soft-${colorEstado(mandamiento.estado)} text-${colorEstado(mandamiento.estado)}`">{{ guion(mandamiento.estado) }}</span>
                    </div>
                </div>
                <div class="text-muted mt-3">Fecha Registro: <span class="text-body fw-medium cifra">{{ fechaNum(mandamiento.created_at) }}</span></div>
                <div class="text-muted mt-2">Delito: <span class="text-body fw-medium">{{ guion(mandamiento.nombre_delito) }}</span></div>

                <div class="row">
                    <div class="col-xl-6 mt-4">
                        <h5 class="fs-14">Tipo Mandamiento:</h5>
                        <div class="text-muted">{{ guion(mandamiento.tipo_mandamiento) }}</div>
                    </div>
                    <div class="col-xl-6 mt-4">
                        <h5 class="fs-14">Juzgado:</h5>
                        <div class="text-muted">{{ guion(mandamiento.nombre_juzgado) }}</div>
                    </div>
                </div>

                <TablaDatosPersona :persona="persona" />

                <h5 class="fs-14 mb-3 mt-5"><i class="ri-file-list-line me-1 align-middle" /> Detalles:</h5>
                <div class="table-responsive">
                    <table class="table mb-0">
                        <tbody>
                            <tr><th scope="row" style="width: 200px">Nro Hoja de Ruta / Memorandum</th><td>{{ guion(mandamiento.hoja_ruta) }}</td></tr>
                            <tr><th scope="row">Asignado a</th><td>{{ guion(mandamiento.asignado) }}</td></tr>
                            <tr><th scope="row">Teléfono</th><td>{{ guion(mandamiento.telefono) }}</td></tr>
                            <tr><th scope="row">Domicilio</th><td style="white-space: pre-line">{{ guion(mandamiento.domicilio) }}</td></tr>
                            <tr><th scope="row">Vehículos</th><td style="white-space: pre-line">{{ guion(mandamiento.vehiculos) }}</td></tr>
                            <tr><th scope="row">Actividades Realizadas</th><td style="white-space: pre-line">{{ guion(mandamiento.actividades_realizadas) }}</td></tr>
                        </tbody>
                    </table>
                </div>

                <h5 class="fs-14 mb-3 mt-5"><i class="ri-file-list-line me-1 align-middle" /> Estado de Ejecución:</h5>
                <div class="table-responsive">
                    <table class="table mb-0">
                        <tbody>
                            <tr><th scope="row" style="width: 200px">Estado</th><td>{{ guion(mandamiento.estado) }}</td></tr>
                            <tr><th scope="row">Fecha Ejecución</th><td class="cifra">{{ fechaNum(mandamiento.fecha_ejecucion) }}</td></tr>
                            <tr><th scope="row">Ejecutado por</th><td>{{ guion(mandamiento.ejecutado_por) }}</td></tr>
                            <tr>
                                <th scope="row">Acta de Ejecución</th>
                                <td>
                                    <a v-if="acta" :href="urlArchivo(acta.ruta)" target="_blank" rel="noopener" class="btn btn-link btn-sm p-0">
                                        <i :class="esPdf(acta) ? 'ri-file-pdf-line' : 'ri-image-line'" /> Ver Acta
                                    </a>
                                    <span v-else class="text-muted">—</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div v-else class="alert alert-warning mb-0">
        <i class="ri-alert-line align-middle me-2" /> No se encontró información de persona relacionada con este mandamiento.
        <h3 class="fs-16 mt-2 mb-0">La persona fue eliminada, consulte con el administrador</h3>
    </div>
</template>
