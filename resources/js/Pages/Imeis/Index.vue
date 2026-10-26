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
import FormularioImei from '@/Components/Imeis/FormularioImei.vue';
import { useListado } from '@/Composables/useListado';
import { useNotificacion } from '@/Composables/useNotificacion';
import { usePermisos } from '@/Composables/usePermisos';
import { fecha, fechaHora, guion } from '@/Utils/formato';
import { http } from '@/Utils/http';

const props = defineProps({
    imeis: { type: Object, required: true },
    filtros: { type: Object, default: () => ({}) },
});

const { puede } = usePermisos();
const { confirmar, errorDeRespuesta } = useNotificacion();
const { filtros, cargando, irAPagina } = useListado('imeis.index', { search: props.filtros.search ?? '', filtro: props.filtros.filtro ?? '' }, ['imeis']);

const numero = (indice) => (props.imeis.from ?? 1) + indice;
const textoTelefono = (telefono) =>
    `${telefono.numero_celular}${telefono.persona ? ` - ${telefono.persona.nombres} ${telefono.persona.apellidos ?? ''}` : ''}`;

// Alta / edición: el formulario es `FormularioImei` (el mismo de «Registrar IMEI»).
const modalFormulario = ref(false);
const editando = ref(null);
const formulario = ref(null);

const abrirNuevo = () => {
    editando.value = null;
    modalFormulario.value = true;
};
const abrirEditar = (imei) => {
    editando.value = imei;
    modalFormulario.value = true;
};

// Detalle
const modalDetalle = ref(false);
const detalle = ref(null);
const verDetalle = async (imei) => {
    detalle.value = null;
    modalDetalle.value = true;
    try {
        detalle.value = (await http.get(route('imeis.show', imei.id))).data;
    } catch (err) {
        modalDetalle.value = false;
        errorDeRespuesta(err);
    }
};
const editarDesdeDetalle = () => {
    const imei = props.imeis.data.find((fila) => fila.id === detalle.value.id) ?? detalle.value;
    modalDetalle.value = false;
    abrirEditar(imei);
};

// Vincular teléfono
const modalVincular = ref(false);
const imeiVincular = ref(null);
const formVincular = useForm({ telefono_id: null });
const abrirVincular = (imei) => {
    imeiVincular.value = imei;
    formVincular.reset();
    formVincular.clearErrors();
    modalVincular.value = true;
};
const vincular = () => {
    formVincular.post(route('imeis.vincular-telefono', imeiVincular.value.id), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => (modalVincular.value = false),
    });
};

const eliminar = async (imei) => {
    if (await confirmar({ titulo: `¿Eliminar el IMEI ${imei.imei}?`, texto: 'Esta acción no se puede deshacer.', confirmar: 'Sí, eliminar', peligro: true })) {
        router.delete(route('imeis.destroy', imei.id), { preserveScroll: true, preserveState: true });
    }
};
</script>

<template>
    <div>
        <PageHeader titulo="Gestión de IMEIs" :migas="[{ etiqueta: 'IMEIs' }]">
            <template v-if="puede('imeis.crear')" #acciones>
                <button type="button" class="btn btn-primary" @click="abrirNuevo"><i class="ri-add-line align-middle me-1" /> Nuevo IMEI</button>
            </template>
        </PageHeader>

        <div class="card">
            <div class="card-header">
                <div class="row g-2">
                    <div class="col-md-4">
                        <div class="input-group">
                            <span class="input-group-text bg-primary text-white border-primary"><i class="ri-search-line" /></span>
                            <input v-model="filtros.search" type="search" class="form-control" placeholder="Buscar (IMEI, características)..." aria-label="Buscar IMEIs">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select v-model="filtros.filtro" class="form-select" aria-label="Filtrar por">
                            <option value="">Filtrar Por</option>
                            <option value="imei">IMEI</option>
                            <option value="caracteristicas">Características</option>
                            <option value="numero">Número Telefónico</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr><th>#</th><th>IMEI</th><th>Características</th><th>Teléfono Vinculado</th><th>Fecha Registro</th><th class="text-center">Acciones</th></tr>
                        </thead>
                        <tbody>
                            <FilasCargando v-if="cargando" :columnas="6" />
                            <template v-else>
                                <tr v-for="(imei, indice) in imeis.data" :key="imei.id">
                                    <td class="text-muted">{{ numero(indice) }}</td>
                                    <td><span class="fw-bold cifra">{{ imei.imei }}</span></td>
                                    <td>{{ guion(imei.caracteristicas) }}</td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div v-if="imei.telefonos?.length" class="d-flex flex-column gap-1">
                                                <span v-for="telefono in imei.telefonos" :key="telefono.id" class="badge badge-outline-secondary text-start">{{ textoTelefono(telefono) }}</span>
                                            </div>
                                            <small v-else class="text-muted">Sin teléfonos</small>
                                            <button v-if="puede('imeis.crear')" type="button" class="btn btn-sm btn-soft-secondary" title="Vincular teléfono" @click="abrirVincular(imei)">
                                                <i class="mdi mdi-phone-plus align-middle" />
                                            </button>
                                        </div>
                                    </td>
                                    <td class="cifra text-nowrap">{{ fecha(imei.created_at) }}</td>
                                    <td class="text-center">
                                        <div class="d-flex gap-2 justify-content-center">
                                            <button type="button" class="btn btn-sm btn-soft-secondary" title="Ver detalles" @click="verDetalle(imei)"><i class="ri-eye-fill align-bottom" /></button>
                                            <button v-if="puede('imeis.editar')" type="button" class="btn btn-sm btn-soft-secondary" title="Editar" @click="abrirEditar(imei)"><i class="ri-pencil-fill align-bottom" /></button>
                                            <button v-if="puede('imeis.eliminar')" type="button" class="btn btn-sm btn-soft-secondary" title="Eliminar" @click="eliminar(imei)"><i class="ri-delete-bin-fill align-bottom" /></button>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
                <EstadoVacio v-if="!cargando && !imeis.data.length" titulo="No se encontraron IMEIs" />
                <Paginacion :paginado="imeis" entidad="IMEIs" :deshabilitado="cargando" @ir="irAPagina" />
            </div>
        </div>

        <BaseModal v-model="modalFormulario" :titulo="editando ? 'Editar IMEI' : 'Nuevo IMEI'" estatico>
            <FormularioImei ref="formulario" :imei="editando" @guardado="modalFormulario = false" />
            <template #pie="{ cerrar }">
                <button type="button" class="btn btn-secondary" @click="cerrar"><i class="ri-close-line align-middle me-1" /> Cancelar</button>
                <button type="submit" form="formImei" class="btn btn-primary" :disabled="formulario?.procesando">
                    <i class="align-middle me-1" :class="formulario?.procesando ? 'ri-loader-4-line' : 'ri-save-3-line'" /> {{ formulario?.procesando ? 'Guardando…' : 'Guardar' }}
                </button>
            </template>
        </BaseModal>

        <BaseModal v-model="modalDetalle" titulo="Detalles del IMEI" centrado>
            <div v-if="!detalle" class="text-center py-5"><div class="spinner-border text-primary" role="status" /></div>
            <template v-else>
                <div class="row">
                    <div class="col-md-6 mb-3"><label class="form-label fw-semibold">IMEI:</label><p class="cifra mb-0">{{ detalle.imei }}</p></div>
                    <div class="col-md-6 mb-3"><label class="form-label fw-semibold">Fecha de Registro:</label><p class="cifra mb-0">{{ fechaHora(detalle.created_at) }}</p></div>
                </div>
                <div class="mb-3"><label class="form-label fw-semibold">Características:</label><p class="mb-0">{{ guion(detalle.caracteristicas) }}</p></div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Teléfono Vinculado:</label>
                    <p v-for="telefono in detalle.telefonos" :key="telefono.id" class="text-primary mb-1">
                        {{ telefono.numero_celular }}
                        <template v-if="telefono.persona"> - {{ telefono.persona.nombres }} {{ telefono.persona.apellidos }} (C.I.: {{ telefono.persona.ci ?? '—' }})</template>
                    </p>
                    <p v-if="!detalle.telefonos?.length" class="text-danger mb-0">No hay teléfonos vinculados.</p>
                </div>
                <div><label class="form-label fw-semibold">Última Actualización:</label><p class="cifra mb-0">{{ fechaHora(detalle.updated_at) }}</p></div>
            </template>
            <template #pie="{ cerrar }">
                <button type="button" class="btn btn-secondary" @click="cerrar"><i class="ri-close-line align-middle me-1" /> Cerrar</button>
                <button v-if="detalle && puede('imeis.editar')" type="button" class="btn btn-warning" @click="editarDesdeDetalle"><i class="ri-edit-2-line align-middle me-1" /> Editar</button>
            </template>
        </BaseModal>

        <BaseModal v-model="modalVincular" titulo="Vincular Teléfono" centrado>
            <label class="form-label">Teléfono</label>
            <BuscadorRemoto
                v-model="formVincular.telefono_id"
                :url="route('imeis.telefonos-search')"
                :minimo="3"
                placeholder="Seleccionar teléfono..."
                :invalido="Boolean(formVincular.errors.telefono_id)"
            />
            <CampoError :mensaje="formVincular.errors.telefono_id" />
            <template #pie="{ cerrar }">
                <button type="button" class="btn btn-secondary" @click="cerrar"><i class="ri-close-line align-middle me-1" /> Cancelar</button>
                <button type="button" class="btn btn-primary" :disabled="formVincular.processing || !formVincular.telefono_id" @click="vincular">
                    <i class="mdi mdi-phone-plus align-middle me-1" /> Vincular
                </button>
            </template>
        </BaseModal>
    </div>
</template>
