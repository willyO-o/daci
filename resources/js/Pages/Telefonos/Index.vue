<script setup>
import { computed, ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import PageHeader from '@/Components/Layout/PageHeader.vue';
import BaseModal from '@/Components/Ui/BaseModal.vue';
import BuscadorRemoto from '@/Components/Ui/BuscadorRemoto.vue';
import CampoError from '@/Components/Ui/CampoError.vue';
import EstadoVacio from '@/Components/Ui/EstadoVacio.vue';
import FilasCargando from '@/Components/Ui/FilasCargando.vue';
import Paginacion from '@/Components/Ui/Paginacion.vue';
import FormularioTelefono from '@/Components/Telefonos/FormularioTelefono.vue';
import { useListado } from '@/Composables/useListado';
import { useNotificacion } from '@/Composables/useNotificacion';
import { usePermisos } from '@/Composables/usePermisos';
import { fecha, fechaHora, guion, nombreCompleto } from '@/Utils/formato';
import { http } from '@/Utils/http';

const props = defineProps({
    telefonos: { type: Object, required: true },
    filtros: { type: Object, default: () => ({}) },
});

const { puede } = usePermisos();
const { confirmar, errorDeRespuesta } = useNotificacion();
const { filtros, cargando, irAPagina } = useListado('telefonos.index', { search: props.filtros.search ?? '', filtro: props.filtros.filtro ?? '' }, ['telefonos']);

const FILTROS = [
    ['numero', 'Número'],
    ['imei', 'IMEI'],
    ['ci_persona', 'CI Persona'],
    ['nombre_persona', 'Nombre Persona'],
    ['callap', 'CallAPP'],
    ['truecall', 'TrueCall'],
    ['uninet', 'Uninet'],
    ['respuesta_requerimiento', 'Respuesta del Requerimiento'],
];

const numero = (indice) => (props.telefonos.from ?? 1) + indice;
const textoPersona = (persona) => `${persona.nombres} ${persona.apellidos ?? ''}${persona.ci ? ` (${persona.ci})` : ''}`;

// Alta / edición: el formulario es `FormularioTelefono` (el mismo de «Registrar Teléfono»).
const modalFormulario = ref(false);
const editando = ref(null);
const formulario = ref(null);

const abrirNuevo = () => {
    editando.value = null;
    modalFormulario.value = true;
};
const abrirEditar = (telefono) => {
    editando.value = telefono;
    modalFormulario.value = true;
};

// Detalle
const modalDetalle = ref(false);
const detalle = ref(null);
const verDetalle = async (telefono) => {
    detalle.value = null;
    modalDetalle.value = true;
    try {
        detalle.value = (await http.get(route('telefonos.show', telefono.id))).data;
    } catch (err) {
        modalDetalle.value = false;
        errorDeRespuesta(err);
    }
};

// Vincular persona
const modalVincular = ref(false);
const telefonoVincular = ref(null);
const formVincular = useForm({ persona_id: null });
const abrirVincular = (telefono) => {
    telefonoVincular.value = telefono;
    formVincular.reset();
    formVincular.clearErrors();
    modalVincular.value = true;
};
const vincular = () => {
    formVincular.post(route('telefonos.vincular-persona', telefonoVincular.value.id), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => (modalVincular.value = false),
    });
};

const eliminar = async (telefono) => {
    if (await confirmar({ titulo: `¿Eliminar el teléfono ${telefono.numero_celular}?`, texto: 'Esta acción no se puede deshacer.', confirmar: 'Sí, eliminar', peligro: true })) {
        router.delete(route('telefonos.destroy', telefono.id), { preserveScroll: true, preserveState: true });
    }
};

const urlPersona = computed(() => (detalle.value?.persona && route().has('personas.show') ? route('personas.show', detalle.value.persona.id) : null));
</script>

<template>
    <div>
        <PageHeader titulo="Gestión de Teléfonos" :migas="[{ etiqueta: 'Teléfonos' }]">
            <template v-if="puede('telefonos.crear')" #acciones>
                <button type="button" class="btn btn-primary" @click="abrirNuevo"><i class="ri-add-line align-middle me-1" /> Nuevo Teléfono</button>
            </template>
        </PageHeader>

        <div class="card">
            <div class="card-header">
                <div class="row g-2">
                    <div class="col-md-4">
                        <div class="input-group">
                            <span class="input-group-text bg-primary text-white border-primary"><i class="ri-search-line" /></span>
                            <input v-model="filtros.search" type="search" class="form-control" placeholder="Buscar (Número, empresa, caso)..." aria-label="Buscar teléfonos">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select v-model="filtros.filtro" class="form-select" aria-label="Filtrar por">
                            <option value="">Filtrar Por</option>
                            <option v-for="[valor, etiqueta] in FILTROS" :key="valor" :value="valor">{{ etiqueta }}</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Número Celular</th>
                                <th>Persona del Caso</th>
                                <th>Caso</th>
                                <th>Persona Vinculada</th>
                                <th>IMEIs Asociados</th>
                                <th>Fecha Registro</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <FilasCargando v-if="cargando" :columnas="8" />
                            <template v-else>
                                <tr v-for="(telefono, indice) in telefonos.data" :key="telefono.id">
                                    <td class="text-muted">{{ numero(indice) }}</td>
                                    <td>
                                        <span class="fw-bold cifra">{{ telefono.numero_celular }}</span>
                                        <small v-if="telefono.empresa" class="d-block text-muted">{{ telefono.empresa }}</small>
                                    </td>
                                    <td>{{ guion(telefono.persona_caso) }}</td>
                                    <td>{{ guion(telefono.caso) }}</td>
                                    <td>
                                        <template v-if="telefono.persona">
                                            <div class="fw-bold cifra">{{ guion(telefono.persona.ci) }}</div>
                                            <small>{{ nombreCompleto(telefono.persona) }}</small>
                                        </template>
                                        <div v-else class="d-flex align-items-center gap-2">
                                            <small class="text-danger">No vinculado</small>
                                            <button v-if="puede('telefonos.editar')" type="button" class="btn btn-sm btn-soft-secondary" title="Vincular persona" @click="abrirVincular(telefono)">
                                                <i class="ri-user-add-line align-bottom" />
                                            </button>
                                        </div>
                                    </td>
                                    <td>
                                        <div v-if="telefono.imeis?.length" class="d-flex flex-wrap gap-1">
                                            <span v-for="imei in telefono.imeis" :key="imei.id" class="badge badge-outline-secondary cifra">{{ imei.imei }}</span>
                                        </div>
                                        <small v-else class="text-muted">Sin IMEIs</small>
                                    </td>
                                    <td class="cifra text-nowrap">{{ fecha(telefono.created_at) }}</td>
                                    <td class="text-center">
                                        <div class="d-flex gap-2 justify-content-center">
                                            <button type="button" class="btn btn-sm btn-soft-secondary" title="Ver detalles" @click="verDetalle(telefono)"><i class="ri-eye-fill align-bottom" /></button>
                                            <button v-if="puede('telefonos.editar')" type="button" class="btn btn-sm btn-soft-secondary" title="Editar" @click="abrirEditar(telefono)"><i class="ri-pencil-fill align-bottom" /></button>
                                            <button v-if="puede('telefonos.eliminar')" type="button" class="btn btn-sm btn-soft-secondary" title="Eliminar" @click="eliminar(telefono)"><i class="ri-delete-bin-fill align-bottom" /></button>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
                <EstadoVacio v-if="!cargando && !telefonos.data.length" titulo="No se encontraron teléfonos" />
                <Paginacion :paginado="telefonos" entidad="teléfonos" :deshabilitado="cargando" @ir="irAPagina" />
            </div>
        </div>

        <BaseModal v-model="modalFormulario" :titulo="editando ? 'Editar Teléfono' : 'Nuevo Teléfono'" tamano="lg" estatico>
            <FormularioTelefono ref="formulario" :telefono="editando" @guardado="modalFormulario = false" />
            <template #pie="{ cerrar }">
                <button type="button" class="btn btn-secondary" @click="cerrar"><i class="ri-close-line align-middle me-1" /> Cancelar</button>
                <button type="submit" form="formTelefono" class="btn btn-primary" :disabled="formulario?.procesando">
                    <i class="align-middle me-1" :class="formulario?.procesando ? 'ri-loader-4-line' : 'ri-save-3-line'" /> {{ formulario?.procesando ? 'Guardando…' : 'Guardar' }}
                </button>
            </template>
        </BaseModal>

        <BaseModal v-model="modalDetalle" titulo="Detalles del Teléfono" tamano="lg" centrado>
            <div v-if="!detalle" class="text-center py-5"><div class="spinner-border text-primary" role="status" /></div>
            <div v-else class="row">
                <div class="col-md-6 mb-3"><h6 class="text-muted">Número Celular</h6><p class="fw-bold cifra mb-0">{{ detalle.numero_celular }}</p></div>
                <div class="col-md-6 mb-3"><h6 class="text-muted">Empresa</h6><p class="fw-bold mb-0">{{ guion(detalle.empresa) }}</p></div>
                <div class="col-md-6 mb-3"><h6 class="text-muted">Persona/Caso</h6><p class="fw-bold mb-0">{{ guion(detalle.persona_caso) }}</p></div>
                <div class="col-md-6 mb-3"><h6 class="text-muted">Caso</h6><p class="fw-bold mb-0">{{ guion(detalle.caso) }}</p></div>
                <div v-if="detalle.persona" class="col-md-6 mb-3">
                    <h6 class="text-muted">Relacionado con</h6>
                    <p class="fw-bold mb-0">
                        <Link v-if="urlPersona && puede('personas.ver')" :href="urlPersona">{{ nombreCompleto(detalle.persona) }}</Link>
                        <template v-else>{{ nombreCompleto(detalle.persona) }}</template>
                    </p>
                </div>
                <div class="col-md-6 mb-3"><h6 class="text-muted">Respuesta Requerimiento</h6><p class="fw-bold mb-0">{{ guion(detalle.respuesta_requerimiento) }}</p></div>
                <div v-if="detalle.informacion" class="col-12 mb-3"><h6 class="text-muted">Información General</h6><p class="mb-0">{{ detalle.informacion }}</p></div>
                <div v-if="detalle.imeis?.length" class="col-12 mb-3">
                    <h6 class="text-muted">IMEI Asociados</h6>
                    <span v-for="imei in detalle.imeis" :key="imei.id" class="badge badge-outline-secondary me-2 cifra">{{ imei.imei }}</span>
                </div>
                <div class="col-md-4"><h6 class="text-muted">CallApp</h6><p class="fw-bold">{{ guion(detalle.callapp) }}</p></div>
                <div class="col-md-4"><h6 class="text-muted">TrueCall</h6><p class="fw-bold">{{ guion(detalle.truecall) }}</p></div>
                <div class="col-md-4"><h6 class="text-muted">UniNet</h6><p class="fw-bold">{{ guion(detalle.uninet) }}</p></div>
                <div class="col-12 mt-2 pt-3 border-top small text-muted">
                    <i class="ri-calendar-line" /> Registrado: <span class="cifra">{{ fechaHora(detalle.created_at) }}</span><br>
                    <i class="ri-refresh-line" /> Último cambio: <span class="cifra">{{ fechaHora(detalle.updated_at) }}</span>
                </div>
            </div>
            <template #pie="{ cerrar }">
                <button type="button" class="btn btn-secondary" @click="cerrar"><i class="ri-close-line align-middle me-1" /> Cerrar</button>
            </template>
        </BaseModal>

        <BaseModal v-model="modalVincular" titulo="Vincular Persona" centrado>
            <label class="form-label">Buscar Persona</label>
            <BuscadorRemoto
                v-model="formVincular.persona_id"
                :url="route('personas.search')"
                placeholder="Nombre, apellido o CI"
                :mapear="(p) => ({ id: p.id, texto: textoPersona(p) })"
                :invalido="Boolean(formVincular.errors.persona_id)"
            />
            <CampoError :mensaje="formVincular.errors.persona_id" />
            <template #pie="{ cerrar }">
                <button type="button" class="btn btn-secondary" @click="cerrar"><i class="ri-close-line align-middle me-1" /> Cancelar</button>
                <button type="button" class="btn btn-primary" :disabled="formVincular.processing || !formVincular.persona_id" @click="vincular">
                    <i class="ri-user-add-line align-middle me-1" /> Vincular
                </button>
            </template>
        </BaseModal>
    </div>
</template>
