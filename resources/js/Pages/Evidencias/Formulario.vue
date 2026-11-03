<script setup>
import { computed, nextTick, ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import SelectorCaso from '@/Components/Casos/SelectorCaso.vue';
import ArticuloEvidencia from '@/Components/Evidencias/ArticuloEvidencia.vue';
import GaleriaArchivos from '@/Components/Evidencias/GaleriaArchivos.vue';
import PageHeader from '@/Components/Layout/PageHeader.vue';
import CampoError from '@/Components/Ui/CampoError.vue';
import SubirArchivos from '@/Components/Ui/SubirArchivos.vue';
import { useNotificacion } from '@/Composables/useNotificacion';
import { tamano } from '@/Utils/evidencias';
import { hoyIso } from '@/Utils/formato';

/**
 * Registrar o editar una evidencia: (1) datos del procedimiento y (2) sus artículos, que se agregan uno a uno. Todo se
 * guarda en una sola operación, con sus fotografías y documentos.
 */
const props = defineProps({
    evidencia: { type: Object, default: null },
    archivos: { type: Object, default: () => ({ visibles: [], ocultos: 0 }) },
    tipos: { type: Array, default: () => [] },
    divisiones: { type: Array, default: () => [] },
    reglas: { type: Object, required: true },
});

const { confirmar, aviso } = useNotificacion();

const editando = computed(() => Boolean(props.evidencia?.id));
const hoy = hoyIso();
const ahora = () => {
    const f = new Date();
    f.setMinutes(f.getMinutes() - f.getTimezoneOffset());

    return f.toISOString().slice(0, 16);
};
const maximoFechaHora = ahora();

const articuloVacio = (base = {}) => ({
    id: null,
    tipo_articulo_id: null,
    descripcion: '',
    marca: '',
    modelo: '',
    color: '',
    imei_1: '',
    imei_2: '',
    numero_serie: '',
    cantidad: 1,
    unidad_medida: '',
    estado_fisico: null,
    funcionario_recoge: '',
    fecha_recepcion: '',
    observaciones: '',
    solicitar_publicacion: false,
    estado_publicacion: null,
    publicado_at: null,
    motivo_publicacion: null,
    fotos: [],
    fotos_imei: [],
    comprobantes: [],
    // Clave estable para Vue (los artículos se agregan, duplican y quitan).
    clave: crypto.getRandomValues(new Uint32Array(1))[0],
    ...base,
});

const desdeGuardado = (a) => articuloVacio({
    ...Object.fromEntries(Object.keys(articuloVacio()).filter((c) => !['fotos', 'fotos_imei', 'comprobantes', 'clave'].includes(c)).map((c) => [c, a[c] ?? articuloVacio()[c]])),
    cantidad: a.cantidad === null || a.cantidad === undefined ? 1 : Number(a.cantidad),
    solicitar_publicacion: a.estado_publicacion === 'PENDIENTE_REVISION',
});

const e = props.evidencia;
const form = useForm({
    codigo: e?.codigo ?? '',
    id_caso: e?.id_caso ?? null,
    id_division: e?.id_division ?? null,
    fecha_hora: e?.fecha_hora ?? ahora(),
    lugar: e?.lugar ?? '',
    detalle_lugar: e?.detalle_lugar ?? '',
    funcionario_responsable: e?.funcionario_responsable ?? '',
    numero_acta: e?.numero_acta ?? '',
    observaciones: e?.observaciones ?? '',
    estado: e?.estado ?? 'REGISTRADA',
    actas: [],
    documentos: [],
    articulos: e?.articulos?.length ? e.articulos.map(desdeGuardado) : [articuloVacio()],
});

// Archivos ya guardados (al editar): se quitan al momento, sin esperar a «Guardar».
const guardados = ref([...(props.archivos.visibles ?? [])]);
const generales = computed(() => guardados.value.filter((a) => !a.evidencia_articulo_id));
const delArticulo = (articulo) => (articulo.id ? guardados.value.filter((a) => a.evidencia_articulo_id === articulo.id) : []);
const quitarGuardado = (id) => {
    guardados.value = guardados.value.filter((a) => a.id !== id);
};

// ---- Artículos
const agregar = async () => {
    form.articulos.push(articuloVacio({
        funcionario_recoge: form.funcionario_responsable,
        fecha_recepcion: form.fecha_hora ? form.fecha_hora.slice(0, 10) : '',
    }));
    await nextTick();
    document.getElementById(`art${form.articulos.length - 1}_tipo`)?.focus();
};

const duplicar = async (indice) => {
    const origen = form.articulos[indice];
    form.articulos.splice(indice + 1, 0, articuloVacio({
        ...origen,
        id: null,
        imei_1: '',
        imei_2: '',
        numero_serie: '',
        fotos: [],
        fotos_imei: [],
        comprobantes: [],
        solicitar_publicacion: false,
        estado_publicacion: null,
        publicado_at: null,
        motivo_publicacion: null,
        clave: undefined,
    }));
    form.articulos[indice + 1].clave = crypto.getRandomValues(new Uint32Array(1))[0];
    await nextTick();
    document.getElementById(`art${indice + 1}_tipo`)?.focus();
};

const quitar = async (indice) => {
    const articulo = form.articulos[indice];
    if (form.articulos.length === 1) {
        aviso('La evidencia debe tener al menos un artículo.');

        return;
    }
    if (articulo.id && !(await confirmar({
        titulo: `¿Quitar el artículo ${indice + 1}?`,
        texto: articulo.estado_publicacion === 'PUBLICADO'
            ? 'Está publicado: dejará de mostrarse en el portal. Se elimina al guardar, con sus archivos.'
            : 'Se elimina al guardar, con sus archivos.',
        confirmar: 'Sí, quitar',
        peligro: true,
    }))) {
        return;
    }
    form.articulos.splice(indice, 1);
};

// ---- Archivos de este envío (PHP descarta en silencio los que pasan de su límite)
const nuevos = computed(() => [
    ...form.actas,
    ...form.documentos,
    ...form.articulos.flatMap((a) => [...a.fotos, ...a.fotos_imei, ...a.comprobantes]),
]);
const excedido = computed(() => nuevos.value.length > props.reglas.maxArchivos);
const pesoTotal = computed(() => nuevos.value.reduce((suma, archivo) => suma + (archivo.size ?? 0), 0));

const cantidadErrores = computed(() => Object.keys(form.errors).length);

const guardar = (accion = 'guardar') => {
    if (excedido.value) {
        aviso(`Se pueden adjuntar hasta ${props.reglas.maxArchivos} archivos por vez. Guarde y adjunte el resto editando la evidencia.`);

        return;
    }
    const limpiar = (datos) => ({
        ...datos,
        accion,
        articulos: datos.articulos.map(({ clave, estado_publicacion, publicado_at, motivo_publicacion, ...resto }) => resto),
        ...(editando.value ? { _method: 'put' } : {}),
    });
    const opciones = {
        forceFormData: true,
        preserveScroll: (pagina) => Object.keys(pagina.props.errors ?? {}).length > 0,
        onError: () => nextTick(() => document.querySelector('.is-invalid')?.focus()),
    };
    form.transform(limpiar).post(editando.value ? route('evidencias.update', props.evidencia.id) : route('evidencias.store'), opciones);
};

const titulo = computed(() => (editando.value ? `Editar evidencia${props.evidencia.codigo ? ` ${props.evidencia.codigo}` : ''}` : 'Registrar evidencia'));
</script>

<template>
    <div>
        <PageHeader :titulo="titulo" :migas="[{ etiqueta: 'Evidencias', ruta: 'evidencias.index' }, { etiqueta: editando ? 'Editar' : 'Registrar' }]" />

        <form novalidate @submit.prevent="guardar('guardar')">
            <div v-if="cantidadErrores" class="alert alert-danger" role="alert">
                <i class="ri-error-warning-line align-middle me-1" /> Revise {{ cantidadErrores === 1 ? 'el campo marcado' : `los ${cantidadErrores} campos marcados` }}.
                <span v-if="form.errors.archivos" class="d-block">{{ form.errors.archivos }}</span>
            </div>

            <!-- 1. Procedimiento -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Datos del procedimiento</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label for="fecha_hora" class="form-label">Fecha y hora <span class="text-danger">*</span></label>
                            <input id="fecha_hora" v-model="form.fecha_hora" type="datetime-local" :max="maximoFechaHora" class="form-control" :class="{ 'is-invalid': form.errors.fecha_hora }">
                            <CampoError :mensaje="form.errors.fecha_hora" />
                        </div>
                        <div class="col-md-5">
                            <label for="id_caso" class="form-label">CUD / Caso</label>
                            <SelectorCaso v-model="form.id_caso" id-campo="id_caso" :codigo="evidencia?.caso_codigo ?? ''" :error="form.errors.id_caso" />
                        </div>
                        <div class="col-md-2 col-6">
                            <label for="codigo" class="form-label">Código interno</label>
                            <input id="codigo" v-model="form.codigo" v-mayuscula type="text" maxlength="50" class="form-control" :class="{ 'is-invalid': form.errors.codigo }" placeholder="(opcional)">
                            <CampoError :mensaje="form.errors.codigo" />
                        </div>
                        <div class="col-md-2 col-6">
                            <label for="numero_acta" class="form-label">N.° de acta</label>
                            <input id="numero_acta" v-model="form.numero_acta" v-mayuscula type="text" maxlength="100" class="form-control" :class="{ 'is-invalid': form.errors.numero_acta }" placeholder="(opcional)">
                            <CampoError :mensaje="form.errors.numero_acta" />
                        </div>

                        <div class="col-md-7">
                            <label for="lugar" class="form-label">Lugar <span class="text-danger">*</span></label>
                            <input id="lugar" v-model="form.lugar" v-mayuscula type="text" maxlength="255" class="form-control" :class="{ 'is-invalid': form.errors.lugar }" placeholder="Zona, calle o referencia">
                            <CampoError :mensaje="form.errors.lugar" />
                        </div>
                        <div class="col-md-5">
                            <label for="id_division" class="form-label">Unidad / División</label>
                            <select id="id_division" v-model="form.id_division" class="form-select" :class="{ 'is-invalid': form.errors.id_division }">
                                <option :value="null">Sin indicar</option>
                                <option v-for="d in divisiones" :key="d.id" :value="d.id">{{ d.division }}</option>
                            </select>
                            <CampoError :mensaje="form.errors.id_division" />
                        </div>
                        <div class="col-12">
                            <label for="detalle_lugar" class="form-label">Detalle del lugar</label>
                            <textarea id="detalle_lugar" v-model="form.detalle_lugar" rows="2" maxlength="2000" class="form-control" :class="{ 'is-invalid': form.errors.detalle_lugar }" placeholder="(opcional) Inmueble, ambiente, circunstancias" />
                        </div>

                        <div class="col-md-8">
                            <label for="funcionario_responsable" class="form-label">Funcionario responsable del procedimiento <span class="text-danger">*</span></label>
                            <input id="funcionario_responsable" v-model="form.funcionario_responsable" v-mayuscula type="text" maxlength="200" class="form-control" :class="{ 'is-invalid': form.errors.funcionario_responsable }" placeholder="Grado y nombre completo">
                            <CampoError :mensaje="form.errors.funcionario_responsable" />
                        </div>
                        <div class="col-md-4">
                            <label for="estado" class="form-label">Estado</label>
                            <select id="estado" v-model="form.estado" class="form-select" :class="{ 'is-invalid': form.errors.estado }">
                                <option v-for="estado in reglas.estados" :key="estado" :value="estado">{{ estado }}</option>
                            </select>
                            <small v-if="form.estado === 'ANULADA'" class="text-danger d-block">Al anular, sus artículos se retiran del portal.</small>
                        </div>
                        <div class="col-12">
                            <label for="observaciones" class="form-label">Observaciones</label>
                            <textarea id="observaciones" v-model="form.observaciones" rows="2" maxlength="5000" class="form-control" :class="{ 'is-invalid': form.errors.observaciones }" />
                        </div>

                        <div class="col-md-6">
                            <span class="form-label d-block">Acta de secuestro <i class="ri-lock-line text-muted" title="Documento interno: nunca se publica" /></span>
                            <SubirArchivos v-model="form.actas" :maximo="5" :tamano-maximo-mb="reglas.maxMbDocumento" acepta="application/pdf,image/jpeg,image/png,image/webp" texto="PDF o fotografía del acta" :error="Object.entries(form.errors).find(([c]) => c.startsWith('actas'))?.[1]" />
                        </div>
                        <div class="col-md-6">
                            <span class="form-label d-block">Otros documentos de respaldo <i class="ri-lock-line text-muted" title="Documento interno: nunca se publica" /></span>
                            <SubirArchivos v-model="form.documentos" :maximo="5" :tamano-maximo-mb="reglas.maxMbDocumento" acepta="application/pdf,image/jpeg,image/png,image/webp" texto="PDF o imagen" :error="Object.entries(form.errors).find(([c]) => c.startsWith('documentos'))?.[1]" />
                        </div>
                        <div v-if="generales.length || archivos.ocultos" class="col-12">
                            <span class="form-label d-block">Documentos guardados</span>
                            <GaleriaArchivos :archivos="generales" :tipos="reglas.tiposArchivo" eliminable vacio="" @eliminado="quitarGuardado" />
                            <small v-if="archivos.ocultos" class="text-muted d-block mt-1"><i class="ri-lock-line" /> {{ archivos.ocultos }} documento(s) interno(s) que su usuario no puede ver; se conservan.</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. Artículos -->
            <div class="card">
                <div class="card-header d-flex align-items-center">
                    <h5 class="card-title mb-0 flex-grow-1">Artículos secuestrados o recuperados <span class="badge bg-soft-primary text-primary cifra ms-1">{{ form.articulos.length }}</span></h5>
                </div>
                <div class="card-body">
                    <CampoError :mensaje="form.errors.articulos" />
                    <div class="d-grid gap-3">
                        <ArticuloEvidencia
                            v-for="(articulo, indice) in form.articulos"
                            :key="articulo.clave"
                            :articulo="articulo"
                            :indice="indice"
                            :tipos="tipos"
                            :reglas="reglas"
                            :errores="form.errors"
                            :archivos="delArticulo(articulo)"
                            :hoy="hoy"
                            @quitar="quitar(indice)"
                            @duplicar="duplicar(indice)"
                            @archivo-eliminado="quitarGuardado"
                        />
                    </div>
                    <button type="button" class="btn btn-soft-primary mt-3" @click="agregar">
                        <i class="ri-add-line align-middle me-1" /> Agregar artículo
                    </button>
                </div>
            </div>

            <!-- Guardar -->
            <div class="barra-guardar card mb-0">
                <div class="card-body d-flex flex-wrap align-items-center gap-2 py-2">
                    <small class="me-auto" :class="excedido ? 'text-danger fw-semibold' : 'text-muted'">
                        <i class="ri-attachment-2 align-middle" />
                        <span class="cifra">{{ nuevos.length }}</span> de {{ reglas.maxArchivos }} archivos por envío<template v-if="pesoTotal"> · <span class="cifra">{{ tamano(pesoTotal) }}</span></template>
                        <template v-if="excedido"> — quite algunos y adjúntelos después editando la evidencia</template>
                    </small>
                    <Link :href="editando ? route('evidencias.show', evidencia.id) : route('evidencias.index')" class="btn btn-light">Cancelar</Link>
                    <button v-if="!editando" type="button" class="btn btn-soft-primary" :disabled="form.processing || excedido" @click="guardar('guardar_nuevo')">
                        Guardar y registrar otra
                    </button>
                    <button type="submit" class="btn btn-primary" :disabled="form.processing || excedido">
                        <i class="align-middle me-1" :class="form.processing ? 'ri-loader-4-line' : 'ri-save-3-line'" />
                        {{ form.processing ? (form.progress ? `Subiendo ${form.progress.percentage} %` : 'Guardando…') : 'Guardar' }}
                    </button>
                </div>
            </div>
        </form>
    </div>
</template>

<style scoped>
/* La barra queda a la vista al bajar por un formulario largo. */
.barra-guardar {
    position: sticky;
    bottom: 0;
    z-index: 5;
    box-shadow: 0 -4px 12px rgb(0 0 0 / 0.06);
}
</style>
