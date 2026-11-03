<script setup>
import { computed, ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import GaleriaArchivos from '@/Components/Evidencias/GaleriaArchivos.vue';
import PageHeader from '@/Components/Layout/PageHeader.vue';
import BaseModal from '@/Components/Ui/BaseModal.vue';
import CampoError from '@/Components/Ui/CampoError.vue';
import EstadoVacio from '@/Components/Ui/EstadoVacio.vue';
import Paginacion from '@/Components/Ui/Paginacion.vue';
import { useListado } from '@/Composables/useListado';
import { ICONO_GRUPO, tituloArticulo } from '@/Utils/evidencias';
import { cifra, fecha, fechaHora, guion } from '@/Utils/formato';

/**
 * Bandeja de publicación para el portal ciudadano: revisa los artículos que se pidió publicar, elige qué
 * fotografías son públicas y aprueba, rechaza o retira. Nada se publica sin pasar por aquí.
 */
const props = defineProps({
    articulos: { type: Object, required: true },
    filtros: { type: Object, default: () => ({}) },
    totales: { type: Object, default: () => ({}) },
    reglas: { type: Object, required: true },
});

const { filtros, cargando, irAPagina } = useListado('evidencias.publicacion.index', { estado: props.filtros.estado, search: props.filtros.search ?? '' }, ['articulos', 'totales']);

const PESTANAS = [
    { estado: 'PENDIENTE_REVISION', etiqueta: 'Pendientes', icono: 'ri-time-line', vacio: 'No hay artículos pendientes de revisión' },
    { estado: 'PUBLICADO', etiqueta: 'Publicados', icono: 'ri-earth-line', vacio: 'No hay artículos publicados' },
    { estado: 'RETIRADO', etiqueta: 'Retirados', icono: 'ri-forbid-line', vacio: 'No hay artículos retirados' },
];
const pestana = computed(() => PESTANAS.find((p) => p.estado === filtros.estado) ?? PESTANAS[0]);

// Las fotos se marcan públicas en el lugar: se actualiza la lista sin recargar.
const fotosCambiadas = ref({});
const fotosDe = (articulo) => (articulo.archivos ?? []).map((a) => fotosCambiadas.value[a.id] ?? a);
const fotosPublicas = (articulo) => fotosDe(articulo).filter((a) => a.es_publico).length;
const alCambiarFoto = (archivo) => {
    fotosCambiadas.value = { ...fotosCambiadas.value, [archivo.id]: archivo };
};

/** Lo que verá el ciudadano: nada de CUD, lugar, funcionarios ni documentos. */
const vistaPublica = (articulo) => [
    articulo.tipo?.nombre,
    [articulo.marca, articulo.modelo].filter(Boolean).join(' ') || articulo.descripcion,
    articulo.color,
    articulo.evidencia?.fecha_hora ? `recuperado el ${fecha(articulo.evidencia.fecha_hora)}` : null,
].filter(Boolean).join(' · ');

const procesando = ref(null);
const aprobar = (articulo) => {
    router.post(route('evidencias.publicacion.aprobar', articulo.id), {}, {
        preserveScroll: true,
        onStart: () => (procesando.value = articulo.id),
        onFinish: () => (procesando.value = null),
    });
};

// Rechazar / retirar piden un motivo.
const modal = ref(false);
const accion = ref(null);
const articuloEnCurso = ref(null);
const formMotivo = useForm({ motivo: '' });
const abrirMotivo = (articulo, tipo) => {
    accion.value = tipo;
    articuloEnCurso.value = articulo;
    formMotivo.reset();
    formMotivo.clearErrors();
    modal.value = true;
};
const confirmarMotivo = () => {
    const ruta = accion.value === 'retirar' ? 'evidencias.publicacion.retirar' : 'evidencias.publicacion.rechazar';
    formMotivo.post(route(ruta, articuloEnCurso.value.id), { preserveScroll: true, onSuccess: () => (modal.value = false) });
};
const tituloModal = computed(() => (accion.value === 'retirar' ? 'Retirar del portal' : 'Rechazar publicación'));
</script>

<template>
    <div>
        <PageHeader titulo="Publicación en el portal ciudadano" :migas="[{ etiqueta: 'Evidencias', ruta: 'evidencias.index' }, { etiqueta: 'Publicación' }]" />

        <div class="card">
            <div class="card-header pb-0 border-bottom-0">
                <div class="d-flex flex-wrap align-items-end gap-3">
                    <ul class="nav nav-tabs nav-tabs-custom card-header-tabs border-bottom-0 flex-grow-1" role="tablist">
                        <li v-for="p in PESTANAS" :key="p.estado" class="nav-item" role="presentation">
                            <button type="button" class="nav-link" :class="{ active: filtros.estado === p.estado }" role="tab" :aria-selected="filtros.estado === p.estado" @click="filtros.estado = p.estado">
                                <i :class="p.icono" class="align-bottom me-1" /> {{ p.etiqueta }}
                                <span class="badge rounded-pill ms-1 cifra" :class="p.estado === 'PENDIENTE_REVISION' && totales[p.estado] ? 'bg-warning' : 'bg-soft-secondary text-secondary'">{{ cifra(totales[p.estado] ?? 0) }}</span>
                            </button>
                        </li>
                    </ul>
                    <div class="pb-2" style="min-width: 18rem">
                        <div class="input-group">
                            <span class="input-group-text bg-primary text-white border-primary"><i class="ri-search-line" /></span>
                            <input v-model="filtros.search" type="search" class="form-control" placeholder="IMEI, serie, marca, modelo o código" aria-label="Buscar artículos">
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-body border-top" :aria-busy="cargando">
                <div v-if="cargando" class="text-center py-5"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Cargando…</span></div></div>
                <template v-else>
                    <article v-for="(articulo, indice) in articulos.data" :key="articulo.id" class="d-flex flex-column flex-lg-row gap-3" :class="{ 'border-top pt-3 mt-3': indice > 0 }">
                        <div class="flex-grow-1 min-w-0">
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <i :class="ICONO_GRUPO[articulo.tipo?.grupo] ?? 'mdi mdi-cube-outline'" class="fs-18 text-primary" aria-hidden="true" />
                                <h6 class="mb-0">{{ tituloArticulo(articulo) }}</h6>
                            </div>
                            <p class="small text-muted mb-2">
                                Evidencia
                                <Link :href="route('evidencias.show', articulo.evidencia.id)">{{ articulo.evidencia.codigo ?? `#${articulo.evidencia.id}` }}</Link>
                                · <span class="cifra">{{ fechaHora(articulo.evidencia.fecha_hora) }}</span>
                                <template v-if="articulo.evidencia.caso"> · CUD <span class="cifra">{{ articulo.evidencia.caso.codigo }}</span></template>
                                <span v-if="articulo.evidencia.estado === 'ANULADA'" class="badge bg-soft-danger text-danger ms-1">Evidencia anulada</span>
                            </p>
                            <dl class="row small mb-2">
                                <div v-if="articulo.imei_1" class="col-sm-4"><dt class="fw-medium text-muted">IMEI 1</dt><dd class="cifra mb-1">{{ articulo.imei_1 }}</dd></div>
                                <div v-if="articulo.imei_2" class="col-sm-4"><dt class="fw-medium text-muted">IMEI 2</dt><dd class="cifra mb-1">{{ articulo.imei_2 }}</dd></div>
                                <div v-if="articulo.numero_serie" class="col-sm-4"><dt class="fw-medium text-muted">N.° de serie</dt><dd class="cifra mb-1">{{ articulo.numero_serie }}</dd></div>
                            </dl>
                            <div class="vista-publica rounded px-3 py-2 mb-2">
                                <span class="d-block small fw-medium">Lo que verá el ciudadano</span>
                                <span class="small">{{ vistaPublica(articulo) }} · <span class="cifra">{{ fotosPublicas(articulo) }}</span> foto(s) pública(s)</span>
                            </div>
                            <GaleriaArchivos :archivos="fotosDe(articulo)" :tipos="reglas.tiposArchivo" :publicable="filtros.estado !== 'RETIRADO'" vacio="Sin fotografías: se publicará sin foto." @cambiado="alCambiarFoto" />
                        </div>

                        <div class="flex-shrink-0 acciones-publicacion">
                            <template v-if="filtros.estado === 'PENDIENTE_REVISION'">
                                <button type="button" class="btn btn-success w-100 mb-2" :disabled="procesando === articulo.id || articulo.evidencia.estado === 'ANULADA'" @click="aprobar(articulo)">
                                    <i class="ri-check-line align-middle me-1" /> Aprobar y publicar
                                </button>
                                <button type="button" class="btn btn-soft-danger w-100" :disabled="procesando === articulo.id" @click="abrirMotivo(articulo, 'rechazar')">
                                    <i class="ri-close-line align-middle me-1" /> Rechazar
                                </button>
                                <small class="text-muted d-block mt-2">Pedido el <span class="cifra">{{ fechaHora(articulo.updated_at) }}</span></small>
                            </template>
                            <template v-else-if="filtros.estado === 'PUBLICADO'">
                                <p class="small mb-2">
                                    Publicado el <span class="cifra">{{ fechaHora(articulo.publicado_at) }}</span><br>
                                    por <span class="fw-medium">{{ articulo.autorizado_por?.name ?? '—' }}</span>
                                </p>
                                <button type="button" class="btn btn-soft-danger w-100" @click="abrirMotivo(articulo, 'retirar')">
                                    <i class="ri-forbid-line align-middle me-1" /> Retirar del portal
                                </button>
                            </template>
                            <template v-else>
                                <p class="small text-muted mb-2">Motivo: {{ guion(articulo.motivo_publicacion) }}</p>
                                <button type="button" class="btn btn-soft-success w-100" :disabled="procesando === articulo.id || articulo.evidencia.estado === 'ANULADA'" @click="aprobar(articulo)">
                                    <i class="ri-earth-line align-middle me-1" /> Volver a publicar
                                </button>
                            </template>
                        </div>
                    </article>

                    <EstadoVacio
                        v-if="!articulos.data.length"
                        :titulo="filtros.search ? 'Ningún artículo coincide con la búsqueda' : pestana.vacio"
                    />
                </template>
                <Paginacion :paginado="articulos" entidad="artículos" :deshabilitado="cargando" @ir="irAPagina" />
            </div>
        </div>

        <BaseModal v-model="modal" :titulo="tituloModal" centrado estatico>
            <p v-if="articuloEnCurso" class="mb-3">{{ tituloArticulo(articuloEnCurso) }}</p>
            <label for="motivoPublicacion" class="form-label">Motivo <span class="text-danger">*</span></label>
            <textarea id="motivoPublicacion" v-model="formMotivo.motivo" rows="3" maxlength="500" class="form-control" :class="{ 'is-invalid': formMotivo.errors.motivo }" :placeholder="accion === 'retirar' ? 'Ej: Entregado a su propietario' : 'Ej: La fotografía muestra datos personales'" />
            <CampoError :mensaje="formMotivo.errors.motivo" />
            <template #pie="{ cerrar }">
                <button type="button" class="btn btn-light" @click="cerrar">Cancelar</button>
                <button type="button" class="btn btn-danger" :disabled="formMotivo.processing" @click="confirmarMotivo">{{ accion === 'retirar' ? 'Retirar' : 'Rechazar' }}</button>
            </template>
        </BaseModal>
    </div>
</template>

<style scoped>
.acciones-publicacion {
    width: 100%;
}

@media (min-width: 992px) {
    .acciones-publicacion {
        width: 13rem;
    }
}

.vista-publica {
    background: var(--vz-light);
}

.min-w-0 {
    min-width: 0;
}
</style>
