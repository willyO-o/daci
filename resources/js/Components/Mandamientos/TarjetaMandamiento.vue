<script setup>
import { computed } from 'vue';
import { colorEstado } from '@/Utils/mandamiento';
import { guion, urlArchivo } from '@/Utils/formato';

/**
 * Tarjeta de un mandamiento en el listado y en la consulta, en vista de cuadrícula o de lista (igual que el
 * sistema anterior). Las acciones visibles las decide quien la usa.
 */
const props = defineProps({
    mandamiento: { type: Object, required: true },
    vista: { type: String, default: 'grid' },
    puedeEditar: { type: Boolean, default: false },
    puedeEliminar: { type: Boolean, default: false },
});

defineEmits(['ver', 'editar', 'eliminar', 'ver-imagenes']);

const foto = computed(() => urlArchivo(props.mandamiento.imagenes_persona?.[0]) ?? '/assets/images/users/user-dummy-img.jpg');
const tieneFotos = computed(() => (props.mandamiento.imagenes_persona?.length ?? 0) > 0);
const archivo = computed(() => props.mandamiento.archivo_mandamiento);
const esPdf = computed(() => (archivo.value?.tipo_archivo ?? '').toLowerCase() === 'pdf');
const badge = computed(() => `bg-soft-${colorEstado(props.mandamiento.estado)} text-${colorEstado(props.mandamiento.estado)}`);
</script>

<template>
    <div :class="vista === 'grid' ? 'col-xxl-4 col-md-6 mb-3' : 'col-12 mb-2'">
        <div class="card h-100 mb-0 border">
            <div class="card-header border-0 pb-0 pt-3 d-sm-flex align-items-center">
                <h4 class="card-title mb-0 flex-grow-1">HR: {{ guion(mandamiento.hoja_ruta) }}</h4>
                <div class="d-flex gap-1 mt-2 mt-sm-0">
                    <button type="button" class="btn btn-soft-secondary btn-sm" title="Ver Detalles" @click="$emit('ver', mandamiento)">
                        <i class="ri-eye-line" />
                    </button>
                    <button v-if="puedeEditar" type="button" class="btn btn-soft-secondary btn-sm" title="Editar Mandamiento" @click="$emit('editar', mandamiento)">
                        <i class="ri-pencil-line" />
                    </button>
                    <button v-if="puedeEliminar" type="button" class="btn btn-soft-secondary btn-sm" title="Eliminar Mandamiento" @click="$emit('eliminar', mandamiento)">
                        <i class="ri-delete-bin-2-line" />
                    </button>
                    <template v-if="archivo">
                        <a v-if="esPdf" :href="urlArchivo(archivo.ruta)" target="_blank" rel="noopener" class="btn btn-soft-secondary btn-sm" title="Ver PDF del mandamiento">
                            <i class="ri-file-pdf-line" />
                        </a>
                        <button v-else type="button" class="btn btn-soft-secondary btn-sm" title="Ver imagen mandamiento" @click="$emit('ver-imagenes', [archivo.ruta])">
                            <i class="ri-image-line" />
                        </button>
                    </template>
                </div>
            </div>

            <div class="card-body">
                <div v-if="vista === 'grid'" class="d-sm-flex align-items-center">
                    <div class="flex-shrink-0">
                        <button type="button" class="btn p-0 border-0" :disabled="!tieneFotos" :aria-label="tieneFotos ? 'Ver fotos de la persona' : 'Sin fotos'" @click="$emit('ver-imagenes', mandamiento.imagenes_persona)">
                            <img :src="foto" alt="Foto de la persona" class="avatar-xl rounded" style="object-fit: cover">
                        </button>
                    </div>
                    <div class="flex-grow-1 ms-sm-3 mt-3 mt-sm-0">
                        <h5 class="fs-16 mb-1">{{ mandamiento.nombre_completo }}</h5>
                        <h6 class="text-muted mb-2">C.I.: <strong class="cifra">{{ guion(mandamiento.ci) }}</strong></h6>
                        <p class="text-muted mb-2">Delito: <strong>{{ guion(mandamiento.nombre_delito) }}</strong></p>
                        <p class="text-muted mb-2"><strong>{{ guion(mandamiento.tipo_mandamiento) }}</strong></p>
                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            Estado: <span class="badge" :class="badge">{{ guion(mandamiento.estado) }}</span>
                        </div>
                        <div class="mt-2 text-muted"><i class="ri-scales-line text-primary me-1 align-bottom" /> {{ mandamiento.nombre_juzgado }}</div>
                    </div>
                </div>

                <div v-else class="d-md-flex align-items-center gap-3">
                    <div class="flex-shrink-0">
                        <button type="button" class="btn p-0 border-0" :disabled="!tieneFotos" :aria-label="tieneFotos ? 'Ver fotos de la persona' : 'Sin fotos'" @click="$emit('ver-imagenes', mandamiento.imagenes_persona)">
                            <img :src="foto" alt="Foto de la persona" class="avatar-lg rounded" style="object-fit: cover">
                        </button>
                    </div>
                    <div class="flex-grow-1 my-3 my-md-0">
                        <h5 class="fs-16 mb-1">{{ mandamiento.nombre_completo }}</h5>
                        <h6 class="text-muted mb-1">C.I.: <strong class="cifra">{{ guion(mandamiento.ci) }}</strong></h6>
                        <p class="text-muted mb-0">Delito: <strong>{{ guion(mandamiento.nombre_delito) }}</strong></p>
                    </div>
                    <div class="text-muted"><i class="ri-auction-line text-primary me-1 align-bottom" /> {{ guion(mandamiento.tipo_mandamiento) }}</div>
                    <div class="text-muted"><i class="ri-scales-line text-primary me-1 align-bottom" /> {{ mandamiento.nombre_juzgado }}</div>
                    <div class="text-nowrap">Estado: <span class="badge" :class="badge">{{ guion(mandamiento.estado) }}</span></div>
                </div>
            </div>
        </div>
    </div>
</template>
