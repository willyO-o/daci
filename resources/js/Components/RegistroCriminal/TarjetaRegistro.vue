<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { fechaNum, guion, urlArchivo } from '@/Utils/formato';

/** Tarjeta de un registro criminal en el listado y en la consulta (cuadrícula o lista). La usan también Faltas y
 * Contravenciones, que pasan su ruta de edición. */
const props = defineProps({
    registro: { type: Object, required: true },
    vista: { type: String, default: 'grid' },
    puedeEditar: { type: Boolean, default: false },
    puedeEliminar: { type: Boolean, default: false },
    puedeFicha: { type: Boolean, default: false },
    /** Ruta de edición del módulo (por defecto, Registro Criminal). */
    rutaEditar: { type: String, default: 'registro-criminal.edit' },
});

defineEmits(['ver', 'eliminar', 'ficha', 'ver-imagenes']);

const persona = computed(() => props.registro.persona);
const foto = computed(() => urlArchivo(props.registro.foto_frente) ?? '/assets/images/users/user-dummy-img.jpg');
const ci = computed(() => `${persona.value.ci ?? '-'}${persona.value.complemento ? ` - ${persona.value.complemento}` : ''}`);
</script>

<template>
    <div :class="vista === 'grid' ? 'col-xxl-4 col-md-6 mb-3' : 'col-12 mb-2'">
        <div class="card h-100 mb-0 border" data-tour="tarjeta">
            <div class="card-header border-0 pb-0 pt-3 d-sm-flex align-items-center">
                <h4 class="card-title mb-0 flex-grow-1">NRO: <span class="cifra">{{ guion(registro.nro_registro) }}</span></h4>
                <div class="d-flex gap-1 mt-2 mt-sm-0">
                    <button type="button" class="btn btn-soft-secondary btn-sm" title="Ver Detalles" data-tour="tarjeta-ver" @click="$emit('ver', registro)"><i class="ri-eye-line" /></button>
                    <button v-if="puedeFicha" type="button" class="btn btn-soft-secondary btn-sm" title="Imprimir Ficha de Registro" @click="$emit('ficha', registro)"><i class="ri-file-pdf-line" /></button>
                    <Link v-if="puedeEditar" :href="route(rutaEditar, registro.id)" class="btn btn-soft-secondary btn-sm" title="Editar Registro"><i class="ri-pencil-line" /></Link>
                    <button v-if="puedeEliminar" type="button" class="btn btn-soft-secondary btn-sm" title="Eliminar Registro" @click="$emit('eliminar', registro)"><i class="ri-delete-bin-2-line" /></button>
                </div>
            </div>
            <div class="card-body">
                <div :class="vista === 'grid' ? 'd-sm-flex align-items-center' : 'd-md-flex align-items-center gap-3'">
                    <div class="flex-shrink-0">
                        <button type="button" class="btn p-0 border-0" :disabled="!registro.fotos?.length" :aria-label="registro.fotos?.length ? 'Ver fotos' : 'Sin fotos'" data-tour="tarjeta-foto" @click="$emit('ver-imagenes', registro.fotos)">
                            <img :src="foto" alt="Foto de la persona" :class="vista === 'grid' ? 'avatar-xl' : 'avatar-lg'" class="rounded" style="object-fit: cover">
                        </button>
                    </div>
                    <div class="flex-grow-1" :class="vista === 'grid' ? 'ms-sm-3 mt-3 mt-sm-0' : 'my-3 my-md-0'">
                        <h5 class="fs-16 mb-1">{{ persona.nombres }} {{ persona.apellidos }}<span v-if="vista === 'grid' && persona.genero" class="text-muted fw-normal"> - {{ persona.genero }}</span></h5>
                        <h6 class="mb-1">C.I.: <strong class="cifra">{{ ci }}</strong></h6>
                        <p class="mb-1">Alias: <strong>{{ guion(registro.alias) }}</strong></p>
                        <template v-if="vista === 'grid'">
                            <p class="mb-1">Nombre Sup: <strong>{{ guion(registro.nombre_supuesto) }}</strong></p>
                            <div>Especialidad: <strong>{{ guion(registro.especialidad) }}</strong></div>
                            <div class="mt-1 text-muted">Nacionalidad: {{ guion(persona.nacionalidad) }}</div>
                            <div class="mt-1 text-muted">Edad: {{ persona.edad ?? '-' }}</div>
                        </template>
                        <template v-else>
                            <p class="text-muted mb-0">Edad: <strong>{{ persona.edad ?? '-' }}</strong> · Nacionalidad: <strong>{{ guion(persona.nacionalidad) }}</strong></p>
                        </template>
                    </div>
                    <div v-if="vista === 'list'" class="text-muted">Nombre sup:<br><strong>{{ guion(registro.nombre_supuesto) }}</strong></div>
                    <div v-if="vista === 'list'" class="text-muted">Especialidad:<br><strong>{{ guion(registro.especialidad) }}</strong></div>
                    <p class="mb-0 mt-1 text-nowrap"><i class="ri-calendar-line text-primary me-1 align-bottom" /> <strong class="cifra">{{ fechaNum(registro.fecha_registro) }}</strong></p>
                </div>
            </div>
        </div>
    </div>
</template>
