<script setup>
import { computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import GaleriaArchivos from '@/Components/Evidencias/GaleriaArchivos.vue';
import PageHeader from '@/Components/Layout/PageHeader.vue';
import { useNotificacion } from '@/Composables/useNotificacion';
import { usePermisos } from '@/Composables/usePermisos';
import { CLASE_ESTADO, CLASE_PUBLICACION, ETIQUETA_ESTADO, ETIQUETA_PUBLICACION, ICONO_GRUPO, camposDelGrupo, tituloArticulo } from '@/Utils/evidencias';
import { cifra, fecha, fechaHora, guion } from '@/Utils/formato';

const props = defineProps({
    evidencia: { type: Object, required: true },
    archivos: { type: Object, default: () => ({ visibles: [], ocultos: 0 }) },
    /** id del artículo => { imeis: [...en la tabla de IMEIs], otras: [{ id, codigo }] } */
    coincidencias: { type: [Object, Array], default: () => ({}) },
    reglas: { type: Object, required: true },
});

const { puede } = usePermisos();
const { confirmar } = useNotificacion();

const generales = computed(() => props.archivos.visibles.filter((a) => !a.evidencia_articulo_id));
const delArticulo = (articulo) => props.archivos.visibles.filter((a) => a.evidencia_articulo_id === articulo.id);
const tiene = (articulo, campo) => camposDelGrupo(props.reglas, articulo.tipo?.grupo).includes(campo);
const coincidencia = (articulo) => props.coincidencias?.[articulo.id] ?? null;
const titulo = computed(() => `Evidencia ${props.evidencia.codigo ?? `del ${fechaHora(props.evidencia.fecha_hora)}`}`);

const eliminar = async () => {
    if (await confirmar({
        titulo: `¿Eliminar ${props.evidencia.codigo ? `la evidencia ${props.evidencia.codigo}` : 'esta evidencia'}?`,
        texto: `Se eliminan sus ${props.evidencia.articulos.length} artículo(s) y sus archivos; los publicados se retiran del portal.`,
        confirmar: 'Sí, eliminar',
        peligro: true,
    })) {
        router.delete(route('evidencias.destroy', props.evidencia.id));
    }
};
</script>

<template>
    <div>
        <PageHeader :titulo="titulo" :migas="[{ etiqueta: 'Evidencias', ruta: 'evidencias.index' }, { etiqueta: evidencia.codigo ?? 'Detalle' }]">
            <template #acciones>
                <div class="d-flex gap-2">
                    <Link :href="route('evidencias.index')" class="btn btn-soft-secondary"><i class="ri-arrow-left-line align-middle me-1" /> Volver</Link>
                    <Link v-if="puede('evidencias.editar')" :href="route('evidencias.edit', evidencia.id)" class="btn btn-soft-primary"><i class="ri-pencil-line align-middle me-1" /> Editar</Link>
                    <button v-if="puede('evidencias.eliminar')" type="button" class="btn btn-soft-danger" @click="eliminar"><i class="ri-delete-bin-line align-middle me-1" /> Eliminar</button>
                </div>
            </template>
        </PageHeader>

        <div class="card">
            <div class="card-body">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                    <span class="badge fs-12" :class="CLASE_ESTADO[evidencia.estado]">{{ ETIQUETA_ESTADO[evidencia.estado] }}</span>
                    <span class="text-muted small">Registrada por {{ evidencia.usuario?.name ?? '—' }} el <span class="cifra">{{ fechaHora(evidencia.created_at) }}</span></span>
                </div>
                <dl class="row mb-0 datos-evidencia">
                    <div class="col-md-3 col-6"><dt>Fecha y hora</dt><dd class="cifra">{{ fechaHora(evidencia.fecha_hora) }}</dd></div>
                    <div class="col-md-3 col-6"><dt>CUD / Caso</dt><dd class="cifra">
                        <Link v-if="evidencia.caso && puede('casos.ver')" :href="route('casos.show', evidencia.caso.id)">{{ evidencia.caso.codigo }}</Link>
                        <template v-else>{{ guion(evidencia.caso?.codigo) }}</template>
                    </dd></div>
                    <div class="col-md-3 col-6"><dt>Código interno</dt><dd>{{ guion(evidencia.codigo) }}</dd></div>
                    <div class="col-md-3 col-6"><dt>N.° de acta</dt><dd>{{ guion(evidencia.numero_acta) }}</dd></div>
                    <div class="col-md-6"><dt>Lugar</dt><dd>{{ evidencia.lugar }}</dd></div>
                    <div class="col-md-3 col-6"><dt>Unidad / División</dt><dd>{{ guion(evidencia.division?.division) }}</dd></div>
                    <div class="col-md-3 col-6"><dt>Funcionario responsable</dt><dd>{{ evidencia.funcionario_responsable }}</dd></div>
                    <div v-if="evidencia.detalle_lugar" class="col-12"><dt>Detalle del lugar</dt><dd class="texto-largo">{{ evidencia.detalle_lugar }}</dd></div>
                    <div v-if="evidencia.observaciones" class="col-12"><dt>Observaciones</dt><dd class="texto-largo">{{ evidencia.observaciones }}</dd></div>
                </dl>
                <div v-if="generales.length || archivos.ocultos" class="border-top pt-3 mt-1">
                    <h6 class="mb-2">Actas y documentos</h6>
                    <GaleriaArchivos :archivos="generales" :tipos="reglas.tiposArchivo" vacio="" />
                    <small v-if="archivos.ocultos" class="text-muted d-block mt-1"><i class="ri-lock-line" /> {{ archivos.ocultos }} documento(s) interno(s): su usuario no tiene permiso para verlos.</small>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Artículos <span class="badge bg-soft-primary text-primary cifra ms-1">{{ evidencia.articulos.length }}</span></h5>
            </div>
            <div class="card-body">
                <article v-for="(articulo, indice) in evidencia.articulos" :key="articulo.id" class="articulo-detalle" :class="{ 'border-top pt-3 mt-3': indice > 0 }">
                    <header class="d-flex flex-wrap align-items-center gap-2 mb-2">
                        <span class="avatar-xs flex-shrink-0"><span class="avatar-title rounded bg-soft-primary text-primary fs-16"><i :class="ICONO_GRUPO[articulo.tipo?.grupo] ?? 'mdi mdi-cube-outline'" /></span></span>
                        <h6 class="mb-0 me-auto">{{ indice + 1 }}. {{ tituloArticulo(articulo) }}</h6>
                        <span class="badge" :class="CLASE_PUBLICACION[articulo.estado_publicacion]">{{ ETIQUETA_PUBLICACION[articulo.estado_publicacion] }}</span>
                    </header>

                    <div v-if="coincidencia(articulo)" class="alert alert-warning py-2 small mb-2" role="status">
                        <i class="ri-alarm-warning-line align-middle me-1" />
                        <template v-if="coincidencia(articulo).imeis.length">
                            El IMEI <span class="cifra fw-semibold">{{ coincidencia(articulo).imeis.join(', ') }}</span> figura en la tabla de IMEIs de las investigaciones<template v-if="puede('imeis.listar')">
                                (<Link :href="route('imeis.index', { search: coincidencia(articulo).imeis[0] })">ver</Link>)</template>.
                        </template>
                        <template v-if="coincidencia(articulo).otras.length">
                            También aparece en {{ coincidencia(articulo).otras.length === 1 ? 'otra evidencia' : `${coincidencia(articulo).otras.length} evidencias más` }}:
                            <template v-for="(otra, i) in coincidencia(articulo).otras" :key="otra.id">
                                <Link :href="route('evidencias.show', otra.id)">{{ otra.codigo ?? `#${otra.id}` }}</Link><template v-if="i < coincidencia(articulo).otras.length - 1">, </template>
                            </template>.
                        </template>
                    </div>

                    <dl class="row mb-2 datos-evidencia">
                        <div v-if="tiene(articulo, 'marca')" class="col-md-2 col-6"><dt>Marca</dt><dd>{{ guion(articulo.marca) }}</dd></div>
                        <div v-if="tiene(articulo, 'modelo')" class="col-md-2 col-6"><dt>Modelo</dt><dd>{{ guion(articulo.modelo) }}</dd></div>
                        <div v-if="tiene(articulo, 'color')" class="col-md-2 col-6"><dt>Color</dt><dd>{{ guion(articulo.color) }}</dd></div>
                        <div v-if="tiene(articulo, 'imei_1')" class="col-md-3 col-6"><dt>IMEI 1</dt><dd class="cifra">{{ guion(articulo.imei_1) }}</dd></div>
                        <div v-if="tiene(articulo, 'imei_1')" class="col-md-3 col-6"><dt>IMEI 2</dt><dd class="cifra">{{ guion(articulo.imei_2) }}</dd></div>
                        <div v-if="tiene(articulo, 'numero_serie')" class="col-md-3 col-6"><dt>N.° de serie</dt><dd class="cifra">{{ guion(articulo.numero_serie) }}</dd></div>
                        <div v-if="tiene(articulo, 'cantidad')" class="col-md-2 col-6"><dt>Cantidad</dt><dd class="cifra">{{ cifra(Number(articulo.cantidad)) }} {{ articulo.unidad_medida ?? '' }}</dd></div>
                        <div v-if="articulo.descripcion" class="col-12"><dt>Descripción</dt><dd>{{ articulo.descripcion }}</dd></div>
                        <div class="col-md-2 col-6"><dt>Estado físico</dt><dd>{{ guion(articulo.estado_fisico) }}</dd></div>
                        <div class="col-md-4 col-6"><dt>Recogido por</dt><dd>{{ guion(articulo.funcionario_recoge) }}</dd></div>
                        <div class="col-md-3 col-6"><dt>Fecha de recepción</dt><dd class="cifra">{{ guion(fecha(articulo.fecha_recepcion)) }}</dd></div>
                        <div v-if="articulo.observaciones" class="col-12"><dt>Observaciones</dt><dd class="texto-largo">{{ articulo.observaciones }}</dd></div>
                    </dl>

                    <p v-if="articulo.estado_publicacion === 'PUBLICADO'" class="small text-success mb-2">
                        <i class="ri-earth-line align-middle" /> Publicado el <span class="cifra">{{ fechaHora(articulo.publicado_at) }}</span> por {{ articulo.autorizado_por?.name ?? '—' }}.
                    </p>
                    <p v-else-if="articulo.motivo_publicacion" class="small text-muted mb-2">Publicación: {{ articulo.motivo_publicacion }}</p>

                    <GaleriaArchivos :archivos="delArticulo(articulo)" :tipos="reglas.tiposArchivo" vacio="Sin fotografías ni comprobantes." />
                </article>
            </div>
        </div>
    </div>
</template>

<style scoped>
.datos-evidencia dt {
    font-weight: 500;
    font-size: 0.75rem;
    color: var(--vz-secondary-color);
    margin-bottom: 0.125rem;
}

.datos-evidencia dd {
    margin-bottom: 0.75rem;
}

.texto-largo {
    white-space: pre-line;
    max-width: 75ch;
}
</style>
