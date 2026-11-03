<script setup>
import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import PageHeader from '@/Components/Layout/PageHeader.vue';
import BaseModal from '@/Components/Ui/BaseModal.vue';
import CampoError from '@/Components/Ui/CampoError.vue';
import EstadoVacio from '@/Components/Ui/EstadoVacio.vue';
import { ICONO_GRUPO } from '@/Utils/evidencias';
import { cifra } from '@/Utils/formato';

/**
 * Catálogo de tipos de artículo. El grupo decide qué campos pide el formulario de evidencias; un tipo en uso no cambia
 * de grupo (sus artículos perderían esos datos al editarlos) y no se borra: se inactiva.
 */
const props = defineProps({
    tipos: { type: Array, default: () => [] },
    grupos: { type: Object, required: true },
});

const modal = ref(false);
const editando = ref(null);
const form = useForm({ nombre: '', grupo: 'OTRO', estado: 'ACTIVO' });
const grupoBloqueado = computed(() => Boolean(editando.value?.articulos_count));

const abrir = (tipo = null) => {
    editando.value = tipo;
    form.defaults(tipo ? { nombre: tipo.nombre, grupo: tipo.grupo, estado: tipo.estado } : { nombre: '', grupo: 'OTRO', estado: 'ACTIVO' });
    form.reset();
    form.clearErrors();
    modal.value = true;
};

const guardar = () => {
    const opciones = { preserveScroll: true, onSuccess: () => (modal.value = false) };
    if (editando.value) {
        form.put(route('tipos-articulo.update', editando.value.id), opciones);
    } else {
        form.post(route('tipos-articulo.store'), opciones);
    }
};

const ordenados = computed(() => [...props.tipos].sort((a, b) => (a.estado === b.estado ? a.nombre.localeCompare(b.nombre) : a.estado === 'ACTIVO' ? -1 : 1)));
</script>

<template>
    <div>
        <PageHeader titulo="Tipos de artículo" :migas="[{ etiqueta: 'Evidencias', ruta: 'evidencias.index' }, { etiqueta: 'Tipos de artículo' }]">
            <template #acciones>
                <button type="button" class="btn btn-primary" @click="abrir()"><i class="ri-add-line align-middle me-1" /> Nuevo tipo</button>
            </template>
        </PageHeader>

        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th scope="col">Tipo</th>
                                <th scope="col">Campos que pide</th>
                                <th scope="col" class="text-end">Artículos</th>
                                <th scope="col">Estado</th>
                                <th scope="col" class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="tipo in ordenados" :key="tipo.id" :class="{ 'text-muted': tipo.estado === 'INACTIVO' }">
                                <td><i :class="ICONO_GRUPO[tipo.grupo]" class="me-2 text-primary" aria-hidden="true" /><span class="fw-semibold">{{ tipo.nombre }}</span></td>
                                <td>{{ grupos[tipo.grupo]?.etiqueta ?? tipo.grupo }}</td>
                                <td class="text-end cifra">{{ cifra(tipo.articulos_count) }}</td>
                                <td><span class="badge" :class="tipo.estado === 'ACTIVO' ? 'bg-soft-success text-success' : 'bg-soft-secondary text-secondary'">{{ tipo.estado === 'ACTIVO' ? 'Activo' : 'Inactivo' }}</span></td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm btn-soft-secondary" :title="`Editar ${tipo.nombre}`" @click="abrir(tipo)"><i class="ri-pencil-fill align-bottom" /></button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <EstadoVacio v-if="!tipos.length" titulo="No hay tipos de artículo" />
            </div>
        </div>

        <BaseModal v-model="modal" :titulo="editando ? 'Editar tipo de artículo' : 'Nuevo tipo de artículo'" centrado estatico>
            <form id="formTipoArticulo" novalidate @submit.prevent="guardar">
                <div class="mb-3">
                    <label for="tipoNombre" class="form-label">Nombre <span class="text-danger">*</span></label>
                    <input id="tipoNombre" v-model="form.nombre" v-mayuscula type="text" maxlength="100" class="form-control" :class="{ 'is-invalid': form.errors.nombre }" placeholder="Ej: CÁMARA FOTOGRÁFICA">
                    <CampoError :mensaje="form.errors.nombre" />
                </div>
                <div class="mb-3">
                    <label for="tipoGrupo" class="form-label">Campos que pide</label>
                    <select id="tipoGrupo" v-model="form.grupo" class="form-select" :class="{ 'is-invalid': form.errors.grupo }" :disabled="grupoBloqueado">
                        <option v-for="(grupo, clave) in grupos" :key="clave" :value="clave">{{ grupo.etiqueta }}</option>
                    </select>
                    <CampoError :mensaje="form.errors.grupo" />
                    <small v-if="grupoBloqueado" class="text-muted d-block mt-1">Ya tiene artículos registrados: el grupo no se puede cambiar.</small>
                </div>
                <div class="form-check form-switch">
                    <input id="tipoActivo" class="form-check-input" type="checkbox" :checked="form.estado === 'ACTIVO'" @change="form.estado = $event.target.checked ? 'ACTIVO' : 'INACTIVO'">
                    <label class="form-check-label" for="tipoActivo">Activo (se ofrece al registrar)</label>
                </div>
            </form>
            <template #pie="{ cerrar }">
                <button type="button" class="btn btn-light" @click="cerrar">Cancelar</button>
                <button type="submit" form="formTipoArticulo" class="btn btn-primary" :disabled="form.processing"><i class="ri-save-3-line align-middle me-1" /> Guardar</button>
            </template>
        </BaseModal>
    </div>
</template>
