<script setup>
import { computed } from 'vue';
import InformacionAdicional from '@/Components/Personas/InformacionAdicional.vue';
import TablaDatosPersona from '@/Components/Personas/TablaDatosPersona.vue';
import { ciCompleto, fechaNum, guion, urlArchivo } from '@/Utils/formato';

/**
 * Ficha de un registro criminal (`registro-criminal/partials/_datos.blade.php`): fotos de frente y perfil, datos
 * personales, datos del registro e información adicional de la persona.
 */
const props = defineProps({
    registro: { type: Object, required: true },
    identificador: { type: String, default: null },
    nuevaPestana: { type: Boolean, default: true },
});

const persona = computed(() => props.registro.persona);

const filas = computed(() => [
    ['Nro Registro', props.registro.nro_registro],
    ['Nombre Supuesto', props.registro.nombre_supuesto],
    ['Teléfono', props.registro.telefono],
    ['Edad Aproximada', props.registro.edad_aproximada],
    ['Alias', props.registro.alias],
    ['Especialidad', props.registro.especialidad],
    ['Modus Operandi', props.registro.modus_operandi],
    ['Zonas de Operación', props.registro.zonas_opera],
    ['División', props.registro.division],
    ['Observaciones', props.registro.observaciones],
    ['Rasgos Distintivos', props.registro.rasgos],
    ['Estatura', props.registro.estatura],
    ['Peso', props.registro.peso],
    ['CUD', props.registro.cud],
    ['Características Particulares', props.registro.caracteristicas_particulares],
    ['Nombre del Cónyuge', props.registro.nombre_conyuge],
    ['Hijos', props.registro.hijos],
    ['Domicilio', props.registro.domicilio],
]);
</script>

<template>
    <div v-if="persona" class="row gx-lg-5">
        <div class="col-xl-4 col-md-8 mx-auto">
            <div class="p-2 rounded bg-light">
                <h6>Fotos de frente</h6>
                <a v-if="registro.foto_frente" :href="urlArchivo(registro.foto_frente)" target="_blank" rel="noopener">
                    <img :src="urlArchivo(registro.foto_frente)" alt="Foto de frente" class="img-fluid d-block rounded mb-3">
                </a>
                <p v-else class="text-muted text-center py-4">Sin foto de frente</p>
                <h6>Fotos de perfil</h6>
                <a v-if="registro.foto_perfil" :href="urlArchivo(registro.foto_perfil)" target="_blank" rel="noopener">
                    <img :src="urlArchivo(registro.foto_perfil)" alt="Foto de perfil" class="img-fluid d-block rounded">
                </a>
                <p v-else class="text-muted text-center py-4 mb-0">Sin foto de perfil</p>
            </div>
        </div>

        <div class="col-xl-8">
            <div class="mt-xl-0 mt-4">
                <h2 class="fs-22">Nro registro: <span class="cifra">{{ registro.nro_registro }}</span></h2>
                <h4 class="fs-17">{{ persona.nombres }} {{ persona.apellidos }}</h4>
                <div class="hstack gap-3 flex-wrap">
                    <div class="text-primary"><span class="text-muted fw-medium">C.I.:</span> {{ ciCompleto(persona) }}</div>
                    <div class="vr" />
                    <div class="text-muted">Especialidad: <span class="text-primary fw-medium">{{ guion(registro.especialidad) }}</span></div>
                    <div class="vr" />
                    <div class="text-muted">Registrado por: {{ guion(registro.registrado_por) }}</div>
                </div>
                <div class="text-muted mt-3">Fecha Registro: <span class="text-body fw-medium cifra">{{ fechaNum(registro.fecha_registro) }}</span></div>

                <TablaDatosPersona :persona="persona" />

                <h5 class="fs-14 mb-3 mt-5"><i class="ri-file-list-line me-1 align-middle" /> Datos del Registro:</h5>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <tbody>
                            <tr v-for="[etiqueta, valor] in filas" :key="etiqueta">
                                <th scope="row" style="width: 200px">{{ etiqueta }}</th>
                                <td style="white-space: pre-line">{{ guion(valor) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <InformacionAdicional
                    :registros="registro.otros_registros ?? []"
                    :mandamientos="registro.mandamientos ?? []"
                    :vehiculos="registro.vehiculos ?? []"
                    :telefonos="registro.telefonos ?? []"
                    :identificador="identificador"
                    :nueva-pestana="nuevaPestana"
                />
            </div>
        </div>
    </div>

    <div v-else class="alert alert-warning mb-0">
        <i class="ri-alert-line align-middle me-2" />
        <h3 class="fs-16 d-inline">La persona fue eliminada, consulte con el administrador</h3>
    </div>
</template>
