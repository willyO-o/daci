<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import PageHeader from '@/Components/Layout/PageHeader.vue';
import BaseModal from '@/Components/Ui/BaseModal.vue';
import EstadoVacio from '@/Components/Ui/EstadoVacio.vue';
import FilasCargando from '@/Components/Ui/FilasCargando.vue';
import Paginacion from '@/Components/Ui/Paginacion.vue';
import FormularioCaso from '@/Components/Casos/FormularioCaso.vue';
import { useListado } from '@/Composables/useListado';
import { useNotificacion } from '@/Composables/useNotificacion';
import { usePermisos } from '@/Composables/usePermisos';
import { cifra, fecha, guion } from '@/Utils/formato';

const props = defineProps({
    casos: { type: Object, required: true },
    filtros: { type: Object, default: () => ({}) },
    estados: { type: Array, default: () => [] },
});

const { puede } = usePermisos();
const { confirmar } = useNotificacion();
const { filtros, cargando, irAPagina } = useListado('casos.index', { search: props.filtros.search ?? '', estado: props.filtros.estado ?? '' }, ['casos']);

const numero = (indice) => (props.casos.from ?? 1) + indice;
/** Vehículos: los del caso más los vínculos vehículo–persona con este caso. */
const vehiculos = (caso) => (caso.vehiculos_count ?? 0) + (caso.vinculos_vehiculo_count ?? 0);
const total = (caso) => (caso.telefonos_count ?? 0) + (caso.registros_criminales_count ?? 0) + (caso.faltas_contravenciones_count ?? 0) + vehiculos(caso);

const modalFormulario = ref(false);
const editando = ref(null);
const formulario = ref(null);

const abrirNuevo = () => {
    editando.value = null;
    modalFormulario.value = true;
};
const abrirEditar = (caso) => {
    editando.value = caso;
    modalFormulario.value = true;
};

const eliminar = async (caso) => {
    if (await confirmar({ titulo: `¿Eliminar el caso ${caso.codigo}?`, texto: 'Sólo se puede eliminar un caso sin registros vinculados.', confirmar: 'Sí, eliminar', peligro: true })) {
        router.delete(route('casos.destroy', caso.id), { preserveScroll: true, preserveState: true });
    }
};
</script>

<template>
    <div>
        <PageHeader titulo="Gestión de Casos" :migas="[{ etiqueta: 'Casos' }]">
            <template v-if="puede('casos.crear')" #acciones>
                <button type="button" class="btn btn-primary" @click="abrirNuevo"><i class="ri-add-line align-middle me-1" /> Nuevo Caso</button>
            </template>
        </PageHeader>

        <div class="card">
            <div class="card-header">
                <div class="row g-2">
                    <div class="col-md-5">
                        <div class="input-group">
                            <span class="input-group-text bg-primary text-white border-primary"><i class="ri-search-line" /></span>
                            <input v-model="filtros.search" type="search" class="form-control" placeholder="Buscar (Caso / CUD, descripción)..." aria-label="Buscar casos">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select v-model="filtros.estado" class="form-select" aria-label="Estado">
                            <option value="">Todos los estados</option>
                            <option v-for="estado in estados" :key="estado" :value="estado">{{ estado }}</option>
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
                                <th>Caso / CUD</th>
                                <th>Estado</th>
                                <th v-if="puede('telefonos.ver')" class="text-end">Teléfonos</th>
                                <th v-if="puede('registro_criminal.ver')" class="text-end">Reg. Criminal</th>
                                <th v-if="puede('faltas_contravenciones.ver')" class="text-end">Faltas y Contrav.</th>
                                <th v-if="puede('vehiculos.ver')" class="text-end">Vehículos</th>
                                <th>Fecha Registro</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <FilasCargando v-if="cargando" :columnas="9" />
                            <template v-else>
                                <tr v-for="(caso, indice) in casos.data" :key="caso.id">
                                    <td class="text-muted">{{ numero(indice) }}</td>
                                    <td>
                                        <Link v-if="puede('casos.ver')" :href="route('casos.show', caso.id)" class="fw-bold">{{ caso.codigo }}</Link>
                                        <span v-else class="fw-bold">{{ caso.codigo }}</span>
                                        <small v-if="caso.descripcion" class="d-block text-muted text-truncate" style="max-width: 28rem">{{ caso.descripcion }}</small>
                                    </td>
                                    <td><span class="badge" :class="caso.estado === 'ACTIVO' ? 'bg-soft-success text-success' : 'bg-soft-secondary text-secondary'">{{ caso.estado }}</span></td>
                                    <td v-if="puede('telefonos.ver')" class="text-end cifra">{{ cifra(caso.telefonos_count ?? 0) }}</td>
                                    <td v-if="puede('registro_criminal.ver')" class="text-end cifra">{{ cifra(caso.registros_criminales_count ?? 0) }}</td>
                                    <td v-if="puede('faltas_contravenciones.ver')" class="text-end cifra">{{ cifra(caso.faltas_contravenciones_count ?? 0) }}</td>
                                    <td v-if="puede('vehiculos.ver')" class="text-end cifra">{{ cifra(vehiculos(caso)) }}</td>
                                    <td class="cifra text-nowrap">{{ guion(fecha(caso.created_at)) }}</td>
                                    <td class="text-center">
                                        <div class="d-flex gap-2 justify-content-center">
                                            <Link v-if="puede('casos.ver')" :href="route('casos.show', caso.id)" class="btn btn-sm btn-soft-secondary" title="Ver lo vinculado"><i class="ri-eye-fill align-bottom" /></Link>
                                            <button v-if="puede('casos.editar')" type="button" class="btn btn-sm btn-soft-secondary" title="Editar" @click="abrirEditar(caso)"><i class="ri-pencil-fill align-bottom" /></button>
                                            <button v-if="puede('casos.eliminar') && total(caso) === 0" type="button" class="btn btn-sm btn-soft-secondary" title="Eliminar" @click="eliminar(caso)"><i class="ri-delete-bin-fill align-bottom" /></button>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
                <EstadoVacio v-if="!cargando && !casos.data.length" titulo="No se encontraron casos" />
                <Paginacion :paginado="casos" entidad="casos" :deshabilitado="cargando" @ir="irAPagina" />
            </div>
        </div>

        <BaseModal v-model="modalFormulario" :titulo="editando ? 'Editar Caso' : 'Nuevo Caso'" estatico>
            <FormularioCaso ref="formulario" :caso="editando" :estados="estados" @guardado="modalFormulario = false" />
            <template #pie="{ cerrar }">
                <button type="button" class="btn btn-secondary" @click="cerrar"><i class="ri-close-line align-middle me-1" /> Cancelar</button>
                <button type="submit" form="formCaso" class="btn btn-primary" :disabled="formulario?.procesando">
                    <i class="align-middle me-1" :class="formulario?.procesando ? 'ri-loader-4-line' : 'ri-save-3-line'" /> {{ formulario?.procesando ? 'Guardando…' : 'Guardar' }}
                </button>
            </template>
        </BaseModal>
    </div>
</template>
