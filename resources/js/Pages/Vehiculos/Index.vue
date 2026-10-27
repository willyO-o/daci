<script setup>
import { ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import PageHeader from '@/Components/Layout/PageHeader.vue';
import BaseModal from '@/Components/Ui/BaseModal.vue';
import BuscadorRemoto from '@/Components/Ui/BuscadorRemoto.vue';
import CampoError from '@/Components/Ui/CampoError.vue';
import EstadoVacio from '@/Components/Ui/EstadoVacio.vue';
import FilasCargando from '@/Components/Ui/FilasCargando.vue';
import Paginacion from '@/Components/Ui/Paginacion.vue';
import DetalleVehiculo from '@/Components/Vehiculos/DetalleVehiculo.vue';
import FormularioVehiculo from '@/Components/Vehiculos/FormularioVehiculo.vue';
import { useListado } from '@/Composables/useListado';
import { useNotificacion } from '@/Composables/useNotificacion';
import { usePermisos } from '@/Composables/usePermisos';
import { fecha, guion, nombreCompleto } from '@/Utils/formato';
import { http } from '@/Utils/http';
import { guiaFormularioVehiculo } from '@/Utils/guias';

const props = defineProps({
    vehiculos: { type: Object, required: true },
    filtros: { type: Object, default: () => ({}) },
});

const TIPOS = ['BSISA', 'RUAT', 'SOAT', 'ANH', 'ITB'];

const { puede } = usePermisos();
const { confirmar, errorDeRespuesta } = useNotificacion();
const { filtros, cargando, irAPagina, recargar } = useListado('vehiculos.index', { search: props.filtros.search ?? '', filtro: props.filtros.filtro ?? '' }, ['vehiculos']);

const numero = (indice) => (props.vehiculos.from ?? 1) + indice;
const textoPersona = (p) => `${p.nombres} ${p.apellidos ?? ''} - CI: ${p.ci ?? ''}`;

// Alta / edición: el formulario es `FormularioVehiculo` (el mismo de «Registrar Vehículo»).
const modalFormulario = ref(false);
const editando = ref(null);
const formulario = ref(null);

const abrirNuevo = () => {
    editando.value = null;
    modalFormulario.value = true;
};
const abrirEditar = (vehiculo) => {
    editando.value = vehiculo;
    modalFormulario.value = true;
};

// Detalle
const modalDetalle = ref(false);
const detalle = ref(null);
const verDetalle = async (vehiculo) => {
    detalle.value = null;
    modalDetalle.value = true;
    try {
        detalle.value = (await http.get(route('vehiculos.show', vehiculo.id))).data;
    } catch (err) {
        modalDetalle.value = false;
        errorDeRespuesta(err);
    }
};

// Vincular persona
const modalVincular = ref(false);
const vehiculoVincular = ref(null);
const formVincular = useForm({ persona_id: null, tipo: '', caso: '' });
const abrirVincular = (vehiculo) => {
    vehiculoVincular.value = vehiculo;
    formVincular.reset();
    formVincular.clearErrors();
    modalVincular.value = true;
};
const vincular = () => {
    formVincular.post(route('vehiculos.vincular-persona', vehiculoVincular.value.id), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => (modalVincular.value = false),
    });
};

const eliminar = async (vehiculo) => {
    if (await confirmar({ titulo: `¿Eliminar el vehículo ${vehiculo.placa}?`, texto: 'Se borrarán también sus personas vinculadas, carguíos, ITV y fotos.', confirmar: 'Sí, eliminar', peligro: true })) {
        router.delete(route('vehiculos.destroy', vehiculo.id), { preserveScroll: true, preserveState: true });
    }
};
</script>

<template>
    <div>
        <PageHeader titulo="Gestión de Vehículos" :migas="[{ etiqueta: 'Vehículos' }]">
            <template v-if="puede('vehiculos.crear')" #acciones>
                <button type="button" class="btn btn-primary" @click="abrirNuevo"><i class="ri-add-line align-middle me-1" /> Nuevo Vehículo</button>
            </template>
        </PageHeader>

        <div class="card">
            <div class="card-header">
                <div class="row g-2">
                    <div class="col-md-4">
                        <div class="input-group">
                            <span class="input-group-text bg-primary text-white border-primary"><i class="ri-search-line" /></span>
                            <input v-model="filtros.search" type="search" class="form-control" placeholder="Buscar (Placa, descripción, responsable)..." aria-label="Buscar vehículos">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select v-model="filtros.filtro" class="form-select" aria-label="Filtrar por">
                            <option value="">Filtrar Por</option>
                            <option value="placa">Placa</option>
                            <option value="descripcion">Descripción</option>
                            <option value="responsable">Responsable</option>
                            <option value="caso_relacionado">Caso Relacionado</option>
                            <option value="ci_persona">CI de Persona</option>
                            <option value="nombres">Nombre de Persona</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr><th>#</th><th>Placa</th><th>Descripción</th><th>Personas Vinculadas</th><th>Responsable</th><th>Caso Relacionado</th><th>Fecha Registro</th><th class="text-center">Acciones</th></tr>
                        </thead>
                        <tbody>
                            <FilasCargando v-if="cargando" :columnas="8" />
                            <template v-else>
                                <tr v-for="(vehiculo, indice) in vehiculos.data" :key="vehiculo.id">
                                    <td class="text-muted">{{ numero(indice) }}</td>
                                    <td><span class="fw-bold">{{ vehiculo.placa }}</span></td>
                                    <td>{{ guion(vehiculo.descripcion) }}</td>
                                    <td>
                                        <div v-if="vehiculo.personas?.length" style="max-height: 150px; overflow-y: auto">
                                            <div v-for="persona in vehiculo.personas" :key="`${persona.id}-${persona.pivot?.tipo}`" class="mb-2">
                                                <div class="fw-bold cifra">{{ guion(persona.ci) }}</div>
                                                <small>{{ nombreCompleto(persona) }}</small><br>
                                                <span class="badge badge-outline-secondary">{{ persona.pivot?.tipo }}</span>
                                                <span v-if="persona.pivot?.numero_informacion" class="badge badge-outline-info ms-1">{{ persona.pivot.numero_informacion }}</span>
                                            </div>
                                        </div>
                                        <small v-else class="text-muted">Sin personas vinculadas</small>
                                    </td>
                                    <td>{{ guion(vehiculo.responsable) }}</td>
                                    <td>{{ guion(vehiculo.caso_relacionado) }}</td>
                                    <td class="cifra text-nowrap">{{ fecha(vehiculo.created_at) }}</td>
                                    <td class="text-center">
                                        <div class="d-flex gap-2 justify-content-center">
                                            <button type="button" class="btn btn-sm btn-soft-secondary" title="Ver detalles" @click="verDetalle(vehiculo)"><i class="ri-eye-fill align-bottom" /></button>
                                            <button v-if="puede('vehiculos.vincular')" type="button" class="btn btn-sm btn-soft-success" title="Vincular persona" @click="abrirVincular(vehiculo)"><i class="ri-links-line align-bottom" /></button>
                                            <button v-if="puede('vehiculos.editar')" type="button" class="btn btn-sm btn-soft-secondary" title="Editar" @click="abrirEditar(vehiculo)"><i class="ri-pencil-fill align-bottom" /></button>
                                            <button v-if="puede('vehiculos.eliminar')" type="button" class="btn btn-sm btn-soft-secondary" title="Eliminar" @click="eliminar(vehiculo)"><i class="ri-delete-bin-fill align-bottom" /></button>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
                <EstadoVacio v-if="!cargando && !vehiculos.data.length" titulo="No se encontraron vehículos" />
                <Paginacion :paginado="vehiculos" entidad="vehículos" :deshabilitado="cargando" @ir="irAPagina" />
            </div>
        </div>

        <BaseModal v-model="modalFormulario" :titulo="editando ? 'Editar Vehículo' : 'Nuevo Vehículo'" tamano="lg" estatico :guia="guiaFormularioVehiculo">
            <FormularioVehiculo ref="formulario" :vehiculo="editando" @guardado="modalFormulario = false" @fotos-cambiadas="recargar" />
            <template #pie="{ cerrar }">
                <button type="button" class="btn btn-secondary" @click="cerrar"><i class="ri-close-line align-middle me-1" /> Cancelar</button>
                <button type="submit" form="formVehiculo" class="btn btn-primary" data-tour="f-guardar" :disabled="formulario?.procesando">
                    <i class="align-middle me-1" :class="formulario?.procesando ? 'ri-loader-4-line' : 'ri-save-3-line'" /> {{ formulario?.procesando ? 'Guardando…' : 'Guardar' }}
                </button>
            </template>
        </BaseModal>

        <BaseModal v-model="modalDetalle" titulo="Detalles del Vehículo" tamano="xl" centrado>
            <DetalleVehiculo v-if="detalle" :vehiculo="detalle" />
            <div v-else class="text-center py-5"><div class="spinner-border text-primary" role="status" /></div>
            <template #pie="{ cerrar }">
                <button type="button" class="btn btn-secondary" @click="cerrar"><i class="ri-close-line align-middle me-1" /> Cerrar</button>
            </template>
        </BaseModal>

        <BaseModal v-model="modalVincular" titulo="Vincular Persona al Vehículo" centrado>
            <form id="formVincularVehiculo" novalidate @submit.prevent="vincular">
                <div class="mb-3">
                    <label class="form-label">Buscar Persona *</label>
                    <BuscadorRemoto v-model="formVincular.persona_id" :url="route('personas.search')" placeholder="Seleccionar persona" :mapear="(p) => ({ id: p.id, texto: textoPersona(p) })" :invalido="Boolean(formVincular.errors.persona_id)" />
                    <CampoError :mensaje="formVincular.errors.persona_id" />
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="vincularTipo" class="form-label">Tipo de Información *</label>
                        <input id="vincularTipo" v-model="formVincular.tipo" v-mayuscula list="datalistTipo" class="form-control" :class="{ 'is-invalid': formVincular.errors.tipo }" placeholder="Buscar tipo...">
                        <datalist id="datalistTipo"><option v-for="tipo in TIPOS" :key="tipo" :value="tipo" /></datalist>
                        <CampoError :mensaje="formVincular.errors.tipo" />
                    </div>
                    <div class="col-md-6">
                        <label for="vincularCaso" class="form-label">Caso (Opcional)</label>
                        <input id="vincularCaso" v-model="formVincular.caso" v-mayuscula type="text" class="form-control" placeholder="Ingresa el caso si aplica">
                    </div>
                </div>
            </form>
            <template #pie="{ cerrar }">
                <button type="button" class="btn btn-secondary" @click="cerrar"><i class="ri-close-line align-middle me-1" /> Cancelar</button>
                <button type="submit" form="formVincularVehiculo" class="btn btn-primary" :disabled="formVincular.processing"><i class="ri-links-line align-middle me-1" /> Vincular</button>
            </template>
        </BaseModal>
    </div>
</template>
