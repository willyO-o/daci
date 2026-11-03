<script setup>
import { computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import PageHeader from '@/Components/Layout/PageHeader.vue';
import EstadoVacio from '@/Components/Ui/EstadoVacio.vue';
import FilasCargando from '@/Components/Ui/FilasCargando.vue';
import Paginacion from '@/Components/Ui/Paginacion.vue';
import RangoFechas from '@/Components/Ui/RangoFechas.vue';
import { useListado } from '@/Composables/useListado';
import { useNotificacion } from '@/Composables/useNotificacion';
import { usePermisos } from '@/Composables/usePermisos';
import { CLASE_ESTADO, ETIQUETA_ESTADO } from '@/Utils/evidencias';
import { cifra, fechaHora, guion } from '@/Utils/formato';

const props = defineProps({
    evidencias: { type: Object, required: true },
    filtros: { type: Object, default: () => ({}) },
    tipos: { type: Array, default: () => [] },
    divisiones: { type: Array, default: () => [] },
    reglas: { type: Object, required: true },
});

const { puede } = usePermisos();
const { confirmar } = useNotificacion();

const CAMPOS = ['codigo', 'cud', 'desde', 'hasta', 'tipo', 'estado', 'funcionario', 'division', 'imei'];
const { filtros, cargando, irAPagina } = useListado(
    'evidencias.index',
    Object.fromEntries(CAMPOS.map((c) => [c, props.filtros[c] ?? ''])),
    ['evidencias'],
    { camposTexto: ['codigo', 'cud', 'funcionario', 'imei'] },
);

const hayFiltros = computed(() => CAMPOS.some((c) => filtros[c]));
const limpiarFiltros = () => CAMPOS.forEach((c) => (filtros[c] = ''));
const numero = (indice) => (props.evidencias.from ?? 1) + indice;

const eliminar = async (evidencia) => {
    const nombre = evidencia.codigo ? `la evidencia ${evidencia.codigo}` : `la evidencia del ${fechaHora(evidencia.fecha_hora)}`;
    if (await confirmar({
        titulo: `¿Eliminar ${nombre}?`,
        texto: `Se eliminan sus ${evidencia.articulos_count} artículo(s) y sus archivos; los publicados se retiran del portal.`,
        confirmar: 'Sí, eliminar',
        peligro: true,
    })) {
        router.delete(route('evidencias.destroy', evidencia.id), { preserveScroll: true });
    }
};
</script>

<template>
    <div>
        <PageHeader titulo="Evidencias" :migas="[{ etiqueta: 'Evidencias' }]">
            <template v-if="puede('evidencias.crear')" #acciones>
                <Link :href="route('evidencias.create')" class="btn btn-primary"><i class="ri-add-line align-middle me-1" /> Nueva evidencia</Link>
            </template>
        </PageHeader>

        <div class="card">
            <div class="card-header">
                <div class="row g-2">
                    <div class="col-xl-3 col-md-6">
                        <div class="input-group">
                            <span class="input-group-text bg-primary text-white border-primary"><i class="ri-search-line" /></span>
                            <input v-model="filtros.imei" type="search" class="form-control" placeholder="IMEI o número de serie" aria-label="Buscar por IMEI o número de serie">
                        </div>
                    </div>
                    <div class="col-xl-2 col-md-3 col-6">
                        <input v-model="filtros.cud" type="search" class="form-control" placeholder="CUD / Caso" aria-label="CUD o caso">
                    </div>
                    <div class="col-xl-2 col-md-3 col-6">
                        <input v-model="filtros.codigo" type="search" class="form-control" placeholder="Código o N.° de acta" aria-label="Código o número de acta">
                    </div>
                    <div class="col-xl-5 col-md-12">
                        <RangoFechas v-model:inicio="filtros.desde" v-model:fin="filtros.hasta" etiqueta="Fecha del procedimiento" />
                    </div>
                    <div class="col-xl-3 col-md-4 col-6">
                        <select v-model="filtros.tipo" class="form-select" aria-label="Tipo de artículo">
                            <option value="">Todos los tipos de artículo</option>
                            <option v-for="t in tipos" :key="t.id" :value="t.id">{{ t.nombre }}</option>
                        </select>
                    </div>
                    <div class="col-xl-2 col-md-4 col-6">
                        <select v-model="filtros.estado" class="form-select" aria-label="Estado">
                            <option value="">Todos los estados</option>
                            <option v-for="estado in reglas.estados" :key="estado" :value="estado">{{ ETIQUETA_ESTADO[estado] }}</option>
                        </select>
                    </div>
                    <div class="col-xl-3 col-md-4">
                        <select v-model="filtros.division" class="form-select" aria-label="Unidad o división">
                            <option value="">Todas las unidades</option>
                            <option v-for="d in divisiones" :key="d.id" :value="d.id">{{ d.division }}</option>
                        </select>
                    </div>
                    <div class="col-xl-3 col-md-8">
                        <input v-model="filtros.funcionario" type="search" class="form-control" placeholder="Funcionario (responsable o que recogió)" aria-label="Funcionario">
                    </div>
                    <div v-if="hayFiltros" class="col-xl-1 col-md-4 d-grid">
                        <button type="button" class="btn btn-soft-secondary" title="Quitar todos los filtros" @click="limpiarFiltros">Limpiar</button>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th scope="col">#</th>
                                <th scope="col">Código</th>
                                <th scope="col">CUD</th>
                                <th scope="col">Fecha y hora</th>
                                <th scope="col">Lugar</th>
                                <th scope="col">Funcionario responsable</th>
                                <th scope="col" class="text-end">Artículos</th>
                                <th scope="col">Estado</th>
                                <th scope="col" class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <FilasCargando v-if="cargando" :columnas="9" />
                            <template v-else>
                                <tr v-for="(evidencia, indice) in evidencias.data" :key="evidencia.id">
                                    <td class="text-muted cifra">{{ numero(indice) }}</td>
                                    <td>
                                        <span class="fw-semibold">{{ guion(evidencia.codigo) }}</span>
                                        <small v-if="evidencia.numero_acta" class="d-block text-muted">Acta {{ evidencia.numero_acta }}</small>
                                    </td>
                                    <td class="cifra">{{ guion(evidencia.caso?.codigo) }}</td>
                                    <td class="cifra text-nowrap">{{ fechaHora(evidencia.fecha_hora) }}</td>
                                    <td>
                                        <span class="d-inline-block text-truncate" style="max-width: 16rem" :title="evidencia.lugar">{{ evidencia.lugar }}</span>
                                        <small v-if="evidencia.division" class="d-block text-muted">{{ evidencia.division.division }}</small>
                                    </td>
                                    <td>{{ evidencia.funcionario_responsable }}</td>
                                    <td class="text-end">
                                        <span class="cifra fw-semibold">{{ cifra(evidencia.articulos_count) }}</span>
                                        <small v-if="evidencia.publicados_count" class="d-block text-success text-nowrap"><i class="ri-earth-line" /> <span class="cifra">{{ evidencia.publicados_count }}</span> publicado(s)</small>
                                        <small v-if="evidencia.pendientes_count" class="d-block text-warning text-nowrap"><span class="cifra">{{ evidencia.pendientes_count }}</span> en revisión</small>
                                    </td>
                                    <td><span class="badge" :class="CLASE_ESTADO[evidencia.estado]">{{ ETIQUETA_ESTADO[evidencia.estado] }}</span></td>
                                    <td class="text-center">
                                        <div class="d-flex gap-2 justify-content-center">
                                            <Link v-if="puede('evidencias.ver')" :href="route('evidencias.show', evidencia.id)" class="btn btn-sm btn-soft-secondary" title="Ver artículos y archivos"><i class="ri-eye-fill align-bottom" /></Link>
                                            <Link v-if="puede('evidencias.editar')" :href="route('evidencias.edit', evidencia.id)" class="btn btn-sm btn-soft-secondary" title="Editar"><i class="ri-pencil-fill align-bottom" /></Link>
                                            <button v-if="puede('evidencias.eliminar')" type="button" class="btn btn-sm btn-soft-secondary" title="Eliminar" @click="eliminar(evidencia)"><i class="ri-delete-bin-fill align-bottom" /></button>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
                <EstadoVacio
                    v-if="!cargando && !evidencias.data.length"
                    :titulo="hayFiltros ? 'Ninguna evidencia coincide con los filtros' : 'Todavía no hay evidencias registradas'"
                />
                <Paginacion :paginado="evidencias" entidad="evidencias" :deshabilitado="cargando" @ir="irAPagina" />
            </div>
        </div>
    </div>
</template>
