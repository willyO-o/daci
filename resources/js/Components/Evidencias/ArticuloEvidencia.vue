<script setup>
import { computed, watch } from 'vue';
import CampoError from '@/Components/Ui/CampoError.vue';
import SubirArchivos from '@/Components/Ui/SubirArchivos.vue';
import GaleriaArchivos from '@/Components/Evidencias/GaleriaArchivos.vue';
import { CLASE_PUBLICACION, ETIQUETA_PUBLICACION, ICONO_GRUPO, camposDelGrupo } from '@/Utils/evidencias';
import { fechaHora } from '@/Utils/formato';

/**
 * Un artículo dentro del formulario de evidencia. Edita en el lugar el objeto `articulo` (un elemento de
 * `form.articulos`). Según el GRUPO del tipo elegido muestra sólo sus campos (celular: IMEI; equipo: serie; material:
 * cantidad y unidad); el servidor vacía los que no correspondan.
 */
const props = defineProps({
    articulo: { type: Object, required: true },
    indice: { type: Number, required: true },
    tipos: { type: Array, default: () => [] },
    reglas: { type: Object, required: true },
    /** `form.errors` completo (claves `articulos.N.campo`). */
    errores: { type: Object, default: () => ({}) },
    /** Archivos ya guardados de este artículo (al editar). */
    archivos: { type: Array, default: () => [] },
    hoy: { type: String, required: true },
});

const emit = defineEmits(['quitar', 'duplicar', 'archivo-eliminado']);

const tipo = computed(() => props.tipos.find((t) => String(t.id) === String(props.articulo.tipo_articulo_id)) ?? null);
const grupo = computed(() => tipo.value?.grupo ?? null);
const campos = computed(() => camposDelGrupo(props.reglas, grupo.value));
const tiene = (campo) => campos.value.includes(campo);
const descripcionObligatoria = computed(() => props.reglas.descripcionObligatoria.includes(grupo.value));

// Al cambiar a un tipo de otro grupo se vacían los campos que dejan de corresponder: si no, un IMEI escrito antes
// viajaría oculto (y su error aparecería en un campo que no se ve).
const CAMPOS_DE_GRUPO = ['marca', 'modelo', 'color', 'imei_1', 'imei_2', 'numero_serie', 'cantidad', 'unidad_medida'];
watch(grupo, (nuevo, anterior) => {
    if (!anterior || nuevo === anterior) {
        return;
    }
    const propios = camposDelGrupo(props.reglas, nuevo);
    CAMPOS_DE_GRUPO.filter((campo) => !propios.includes(campo)).forEach((campo) => {
        props.articulo[campo] = campo === 'cantidad' ? 1 : '';
    });
    if (!propios.includes('imei_1')) {
        props.articulo.fotos_imei = [];
    }
});

const id = (campo) => `art${props.indice}_${campo}`;
const error = (campo) => props.errores[`articulos.${props.indice}.${campo}`];
const errorArchivos = (campo) => Object.entries(props.errores).find(([clave]) => clave.startsWith(`articulos.${props.indice}.${campo}`))?.[1];

/** Dígito verificador (Luhn) de un IMEI de 15 dígitos: sólo avisa, no impide guardar. */
const estadoImei = (valor) => {
    const digitos = String(valor ?? '').replace(/\D/g, '');
    if (!digitos) {
        return null;
    }
    if (digitos.length < 14 || digitos.length > 16) {
        return { clase: 'text-danger', texto: `${digitos.length} dígitos (debe tener 15)` };
    }
    if (digitos.length !== 15) {
        return { clase: 'text-muted', texto: `${digitos.length} dígitos` };
    }
    const suma = [...digitos].reverse().reduce((total, d, i) => {
        let n = Number(d);
        if (i % 2 === 1) {
            n *= 2;
            if (n > 9) {
                n -= 9;
            }
        }

        return total + n;
    }, 0);

    return suma % 10 === 0
        ? { clase: 'text-success', texto: 'IMEI válido' }
        : { clase: 'text-warning', texto: 'Revise: el dígito verificador no coincide' };
};

const publicado = computed(() => props.articulo.estado_publicacion === 'PUBLICADO');
const estadoPublicacion = computed(() => props.articulo.estado_publicacion ?? 'NO_PUBLICAR');
</script>

<template>
    <section class="articulo-evidencia border rounded p-3" :aria-labelledby="id('titulo')">
        <header class="d-flex align-items-center gap-2 mb-3">
            <span class="avatar-xs flex-shrink-0">
                <span class="avatar-title rounded bg-soft-primary text-primary fs-16"><i :class="ICONO_GRUPO[grupo] ?? 'mdi mdi-cube-outline'" /></span>
            </span>
            <h6 :id="id('titulo')" class="mb-0 flex-grow-1">
                Artículo {{ indice + 1 }}<span v-if="tipo" class="text-muted fw-normal"> · {{ tipo.nombre }}</span>
            </h6>
            <span v-if="articulo.id" class="badge" :class="CLASE_PUBLICACION[estadoPublicacion]">{{ ETIQUETA_PUBLICACION[estadoPublicacion] }}</span>
            <button type="button" class="btn btn-sm btn-soft-secondary" title="Duplicar (sin IMEI, serie ni archivos)" @click="emit('duplicar')">
                <i class="ri-file-copy-line align-bottom" /><span class="d-none d-md-inline ms-1">Duplicar</span>
            </button>
            <button type="button" class="btn btn-sm btn-soft-danger" :aria-label="`Quitar el artículo ${indice + 1}`" @click="emit('quitar')">
                <i class="ri-delete-bin-line align-bottom" />
            </button>
        </header>

        <div class="row g-3">
            <div class="col-md-4">
                <label :for="id('tipo')" class="form-label">Tipo de artículo <span class="text-danger">*</span></label>
                <select :id="id('tipo')" v-model="articulo.tipo_articulo_id" class="form-select" :class="{ 'is-invalid': error('tipo_articulo_id') }">
                    <option :value="null" disabled>Seleccione…</option>
                    <option v-for="t in tipos" :key="t.id" :value="t.id">{{ t.nombre }}{{ t.estado === 'INACTIVO' ? ' (inactivo)' : '' }}</option>
                </select>
                <CampoError :mensaje="error('tipo_articulo_id')" />
            </div>

            <template v-if="grupo">
                <div v-if="tiene('marca')" class="col-md-3 col-6">
                    <label :for="id('marca')" class="form-label">Marca</label>
                    <input :id="id('marca')" v-model="articulo.marca" v-mayuscula type="text" maxlength="100" class="form-control" :class="{ 'is-invalid': error('marca') }" placeholder="Ej: SAMSUNG">
                    <CampoError :mensaje="error('marca')" />
                </div>
                <div v-if="tiene('modelo')" class="col-md-3 col-6">
                    <label :for="id('modelo')" class="form-label">Modelo</label>
                    <input :id="id('modelo')" v-model="articulo.modelo" v-mayuscula type="text" maxlength="100" class="form-control" :class="{ 'is-invalid': error('modelo') }" placeholder="Ej: GALAXY A12">
                    <CampoError :mensaje="error('modelo')" />
                </div>
                <div v-if="tiene('color')" class="col-md-2 col-6">
                    <label :for="id('color')" class="form-label">Color</label>
                    <input :id="id('color')" v-model="articulo.color" v-mayuscula type="text" maxlength="50" class="form-control" :class="{ 'is-invalid': error('color') }">
                    <CampoError :mensaje="error('color')" />
                </div>

                <template v-if="tiene('imei_1')">
                    <div v-for="campo in ['imei_1', 'imei_2']" :key="campo" class="col-md-4">
                        <label :for="id(campo)" class="form-label">{{ campo === 'imei_1' ? 'IMEI 1' : 'IMEI 2' }}</label>
                        <input
                            :id="id(campo)"
                            v-model="articulo[campo]"
                            type="text"
                            inputmode="numeric"
                            maxlength="20"
                            autocomplete="off"
                            class="form-control cifra"
                            :class="{ 'is-invalid': error(campo) }"
                            placeholder="15 dígitos (marcar *#06#)"
                            :aria-describedby="`${id(campo)}_ayuda`"
                        >
                        <CampoError :mensaje="error(campo)" />
                        <small v-if="!error(campo) && estadoImei(articulo[campo])" :id="`${id(campo)}_ayuda`" class="d-block" :class="estadoImei(articulo[campo]).clase">
                            {{ estadoImei(articulo[campo]).texto }}
                        </small>
                    </div>
                </template>
                <div v-if="tiene('numero_serie')" class="col-md-4">
                    <label :for="id('serie')" class="form-label">Número de serie</label>
                    <input :id="id('serie')" v-model="articulo.numero_serie" v-mayuscula type="text" maxlength="100" autocomplete="off" class="form-control cifra" :class="{ 'is-invalid': error('numero_serie') }">
                    <CampoError :mensaje="error('numero_serie')" />
                </div>
                <div v-if="tiene('cantidad')" class="col-md-2 col-6">
                    <label :for="id('cantidad')" class="form-label">Cantidad</label>
                    <input :id="id('cantidad')" v-model="articulo.cantidad" type="number" min="0.01" step="0.01" class="form-control cifra" :class="{ 'is-invalid': error('cantidad') }">
                    <CampoError :mensaje="error('cantidad')" />
                </div>
                <div v-if="tiene('unidad_medida')" class="col-md-2 col-6">
                    <label :for="id('unidad')" class="form-label">Unidad</label>
                    <input :id="id('unidad')" v-model="articulo.unidad_medida" v-mayuscula :list="id('unidades')" maxlength="30" class="form-control" :class="{ 'is-invalid': error('unidad_medida') }" placeholder="UNIDAD">
                    <datalist :id="id('unidades')"><option v-for="u in reglas.unidades" :key="u" :value="u" /></datalist>
                    <CampoError :mensaje="error('unidad_medida')" />
                </div>

                <div class="col-12">
                    <label :for="id('descripcion')" class="form-label">Descripción <span v-if="descripcionObligatoria" class="text-danger">*</span></label>
                    <input :id="id('descripcion')" v-model="articulo.descripcion" v-mayuscula type="text" maxlength="500" class="form-control" :class="{ 'is-invalid': error('descripcion') }" :placeholder="descripcionObligatoria ? 'Qué es y sus características' : '(opcional) Características que lo identifiquen'">
                    <CampoError :mensaje="error('descripcion')" />
                </div>

                <div class="col-md-3 col-6">
                    <label :for="id('estado_fisico')" class="form-label">Estado físico</label>
                    <select :id="id('estado_fisico')" v-model="articulo.estado_fisico" class="form-select" :class="{ 'is-invalid': error('estado_fisico') }">
                        <option :value="null">Sin indicar</option>
                        <option v-for="e in reglas.estadosFisicos" :key="e" :value="e">{{ e }}</option>
                    </select>
                </div>
                <div class="col-md-5">
                    <label :for="id('recoge')" class="form-label">Funcionario que lo recogió</label>
                    <input :id="id('recoge')" v-model="articulo.funcionario_recoge" v-mayuscula type="text" maxlength="200" class="form-control" :class="{ 'is-invalid': error('funcionario_recoge') }" placeholder="Grado y nombre">
                    <CampoError :mensaje="error('funcionario_recoge')" />
                </div>
                <div class="col-md-4 col-6">
                    <label :for="id('recepcion')" class="form-label">Fecha de recepción</label>
                    <input :id="id('recepcion')" v-model="articulo.fecha_recepcion" type="date" :max="hoy" class="form-control" :class="{ 'is-invalid': error('fecha_recepcion') }">
                    <CampoError :mensaje="error('fecha_recepcion')" />
                </div>
                <div class="col-12">
                    <label :for="id('observaciones')" class="form-label">Observaciones</label>
                    <textarea :id="id('observaciones')" v-model="articulo.observaciones" rows="2" maxlength="2000" class="form-control" :class="{ 'is-invalid': error('observaciones') }" />
                </div>

                <div class="col-md-6">
                    <span class="form-label d-block">Fotografías del artículo</span>
                    <SubirArchivos v-model="articulo.fotos" :maximo="10" :tamano-maximo-mb="reglas.maxMbFoto" acepta="image/jpeg,image/png,image/webp" texto="Arrastre o elija fotografías" :error="errorArchivos('fotos')" />
                </div>
                <div v-if="tiene('imei_1')" class="col-md-3">
                    <span class="form-label d-block">Foto del IMEI</span>
                    <SubirArchivos v-model="articulo.fotos_imei" :maximo="2" :tamano-maximo-mb="reglas.maxMbFoto" acepta="image/jpeg,image/png,image/webp" texto="Etiqueta o pantalla" :error="errorArchivos('fotos_imei')" />
                </div>
                <div :class="tiene('imei_1') ? 'col-md-3' : 'col-md-6'">
                    <span class="form-label d-block">Comprobantes <i class="ri-lock-line text-muted" title="Documento interno: nunca se publica" /></span>
                    <SubirArchivos v-model="articulo.comprobantes" :maximo="5" :tamano-maximo-mb="reglas.maxMbDocumento" acepta="application/pdf,image/jpeg,image/png,image/webp" texto="PDF o imagen" :error="errorArchivos('comprobantes')" />
                </div>
                <div v-if="archivos.length" class="col-12">
                    <span class="form-label d-block">Archivos guardados</span>
                    <GaleriaArchivos :archivos="archivos" :tipos="reglas.tiposArchivo" eliminable @eliminado="(idArchivo) => emit('archivo-eliminado', idArchivo)" />
                </div>

                <div class="col-12">
                    <div class="bg-light rounded px-3 py-2">
                        <div v-if="publicado" class="small">
                            <i class="ri-earth-line text-success align-middle me-1" />
                            Publicado en el portal<template v-if="articulo.publicado_at"> el <span class="cifra">{{ fechaHora(articulo.publicado_at) }}</span></template>.
                            Si cambia el tipo, la descripción, la marca, el modelo, el color, un IMEI o la serie, volverá a revisión. Para retirarlo, use «Publicación».
                        </div>
                        <div v-else class="form-check mb-0">
                            <input :id="id('publicar')" v-model="articulo.solicitar_publicacion" class="form-check-input" type="checkbox">
                            <label class="form-check-label" :for="id('publicar')">
                                Solicitar publicación en el portal ciudadano
                                <small class="d-block text-muted">Queda pendiente hasta que un usuario autorizado lo revise. Nada se publica solo.</small>
                            </label>
                        </div>
                        <small v-if="articulo.motivo_publicacion" class="d-block text-muted mt-1">Última observación: {{ articulo.motivo_publicacion }}</small>
                    </div>
                </div>
            </template>
            <div v-else class="col-md-8 d-flex align-items-end">
                <p class="text-muted small mb-2">Elija el tipo para ver los campos que corresponden.</p>
            </div>
        </div>
    </section>
</template>

<style scoped>
.articulo-evidencia {
    background: var(--vz-card-bg);
}
</style>
