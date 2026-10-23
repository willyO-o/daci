<script setup>
import { ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import PageHeader from '@/Components/Layout/PageHeader.vue';
import Avatar from '@/Components/Ui/Avatar.vue';
import BaseModal from '@/Components/Ui/BaseModal.vue';
import CampoError from '@/Components/Ui/CampoError.vue';
import EstadoVacio from '@/Components/Ui/EstadoVacio.vue';
import FilasCargando from '@/Components/Ui/FilasCargando.vue';
import Paginacion from '@/Components/Ui/Paginacion.vue';
import DetallePersona from '@/Components/Personas/DetallePersona.vue';
import ModalEliminarPersona from '@/Components/Personas/ModalEliminarPersona.vue';
import ModalPersona from '@/Components/Personas/ModalPersona.vue';
import { useListado } from '@/Composables/useListado';
import { useNotificacion } from '@/Composables/useNotificacion';
import { usePermisos } from '@/Composables/usePermisos';
import { ESTADOS_CIVILES, GENEROS, ciCompleto, fecha, guion, nombreCompleto } from '@/Utils/formato';
import { http } from '@/Utils/http';

const props = defineProps({
    personas: { type: Object, required: true },
    paises: { type: Array, required: true },
    filtros: { type: Object, default: () => ({}) },
});

const { puede } = usePermisos();
const { confirmar, errorDeRespuesta } = useNotificacion();

const { filtros, cargando, irAPagina, recargar } = useListado(
    'personas.index',
    {
        search: props.filtros.search ?? '',
        filtro: props.filtros.filtro ?? '',
        genero: props.filtros.genero ?? '',
        estado_civil: props.filtros.estado_civil ?? '',
        visible: props.filtros.visible ?? 'activos',
    },
    ['personas'],
);

const numero = (indice) => (props.personas.from ?? 1) + indice;

// Alta / edición
const modalPersona = ref(false);
const personaEditar = ref(null);
const nueva = () => {
    personaEditar.value = null;
    modalPersona.value = true;
};
const editar = (persona) => {
    personaEditar.value = persona;
    modalPersona.value = true;
};

// Detalle
const modalDetalle = ref(false);
const detalle = ref(null);
const cargandoDetalle = ref(false);
const verDetalle = async (persona) => {
    detalle.value = null;
    modalDetalle.value = true;
    cargandoDetalle.value = true;
    try {
        const { data } = await http.get(route('personas.show', persona.id));
        detalle.value = data;
    } catch (err) {
        modalDetalle.value = false;
        errorDeRespuesta(err);
    } finally {
        cargandoDetalle.value = false;
    }
};

// Eliminar
const modalEliminar = ref(false);
const personaEliminar = ref(null);
const eliminar = (persona) => {
    personaEliminar.value = persona;
    modalEliminar.value = true;
};

const eliminarDefinitivo = async (persona) => {
    const ok = await confirmar({
        titulo: `¿Eliminar a ${nombreCompleto(persona)} de forma permanente?`,
        texto: 'Se borrarán también sus datos relacionados y fotos. Esta acción no se puede deshacer.',
        confirmar: 'Sí, eliminar',
        peligro: true,
    });
    if (ok) {
        router.delete(route('personas.destroy', persona.id), { data: { eliminar_completo: 1 }, preserveScroll: true, preserveState: true });
    }
};

const restaurar = async (persona) => {
    const ok = await confirmar({
        titulo: `¿Recuperar a ${nombreCompleto(persona)}?`,
        texto: 'Volverá a aparecer en los listados y búsquedas.',
        confirmar: '¡Sí, recuperar!',
        icono: 'question',
    });
    if (ok) {
        router.post(route('personas.restore', persona.id), {}, { preserveScroll: true, preserveState: true });
    }
};

// Vincular documento
const modalDocumento = ref(false);
const personaDocumento = ref(null);
const formDocumento = useForm({ url_documento: '' });
const vincularDocumento = (persona) => {
    personaDocumento.value = persona;
    formDocumento.reset();
    formDocumento.clearErrors();
    modalDocumento.value = true;
};
const guardarDocumento = () => {
    formDocumento.patch(route('personas.documento', personaDocumento.value.id), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => (modalDocumento.value = false),
    });
};
</script>

<template>
    <div>
        <PageHeader titulo="Gestión de Personas" :migas="[{ etiqueta: 'Personas' }]">
            <template v-if="puede('personas.crear')" #acciones>
                <button type="button" class="btn btn-primary" @click="nueva">
                    <i class="ri-add-line align-middle me-1" /> Nueva Persona
                </button>
            </template>
        </PageHeader>

        <div class="card">
            <div class="card-header">
                <div class="row g-2">
                    <div class="col-md-4">
                        <div class="input-group">
                            <span class="input-group-text bg-primary text-white border-primary"><i class="ri-search-line" /></span>
                            <input v-model="filtros.search" type="search" class="form-control" placeholder="Buscar (Nombre, apellido, CI)..." aria-label="Buscar personas">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <select v-model="filtros.filtro" class="form-select" aria-label="Buscar en">
                            <option value="">Filtrar Por</option>
                            <option value="nombre">Nombre</option>
                            <option value="apellidos">Apellidos</option>
                            <option value="ci">C.I.</option>
                            <option value="nombre_padre">Nombre del Padre</option>
                            <option value="nombre_madre">Nombre de la Madre</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select v-model="filtros.genero" class="form-select" aria-label="Género">
                            <option value="">Todos los Géneros</option>
                            <option v-for="(etiqueta, valor) in GENEROS" :key="valor" :value="valor">{{ etiqueta }}</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select v-model="filtros.estado_civil" class="form-select" aria-label="Estado civil">
                            <option value="">Todos los Estados</option>
                            <option v-for="(etiqueta, valor) in ESTADOS_CIVILES" :key="valor" :value="valor">{{ etiqueta }}</option>
                        </select>
                    </div>
                    <div v-if="puede('personas.restaurar')" class="col-md-2">
                        <select v-model="filtros.visible" class="form-select" aria-label="Registros visibles">
                            <option value="todos">Todos los registros</option>
                            <option value="activos">Solo Activos</option>
                            <option value="eliminados">Solo Eliminados</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th scope="col">#</th>
                                <th scope="col">Nombre Completo</th>
                                <th scope="col">C.I.</th>
                                <th scope="col">Alias</th>
                                <th scope="col">Género</th>
                                <th scope="col">Estado Civil</th>
                                <th scope="col">Teléfono</th>
                                <th scope="col">Fecha Registro</th>
                                <th scope="col" class="text-center">Documento</th>
                                <th scope="col" class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <FilasCargando v-if="cargando" :columnas="10" />
                            <template v-else>
                                <tr v-for="(persona, indice) in personas.data" :key="persona.id" :class="{ 'table-danger': persona.deleted_at }">
                                    <td class="text-muted">{{ numero(indice) }}</td>
                                    <td>
                                        <button type="button" class="btn btn-link p-0 text-reset text-start d-flex align-items-center gap-2 text-decoration-none" @click="verDetalle(persona)">
                                            <Avatar :nombre="nombreCompleto(persona)" :ruta="persona.multimedia?.[0]?.ruta" />
                                            <span>
                                                <span class="d-block fw-semibold">{{ nombreCompleto(persona) }}</span>
                                                <small class="text-muted">{{ persona.ocupacion }}</small>
                                            </span>
                                        </button>
                                    </td>
                                    <td class="cifra text-nowrap">{{ ciCompleto(persona) }}</td>
                                    <td>{{ guion(persona.alias) }}</td>
                                    <td>{{ GENEROS[persona.genero] ?? '—' }}</td>
                                    <td>{{ ESTADOS_CIVILES[persona.estado_civil] ?? guion(persona.estado_civil) }}</td>
                                    <td class="cifra">{{ guion(persona.telefono) }}</td>
                                    <td class="cifra text-nowrap">{{ fecha(persona.created_at) }}</td>
                                    <td class="text-center">
                                        <a
                                            v-if="persona.url_documento"
                                            :href="persona.url_documento"
                                            target="_blank"
                                            rel="noopener"
                                            class="btn btn-sm btn-soft-success"
                                            title="Abrir documento"
                                        ><i class="ri-download-cloud-fill align-bottom" /></a>
                                        <button
                                            v-else-if="puede('personas.editar') && !persona.deleted_at"
                                            type="button"
                                            class="btn btn-sm btn-soft-secondary"
                                            title="Vincular documento"
                                            @click="vincularDocumento(persona)"
                                        ><i class="ri-link align-bottom" /></button>
                                        <span v-else class="text-muted">—</span>
                                    </td>
                                    <td class="text-center">
                                        <div class="d-flex gap-2 justify-content-center">
                                            <button type="button" class="btn btn-sm btn-soft-info" title="Ver detalles" :aria-label="`Ver a ${nombreCompleto(persona)}`" @click="verDetalle(persona)">
                                                <i class="ri-eye-fill align-bottom" />
                                            </button>
                                            <button v-if="puede('personas.editar') && !persona.deleted_at" type="button" class="btn btn-sm btn-soft-warning" title="Editar" @click="editar(persona)">
                                                <i class="ri-pencil-fill align-bottom" />
                                            </button>
                                            <template v-if="puede('personas.eliminar')">
                                                <button v-if="!persona.deleted_at" type="button" class="btn btn-sm btn-soft-danger" title="Eliminar" @click="eliminar(persona)">
                                                    <i class="ri-delete-bin-fill align-bottom" />
                                                </button>
                                                <button type="button" class="btn btn-sm btn-soft-dark" title="Eliminar definitivamente" @click="eliminarDefinitivo(persona)">
                                                    <i class="ri-delete-bin-fill align-bottom" />
                                                </button>
                                            </template>
                                            <button v-if="persona.deleted_at && puede('personas.restaurar')" type="button" class="btn btn-sm btn-soft-info" title="Recuperar" @click="restaurar(persona)">
                                                <i class="ri-refresh-line align-bottom" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <EstadoVacio
                    v-if="!cargando && !personas.data.length"
                    titulo="No se encontraron personas"
                    :texto="filtros.search ? 'Revise el texto buscado o cambie el campo de búsqueda.' : ''"
                />

                <Paginacion :paginado="personas" entidad="personas" :deshabilitado="cargando" @ir="irAPagina" />
            </div>
        </div>

        <ModalPersona v-model="modalPersona" :persona="personaEditar" :paises="paises" @fotos-cambiadas="recargar" />
        <ModalEliminarPersona v-model="modalEliminar" :persona="personaEliminar" />

        <BaseModal v-model="modalDetalle" titulo="Detalles de la Persona" tamano="xl" centrado>
            <div v-if="cargandoDetalle" class="text-center py-5">
                <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Cargando…</span></div>
            </div>
            <DetallePersona v-else-if="detalle" :persona="detalle" />
            <template #pie="{ cerrar }">
                <button type="button" class="btn btn-light" @click="cerrar">Cerrar</button>
            </template>
        </BaseModal>

        <BaseModal v-model="modalDocumento" titulo="Vincular Documento" centrado estatico>
            <form id="formDocumento" novalidate @submit.prevent="guardarDocumento">
                <label for="urlDocumento" class="form-label">URL del Documento *</label>
                <input
                    id="urlDocumento"
                    v-model="formDocumento.url_documento"
                    type="url"
                    class="form-control"
                    :class="{ 'is-invalid': formDocumento.errors.url_documento }"
                    placeholder="https://drive.google.com/..."
                >
                <small class="text-muted d-block mt-2">Puedes vincular documentos desde Google Drive, OneDrive, Dropbox o cualquier otra fuente.</small>
                <CampoError :mensaje="formDocumento.errors.url_documento" />
            </form>
            <template #pie="{ cerrar }">
                <button type="button" class="btn btn-secondary" @click="cerrar"><i class="ri-close-line align-middle me-1" /> Cancelar</button>
                <button type="submit" form="formDocumento" class="btn btn-primary" :disabled="formDocumento.processing">
                    <i class="align-middle me-1" :class="formDocumento.processing ? 'ri-loader-4-line' : 'ri-link'" />
                    {{ formDocumento.processing ? 'Guardando…' : 'Vincular' }}
                </button>
            </template>
        </BaseModal>
    </div>
</template>
