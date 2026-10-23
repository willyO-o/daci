<script setup>
import { computed, ref } from 'vue';
import PageHeader from '@/Components/Layout/PageHeader.vue';
import BaseModal from '@/Components/Ui/BaseModal.vue';
import EstadoVacio from '@/Components/Ui/EstadoVacio.vue';
import FilasCargando from '@/Components/Ui/FilasCargando.vue';
import Paginacion from '@/Components/Ui/Paginacion.vue';
import { useListado } from '@/Composables/useListado';
import { useNotificacion } from '@/Composables/useNotificacion';
import { http } from '@/Utils/http';

const props = defineProps({
    auditorias: { type: Object, required: true },
    modelos: { type: Array, default: () => [] },
    eventos: { type: Array, default: () => [] },
    filtros: { type: Object, default: () => ({}) },
});

const COLORES = { created: 'success', updated: 'warning', deleted: 'danger', restored: 'info' };
const color = (evento) => COLORES[evento] ?? 'secondary';

const { errorDeRespuesta } = useNotificacion();
const { filtros, cargando, irAPagina } = useListado(
    'auditorias.index',
    {
        search: props.filtros.search ?? '',
        modelo: props.filtros.modelo ?? '',
        evento: props.filtros.evento ?? '',
        fecha_inicio: props.filtros.fecha_inicio ?? '',
        fecha_fin: props.filtros.fecha_fin ?? '',
    },
    ['auditorias'],
);

const numero = (indice) => (props.auditorias.from ?? 1) + indice;

const modal = ref(false);
const detalle = ref(null);
const ver = async (auditoria) => {
    detalle.value = null;
    modal.value = true;
    try {
        detalle.value = (await http.get(route('auditorias.show', auditoria.id))).data;
    } catch (err) {
        modal.value = false;
        errorDeRespuesta(err);
    }
};

const texto = (valor) => (valor === null ? 'null' : typeof valor === 'object' ? JSON.stringify(valor) : String(valor));

const filasCambios = computed(() => {
    if (!detalle.value) {
        return [];
    }
    const { old_values: anteriores = {}, new_values: nuevos = {} } = detalle.value;
    const campos = new Set([...Object.keys(anteriores ?? {}), ...Object.keys(nuevos ?? {})]);

    return [...campos].map((campo) => {
        const anterior = campo in (anteriores ?? {}) ? texto(anteriores[campo]) : '—';
        const nuevo = campo in (nuevos ?? {}) ? texto(nuevos[campo]) : '—';

        return { campo, anterior, nuevo, cambio: anterior !== nuevo };
    });
});
</script>

<template>
    <div>
        <PageHeader titulo="Auditoría de Cambios" :migas="[{ etiqueta: 'Seguridad' }, { etiqueta: 'Auditoría de Cambios' }]" />

        <div class="card">
            <div class="card-header">
                <div class="row g-2 align-items-end">
                    <div class="col-lg-3">
                        <div class="input-group">
                            <span class="input-group-text bg-primary text-white border-primary"><i class="ri-search-line" /></span>
                            <input v-model="filtros.search" type="search" class="form-control" placeholder="Buscar por usuario, IP o ID..." aria-label="Buscar">
                        </div>
                    </div>
                    <div class="col-lg-2">
                        <select v-model="filtros.modelo" class="form-select" aria-label="Módulo">
                            <option value="">Todos los Módulos</option>
                            <option v-for="modelo in modelos" :key="modelo.value" :value="modelo.value">{{ modelo.label }}</option>
                        </select>
                    </div>
                    <div class="col-lg-2">
                        <select v-model="filtros.evento" class="form-select" aria-label="Evento">
                            <option value="">Todos los Eventos</option>
                            <option v-for="evento in eventos" :key="evento.value" :value="evento.value">{{ evento.label }}</option>
                        </select>
                    </div>
                    <div class="col-lg-2">
                        <label for="fechaInicio" class="form-label small fw-semibold mb-1">Fecha Inicio</label>
                        <input id="fechaInicio" v-model="filtros.fecha_inicio" type="date" class="form-control form-control-sm">
                    </div>
                    <div class="col-lg-2">
                        <label for="fechaFin" class="form-label small fw-semibold mb-1">Fecha Fin</label>
                        <input id="fechaFin" v-model="filtros.fecha_fin" type="date" class="form-control form-control-sm">
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr><th>#</th><th>Evento</th><th>Módulo</th><th>ID Registro</th><th>Usuario</th><th>IP</th><th>Cambios</th><th>Fecha / Hora</th><th class="text-center">Acciones</th></tr>
                        </thead>
                        <tbody>
                            <FilasCargando v-if="cargando" :columnas="9" />
                            <template v-else>
                                <tr v-for="(auditoria, indice) in auditorias.data" :key="auditoria.id">
                                    <td class="text-muted">{{ numero(indice) }}</td>
                                    <td><span class="badge fs-11" :class="`bg-soft-${color(auditoria.evento)} text-${color(auditoria.evento)}`">{{ auditoria.evento_label }}</span></td>
                                    <td class="fw-semibold">{{ auditoria.modelo_label }}</td>
                                    <td class="cifra">#{{ auditoria.registro_id }}</td>
                                    <td>
                                        <div class="fw-semibold">{{ auditoria.usuario }}</div>
                                        <small class="text-muted">{{ auditoria.usuario_email }}</small>
                                    </td>
                                    <td class="cifra small">{{ auditoria.ip }}</td>
                                    <td>
                                        <span v-if="auditoria.evento === 'created'" class="badge bg-soft-success text-success cifra">{{ auditoria.campos_nuevos }} campo(s)</span>
                                        <span v-else-if="auditoria.evento === 'deleted'" class="badge bg-soft-danger text-danger cifra">{{ auditoria.campos_antiguos }} campo(s)</span>
                                        <template v-else>
                                            <span class="badge bg-soft-danger text-danger me-1 cifra">{{ auditoria.campos_antiguos }} antes</span>
                                            <span class="badge bg-soft-success text-success cifra">{{ auditoria.campos_nuevos }} después</span>
                                        </template>
                                    </td>
                                    <td class="cifra small text-nowrap">{{ auditoria.created_at }}</td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-sm btn-soft-secondary" title="Ver detalles" @click="ver(auditoria)"><i class="ri-eye-fill align-bottom" /></button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
                <EstadoVacio v-if="!cargando && !auditorias.data.length" titulo="No se encontraron registros de auditoría" />
                <Paginacion :paginado="auditorias" entidad="registros" :deshabilitado="cargando" @ir="irAPagina" />
            </div>
        </div>

        <BaseModal v-model="modal" titulo="Detalle de Auditoría" tamano="xl" centrado desplazable>
            <div v-if="!detalle" class="text-center py-5"><div class="spinner-border text-primary" role="status" /></div>
            <div v-else class="row g-3">
                <div class="col-md-6">
                    <div class="card border mb-0">
                        <div class="card-header p-2 bg-light"><h6 class="mb-0 fw-semibold"><i class="ri-information-line me-1" />Información General</h6></div>
                        <div class="card-body p-3">
                            <table class="table table-sm mb-0">
                                <tbody>
                                    <tr><th class="text-muted fw-normal">ID Auditoría</th><td class="cifra">#{{ detalle.id }}</td></tr>
                                    <tr><th class="text-muted fw-normal">Evento</th><td><span class="badge" :class="`bg-soft-${color(detalle.evento)} text-${color(detalle.evento)}`">{{ detalle.evento_label }}</span></td></tr>
                                    <tr><th class="text-muted fw-normal">Módulo</th><td class="fw-semibold">{{ detalle.modelo_label }}</td></tr>
                                    <tr><th class="text-muted fw-normal">ID Registro</th><td class="cifra">#{{ detalle.registro_id }}</td></tr>
                                    <tr><th class="text-muted fw-normal">Fecha / Hora</th><td class="cifra">{{ detalle.created_at }}</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card border mb-0">
                        <div class="card-header p-2 bg-light"><h6 class="mb-0 fw-semibold"><i class="ri-user-line me-1" />Usuario y Sesión</h6></div>
                        <div class="card-body p-3">
                            <table class="table table-sm mb-0">
                                <tbody>
                                    <tr><th class="text-muted fw-normal">Usuario</th><td class="fw-semibold">{{ detalle.usuario }}</td></tr>
                                    <tr><th class="text-muted fw-normal">Email</th><td>{{ detalle.usuario_email }}</td></tr>
                                    <tr><th class="text-muted fw-normal">IP</th><td class="cifra">{{ detalle.ip }}</td></tr>
                                    <tr><th class="text-muted fw-normal">URL</th><td><small class="text-break">{{ detalle.url }}</small></td></tr>
                                    <tr><th class="text-muted fw-normal">User Agent</th><td><small class="text-muted text-break">{{ detalle.user_agent }}</small></td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="col-12">
                    <div class="card border mb-0">
                        <div class="card-header p-2 bg-light"><h6 class="mb-0 fw-semibold"><i class="ri-file-list-3-line me-1" />Detalle de Cambios</h6></div>
                        <div class="card-body p-3">
                            <p v-if="!filasCambios.length" class="text-muted mb-0">Sin cambios registrados.</p>
                            <div v-else class="table-responsive">
                                <table class="table table-sm table-bordered mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Campo</th>
                                            <th v-if="detalle.evento !== 'created'">{{ detalle.evento === 'deleted' ? 'Valor eliminado' : 'Valor Anterior' }}</th>
                                            <th v-if="detalle.evento !== 'deleted'">{{ detalle.evento === 'created' ? 'Valor' : 'Valor Nuevo' }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="fila in filasCambios" :key="fila.campo" :class="{ 'table-warning': fila.cambio && detalle.evento === 'updated' }">
                                            <td class="fw-semibold text-nowrap">{{ fila.campo }}</td>
                                            <td v-if="detalle.evento !== 'created'" class="text-danger text-break">{{ fila.anterior }}</td>
                                            <td v-if="detalle.evento !== 'deleted'" class="text-success text-break">{{ fila.nuevo }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <template #pie="{ cerrar }">
                <button type="button" class="btn btn-secondary" @click="cerrar"><i class="ri-close-line align-middle me-1" /> Cerrar</button>
            </template>
        </BaseModal>
    </div>
</template>
