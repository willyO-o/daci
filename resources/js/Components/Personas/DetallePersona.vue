<script setup>
import InformacionAdicional from '@/Components/Personas/InformacionAdicional.vue';
import TablaDatosPersona from '@/Components/Personas/TablaDatosPersona.vue';
import { ciCompleto, fechaNum, urlArchivo } from '@/Utils/formato';

/**
 * Ficha completa de una persona (lo que mostraba `personas/show.blade.php`): fotos, datos personales, Segip,
 * documento y las pestañas de información adicional. La usan el modal del listado y la página de detalle.
 */
defineProps({
    persona: { type: Object, required: true },
    /** Identificador de la consulta en curso, para la auditoría de accesos (se pasa a los enlaces de detalle). */
    identificador: { type: String, default: null },
    /** Abrir los detalles de registros y mandamientos en otra pestaña (cuando se ve dentro de un modal). */
    nuevaPestana: { type: Boolean, default: true },
});
</script>

<template>
    <div class="row gx-lg-5">
        <div class="col-xl-4 col-md-8 mx-auto">
            <div class="p-2 rounded bg-light">
                <h6>Fotos de la Persona</h6>
                <template v-if="persona.multimedia?.length">
                    <a v-for="foto in persona.multimedia" :key="foto.id" :href="urlArchivo(foto.ruta)" target="_blank" rel="noopener">
                        <img :src="urlArchivo(foto.ruta)" alt="Foto de la persona" class="img-fluid d-block rounded mb-2">
                    </a>
                </template>
                <div v-else class="alert alert-warning text-center mb-0">No se encontraron fotos disponibles.</div>
            </div>
        </div>

        <div class="col-xl-8">
            <div class="mt-xl-0 mt-4">
                <h2 class="fs-22">{{ persona.nombres }} {{ persona.apellidos }}</h2>
                <div class="hstack gap-3 flex-wrap">
                    <div class="text-primary"><span class="text-muted fw-medium">C.I.:</span> {{ ciCompleto(persona) }}</div>
                    <div class="vr" />
                    <div class="text-muted">REGISTRADO POR: {{ persona.registrado_por ?? '—' }}</div>
                    <span v-if="persona.deleted_at" class="badge bg-soft-danger text-danger">Eliminada</span>
                </div>
                <div class="text-muted mt-3">
                    Fecha Registro: <span class="text-body fw-medium cifra">{{ fechaNum(persona.created_at) }}</span>
                </div>

                <TablaDatosPersona :persona="persona" />

                <div class="mt-4 text-muted">
                    <h5 class="fs-14">Datos Segip:</h5>
                    <p class="mb-0" style="white-space: pre-line">{{ persona.datos_segip || '—' }}</p>
                </div>
                <hr>
                <div class="mt-3 text-muted">
                    <h5 class="fs-14">Url documento:</h5>
                    <p class="mb-0 text-break">
                        <a v-if="persona.url_documento" :href="persona.url_documento" target="_blank" rel="noopener">{{ persona.url_documento }}</a>
                        <template v-else>No agregado</template>
                    </p>
                </div>

                <InformacionAdicional
                    :registros="persona.registro_criminal ?? []"
                    :mandamientos="persona.mandamientos ?? []"
                    :vehiculos="persona.vehiculos ?? []"
                    :telefonos="persona.telefonos ?? []"
                    :identificador="identificador"
                    :nueva-pestana="nuevaPestana"
                />
            </div>
        </div>
    </div>
</template>
