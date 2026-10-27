<script setup>
import { computed, ref } from 'vue';
import PageHeader from '@/Components/Layout/PageHeader.vue';
import BaseModal from '@/Components/Ui/BaseModal.vue';
import EstadoVacio from '@/Components/Ui/EstadoVacio.vue';
import FilasCargando from '@/Components/Ui/FilasCargando.vue';
import Paginacion from '@/Components/Ui/Paginacion.vue';
import { useListado } from '@/Composables/useListado';
import { useNotificacion } from '@/Composables/useNotificacion';
import { fechaHora, guion } from '@/Utils/formato';
import { http } from '@/Utils/http';

const props = defineProps({
    registros: { type: Object, required: true },
    modulos: { type: Array, default: () => [] },
    /** Nombre legible de cada módulo (`listado_personas` → «Búsqueda en Personas»). */
    etiquetas: { type: Object, default: () => ({}) },
    roles: { type: Array, default: () => [] },
    filtros: { type: Object, default: () => ({}) },
});

const { errorDeRespuesta } = useNotificacion();
const { filtros, cargando, irAPagina } = useListado(
    'auditar-consultas.index',
    {
        search: props.filtros.search ?? '',
        modulo: props.filtros.modulo ?? '',
        rol_usuario: props.filtros.rol_usuario ?? '',
        fecha_inicio: props.filtros.fecha_inicio ?? '',
        fecha_fin: props.filtros.fecha_fin ?? '',
    },
    ['registros'],
);

const numero = (indice) => (props.registros.from ?? 1) + indice;

const ETIQUETAS_CRITERIO = { tipo_filtro: 'Filtro', busqueda: 'Búsqueda', fecha_inicio: 'Fecha inicio', fecha_fin: 'Fecha fin' };

const modal = ref(false);
const detalle = ref(null);
const ver = async (registro) => {
    detalle.value = null;
    modal.value = true;
    try {
        detalle.value = (await http.get(route('auditar-consultas.show', registro.id))).data;
    } catch (err) {
        modal.value = false;
        errorDeRespuesta(err);
    }
};

const etiqueta = (modulo) => props.etiquetas[modulo] ?? modulo;

const criterios = computed(() =>
    Object.entries(detalle.value?.criterios_consulta ?? {}).filter(([, valor]) => valor !== null && valor !== ''),
);
</script>

<template>
    <div>
        <PageHeader titulo="Auditoría de Consultas" :migas="[{ etiqueta: 'Seguridad' }, { etiqueta: 'Auditoría de Consultas' }]" />

        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-3">Registro de Auditoría</h5>
                <div class="row g-2 align-items-end">
                    <div class="col-lg-3">
                        <div class="input-group">
                            <span class="input-group-text bg-primary text-white border-primary"><i class="ri-search-line" /></span>
                            <input v-model="filtros.search" type="search" class="form-control" placeholder="Buscar usuario o identificador..." aria-label="Buscar">
                        </div>
                    </div>
                    <div class="col-lg-2">
                        <select v-model="filtros.modulo" class="form-select" aria-label="Módulo">
                            <option value="">Todos los Módulos</option>
                            <option v-for="modulo in modulos" :key="modulo" :value="modulo">{{ etiqueta(modulo) }}</option>
                        </select>
                    </div>
                    <div class="col-lg-2">
                        <select v-model="filtros.rol_usuario" class="form-select" aria-label="Rol">
                            <option value="">Todos los Roles</option>
                            <option v-for="rol in roles" :key="rol" :value="rol">{{ rol }}</option>
                        </select>
                    </div>
                    <div class="col-lg-2">
                        <label for="fechaInicioConsultas" class="form-label small fw-semibold mb-1">Fecha Inicio</label>
                        <input id="fechaInicioConsultas" v-model="filtros.fecha_inicio" type="date" class="form-control form-control-sm">
                    </div>
                    <div class="col-lg-2">
                        <label for="fechaFinConsultas" class="form-label small fw-semibold mb-1">Fecha Fin</label>
                        <input id="fechaFinConsultas" v-model="filtros.fecha_fin" type="date" class="form-control form-control-sm">
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr><th>#</th><th>Usuario</th><th>Rol</th><th>Módulo</th><th class="text-center">Resultados</th><th>Dispositivo</th><th>Fecha/Hora</th><th class="text-center">Acciones</th></tr>
                        </thead>
                        <tbody>
                            <FilasCargando v-if="cargando" :columnas="8" />
                            <template v-else>
                                <tr v-for="(registro, indice) in registros.data" :key="registro.id">
                                    <td class="text-muted">{{ numero(indice) }}</td>
                                    <td>
                                        <div class="fw-semibold">{{ registro.usuario?.name ?? '—' }}</div>
                                        <small class="text-muted">{{ registro.usuario?.email }}</small>
                                    </td>
                                    <td><span class="badge badge-outline-secondary">{{ registro.rol_usuario }}</span></td>
                                    <td><span class="badge badge-outline-primary">{{ etiqueta(registro.modulo) }}</span></td>
                                    <td class="text-center"><span class="badge badge-outline-secondary cifra">{{ registro.cantidad_resultados }}</span></td>
                                    <td class="small text-muted">{{ registro.user_agent ? `${registro.user_agent.browser} (${registro.user_agent.platform})` : '—' }}</td>
                                    <td class="cifra small text-nowrap">{{ fechaHora(registro.created_at) }}</td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-sm btn-soft-secondary" title="Ver detalles" @click="ver(registro)"><i class="ri-eye-fill align-bottom" /></button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
                <EstadoVacio v-if="!cargando && !registros.data.length" titulo="No se encontraron consultas registradas" />
                <Paginacion :paginado="registros" entidad="registros" :deshabilitado="cargando" @ir="irAPagina" />
            </div>
        </div>

        <BaseModal v-model="modal" titulo="Detalles de Auditoría" tamano="lg" centrado desplazable>
            <div v-if="!detalle" class="text-center py-5"><div class="spinner-border text-primary" role="status" /></div>
            <template v-else>
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-muted mb-2">Información del Usuario</h6>
                        <dl class="row mb-0">
                            <dt class="col-sm-4">Usuario:</dt><dd class="col-sm-8"><strong>{{ detalle.usuario?.name ?? '—' }}</strong></dd>
                            <dt class="col-sm-4">Email:</dt><dd class="col-sm-8 text-break">{{ detalle.usuario?.email ?? '—' }}</dd>
                            <dt class="col-sm-4">Rol:</dt><dd class="col-sm-8"><span class="badge badge-outline-secondary">{{ detalle.rol_usuario }}</span></dd>
                        </dl>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-muted mb-2">Información de la Consulta</h6>
                        <dl class="row mb-0">
                            <dt class="col-sm-5">Módulo Accedido:</dt><dd class="col-sm-7"><span class="badge badge-outline-primary">{{ etiqueta(detalle.modulo) }}</span></dd>
                            <dt class="col-sm-5">Cantidad Resultados:</dt><dd class="col-sm-7"><span class="badge badge-outline-success cifra">{{ detalle.cantidad_resultados }}</span></dd>
                            <dt class="col-sm-5">Fecha/Hora:</dt><dd class="col-sm-7"><small class="text-muted cifra">{{ fechaHora(detalle.created_at) }}</small></dd>
                        </dl>
                    </div>
                </div>
                <hr>
                <h6 class="text-muted mb-2">Criterios de Búsqueda</h6>
                <dl v-if="criterios.length" class="row mb-0">
                    <template v-for="[clave, valor] in criterios" :key="clave">
                        <dt class="col-sm-3">{{ ETIQUETAS_CRITERIO[clave] ?? clave }}:</dt>
                        <dd class="col-sm-9"><code>{{ valor }}</code></dd>
                    </template>
                </dl>
                <p v-else class="text-muted mb-0">Sin criterios registrados.</p>
                <hr>
                <h6 class="text-muted mb-2">Registros Accedidos</h6>
                <div v-if="detalle.accesos?.length" class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light"><tr><th>ID</th><th>Módulo</th><th>Información Accedida</th><th>Fecha Acceso</th></tr></thead>
                        <tbody>
                            <tr v-for="acceso in detalle.accesos" :key="`${acceso.modelo}-${acceso.id}-${acceso.fecha_acceso}`">
                                <td class="cifra">{{ acceso.id }}</td>
                                <td><span class="badge bg-soft-success text-success">{{ acceso.modelo }}</span></td>
                                <td>
                                    <a v-if="acceso.url" :href="acceso.url" target="_blank" rel="noopener" class="text-decoration-underline">{{ acceso.descripcion }}</a>
                                    <template v-else>{{ acceso.descripcion }}</template>
                                </td>
                                <td class="small cifra">{{ fechaHora(acceso.fecha_acceso) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-else class="text-muted mb-0">No se abrió ningún registro en esta consulta.</p>
                <hr>
                <h6 class="text-muted mb-2">Información Técnica del Consultor <small>(los datos pueden ser imprecisos)</small></h6>
                <dl class="row mb-0">
                    <dt class="col-sm-3">Dirección IP:</dt><dd class="col-sm-9"><code>{{ detalle.ip_usuario }}</code></dd>
                    <template v-if="detalle.ubicacion">
                        <dt class="col-sm-3">Ubicación:</dt>
                        <dd class="col-sm-9"><small class="text-muted">{{ guion(detalle.ubicacion.cityName) }} - {{ guion(detalle.ubicacion.countryName) }}</small></dd>
                    </template>
                    <dt class="col-sm-3">Navegador:</dt>
                    <dd class="col-sm-9">
                        <small class="text-muted text-break">
                            {{ detalle.user_agent ? `Navegador: ${detalle.user_agent.browser} | S.O.: ${detalle.user_agent.platform} | Dispositivo: ${detalle.user_agent.device}` : '—' }}
                        </small>
                    </dd>
                </dl>
            </template>
            <template #pie="{ cerrar }">
                <button type="button" class="btn btn-secondary" @click="cerrar"><i class="ri-close-line align-middle me-1" /> Cerrar</button>
            </template>
        </BaseModal>
    </div>
</template>
