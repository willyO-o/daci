<script setup>
import { computed, ref, watch } from 'vue';
import BuscadorRemoto from '@/Components/Ui/BuscadorRemoto.vue';
import CampoError from '@/Components/Ui/CampoError.vue';
import SelectorCatalogo from '@/Components/Ui/SelectorCatalogo.vue';
import ModalPersonaRapida from '@/Components/Personas/ModalPersonaRapida.vue';
import { useFormularioJson } from '@/Composables/useFormularioJson';
import { useNotificacion } from '@/Composables/useNotificacion';
import { usePermisos } from '@/Composables/usePermisos';
import { fechaInput, hoyIso } from '@/Utils/formato';

/**
 * Formulario de mandamiento (el `_form.blade.php` del sistema anterior). Lo usan el modal del listado y la página
 * «Registrar Mandamiento». Emite `guardado` con la tarjeta que devuelve el servidor.
 */
const props = defineProps({
    /** Mandamiento a editar (tarjeta del listado) o `null` para registrar. */
    mandamiento: { type: Object, default: null },
    estados: { type: Array, default: () => [] },
    juzgados: { type: Array, required: true },
    delitos: { type: Array, required: true },
    tiposMandamiento: { type: Array, required: true },
    idFormulario: { type: String, default: 'formMandamiento' },
});

const emit = defineEmits(['guardado']);

const { puede } = usePermisos();
const { exito, errorDeRespuesta, error } = useNotificacion();

const listaJuzgados = ref([...props.juzgados]);
const listaDelitos = ref([...props.delitos]);
const listaTipos = ref([...props.tiposMandamiento]);
const textoPersona = ref('');
const modalPersona = ref(false);

const VACIO = {
    hoja_ruta: '',
    tipo_documento: 'FOTOCOPIA',
    id_persona: null,
    id_juzgado: null,
    id_delito: null,
    id_tipo_mandamiento: null,
    asignado: '',
    telefono: '',
    actividades_realizadas: '',
    domicilio: '',
    vehiculos: '',
    imagen_mandamiento: null,
    estado: '',
    fecha_ejecucion: '',
    ejecutado_por: '',
    acta_ejecucion: null,
};

const form = useFormularioJson(VACIO);
const entradaImagen = ref(null);
const entradaActa = ref(null);

const editando = computed(() => Boolean(props.mandamiento?.id));
const hoy = hoyIso();

// El sistema anterior mostraba los datos de ejecución cuando el estado contenía «EJECUTADO».
const ejecutado = computed(() => (form.datos.estado ?? '').toUpperCase().includes('EJECUTADO'));
const tieneActa = computed(() => Boolean(props.mandamiento?.acta_ejecucion));

const cargar = () => {
    if (editando.value) {
        const m = props.mandamiento;
        form.establecer({
            ...Object.fromEntries(Object.keys(VACIO).map((campo) => [campo, m[campo] ?? VACIO[campo]])),
            fecha_ejecucion: fechaInput(m.fecha_ejecucion),
            imagen_mandamiento: null,
            acta_ejecucion: null,
            tipo_documento: m.tipo_documento || 'FOTOCOPIA',
        });
        textoPersona.value = m.nombre_completo ? `${m.nombre_completo}- Ci:${m.ci ?? ''}` : '';
    } else {
        form.reiniciar();
        textoPersona.value = '';
    }
    if (entradaImagen.value) {
        entradaImagen.value.value = '';
    }
    if (entradaActa.value) {
        entradaActa.value.value = '';
    }
};

watch(() => props.mandamiento, cargar, { immediate: true });

const personaCreada = (persona) => {
    form.datos.id_persona = persona.id;
    textoPersona.value = `${persona.nombres} ${persona.apellidos ?? ''} - ${persona.ci ?? ''}`;
};

const elegirArchivo = (campo, evento) => {
    form.datos[campo] = evento.target.files?.[0] ?? null;
};

const guardar = async () => {
    // Con estado EJECUTADO el sistema anterior exigía fecha, ejecutor y acta (salvo que ya hubiera una).
    if (ejecutado.value) {
        const faltantes = {};
        if (!form.datos.fecha_ejecucion) {
            faltantes.fecha_ejecucion = 'Indique la fecha de ejecución.';
        }
        if (!form.datos.ejecutado_por) {
            faltantes.ejecutado_por = 'Indique quién ejecutó el mandamiento.';
        }
        if (!form.datos.acta_ejecucion && !tieneActa.value) {
            faltantes.acta_ejecucion = 'Suba el acta de ejecución.';
        }
        if (Object.keys(faltantes).length) {
            form.errores.value = faltantes;

            return;
        }
    }

    try {
        const { data } = editando.value
            ? await form.enviar(route('mandamientos.update', props.mandamiento.id), 'put')
            : await form.enviar(route('mandamientos.store'));
        exito(data.success, editando.value ? 'Mandamiento Actualizado' : 'Mandamiento Registrado');
        emit('guardado', data.datos);
        if (!editando.value) {
            cargar();
        }
    } catch (err) {
        const estado = err.response?.status;
        const mensajes = {
            0: 'No se pudo conectar con el servidor. Verifique su conexión e intente nuevamente.',
            413: 'Los archivos adjuntos son demasiado pesados. Reduzca su tamaño e intente nuevamente.',
        };
        if (estado === 422) {
            return;
        }
        if (mensajes[estado ?? 0]) {
            error(mensajes[estado ?? 0]);
        } else {
            errorDeRespuesta(err);
        }
    }
};

defineExpose({ procesando: form.procesando, guardar });
</script>

<template>
    <form :id="idFormulario" novalidate @submit.prevent="guardar">
        <div class="row g-3">
            <div class="col-md-6">
                <label for="hoja_ruta" class="form-label">Hoja de Ruta</label>
                <input id="hoja_ruta" v-model="form.datos.hoja_ruta" v-mayuscula type="text" class="form-control" :class="{ 'is-invalid': form.errores.value.hoja_ruta }" placeholder="Ingrese Nro. de hoja de ruta">
                <CampoError :mensaje="form.errores.value.hoja_ruta" />
            </div>
            <div class="col-md-6">
                <span class="form-label d-block">Tipo</span>
                <div class="form-check form-check-inline mt-2">
                    <input id="tipoOriginal" v-model="form.datos.tipo_documento" class="form-check-input" type="radio" value="ORIGINAL">
                    <label class="form-check-label" for="tipoOriginal">ORIGINAL</label>
                </div>
                <div class="form-check form-check-inline">
                    <input id="tipoFotocopia" v-model="form.datos.tipo_documento" class="form-check-input" type="radio" value="FOTOCOPIA">
                    <label class="form-check-label" for="tipoFotocopia">FOTOCOPIA</label>
                </div>
            </div>

            <div class="col-lg-6">
                <label for="id_persona" class="form-label d-flex align-items-center gap-2">
                    Persona <span class="text-danger">*</span>
                    <button v-if="puede('personas.crear')" type="button" class="btn btn-sm btn-primary py-0" @click="modalPersona = true">+ Añadir</button>
                </label>
                <BuscadorRemoto
                    v-model="form.datos.id_persona"
                    id-campo="id_persona"
                    :url="route('personas.search')"
                    :texto-inicial="textoPersona"
                    placeholder="Seleccione una persona"
                    :mapear="(p) => ({ id: p.id, texto: `${p.nombres} ${p.apellidos ?? ''} - CI: ${p.ci ?? ''}` })"
                    :invalido="Boolean(form.errores.value.id_persona)"
                />
                <CampoError :mensaje="form.errores.value.id_persona" />
            </div>
            <div class="col-lg-6">
                <label for="id_juzgado" class="form-label">Juzgado <span class="text-danger">*</span></label>
                <SelectorCatalogo
                    v-model="form.datos.id_juzgado"
                    id-campo="id_juzgado"
                    :opciones="listaJuzgados"
                    campo-texto="nombre_juzgado"
                    placeholder="Seleccione un juzgado"
                    :url-nuevo="route('juzgados.store')"
                    etiqueta-entidad="juzgado"
                    :invalido="Boolean(form.errores.value.id_juzgado)"
                    @creado="(nuevo) => listaJuzgados.push(nuevo)"
                />
                <CampoError :mensaje="form.errores.value.id_juzgado" />
            </div>
            <div class="col-lg-6">
                <label for="id_delito" class="form-label">Delito <span class="text-danger">*</span></label>
                <SelectorCatalogo
                    v-model="form.datos.id_delito"
                    id-campo="id_delito"
                    :opciones="listaDelitos"
                    campo-texto="nombre_delito"
                    placeholder="Seleccione un delito"
                    :url-nuevo="route('delitos.store')"
                    etiqueta-entidad="delito"
                    :invalido="Boolean(form.errores.value.id_delito)"
                    @creado="(nuevo) => listaDelitos.push(nuevo)"
                />
                <CampoError :mensaje="form.errores.value.id_delito" />
            </div>
            <div class="col-lg-6">
                <label for="id_tipo_mandamiento" class="form-label">Tipo Mandamiento <span class="text-danger">*</span></label>
                <SelectorCatalogo
                    v-model="form.datos.id_tipo_mandamiento"
                    id-campo="id_tipo_mandamiento"
                    :opciones="listaTipos"
                    campo-texto="tipo_mandamiento"
                    placeholder="Seleccione un tipo de mandamiento"
                    :url-nuevo="route('tipos-mandamientos.store')"
                    etiqueta-entidad="tipo de mandamiento"
                    :invalido="Boolean(form.errores.value.id_tipo_mandamiento)"
                    @creado="(nuevo) => listaTipos.push(nuevo)"
                />
                <CampoError :mensaje="form.errores.value.id_tipo_mandamiento" />
            </div>
            <div class="col-lg-6">
                <label for="asignado" class="form-label">Asignado A</label>
                <input id="asignado" v-model="form.datos.asignado" v-mayuscula type="text" class="form-control" :class="{ 'is-invalid': form.errores.value.asignado }" placeholder="persona a cargo">
                <CampoError :mensaje="form.errores.value.asignado" />
            </div>
            <div class="col-lg-6">
                <label for="telefonoMandamiento" class="form-label">Teléfono</label>
                <input id="telefonoMandamiento" v-model="form.datos.telefono" type="text" class="form-control" :class="{ 'is-invalid': form.errores.value.telefono }" placeholder="Ingrese teléfono">
                <CampoError :mensaje="form.errores.value.telefono" />
            </div>
            <div class="col-12">
                <label for="actividades_realizadas" class="form-label">Actividades realizadas</label>
                <textarea id="actividades_realizadas" v-model="form.datos.actividades_realizadas" class="form-control" rows="2" />
            </div>
            <div class="col-lg-6">
                <label for="domicilioMandamiento" class="form-label">Domicilio</label>
                <textarea id="domicilioMandamiento" v-model="form.datos.domicilio" class="form-control" rows="2" />
            </div>
            <div class="col-lg-6">
                <label for="vehiculos" class="form-label">Vehículos</label>
                <textarea id="vehiculos" v-model="form.datos.vehiculos" class="form-control" rows="2" />
            </div>
            <div class="col-12">
                <label for="imagen_mandamiento" class="form-label">Adjuntar Foto/PDF del Mandamiento</label>
                <input
                    id="imagen_mandamiento"
                    ref="entradaImagen"
                    type="file"
                    class="form-control"
                    :class="{ 'is-invalid': form.errores.value.imagen_mandamiento }"
                    accept=".jpg,.png,.jpeg,.webp,.pdf"
                    @change="elegirArchivo('imagen_mandamiento', $event)"
                >
                <small v-if="mandamiento?.archivo_mandamiento" class="text-muted">Ya tiene un archivo adjunto; si elige otro, lo reemplaza.</small>
                <CampoError :mensaje="form.errores.value.imagen_mandamiento" />
            </div>
        </div>

        <fieldset class="border rounded p-3 mt-3">
            <div class="row g-3">
                <div class="col-lg-6">
                    <label for="estado" class="form-label">Estado <span class="text-danger">*</span></label>
                    <input id="estado" v-model="form.datos.estado" v-mayuscula list="estadosSugeridos" class="form-control" :class="{ 'is-invalid': form.errores.value.estado }" placeholder="Ingrese estado">
                    <datalist id="estadosSugeridos">
                        <option v-for="estado in estados" :key="estado" :value="estado" />
                    </datalist>
                    <CampoError :mensaje="form.errores.value.estado" />
                </div>
                <template v-if="ejecutado">
                    <div class="col-lg-6">
                        <label for="fecha_ejecucion" class="form-label">Fecha Ejecución</label>
                        <input id="fecha_ejecucion" v-model="form.datos.fecha_ejecucion" type="date" class="form-control" :class="{ 'is-invalid': form.errores.value.fecha_ejecucion }" :max="hoy">
                        <CampoError :mensaje="form.errores.value.fecha_ejecucion" />
                    </div>
                    <div class="col-lg-6">
                        <label for="ejecutado_por" class="form-label">Ejecutado Por <span class="text-danger">*</span></label>
                        <input id="ejecutado_por" v-model="form.datos.ejecutado_por" v-mayuscula type="text" class="form-control" :class="{ 'is-invalid': form.errores.value.ejecutado_por }" placeholder="Ingrese nombre de quien ejecutó el mandamiento">
                        <CampoError :mensaje="form.errores.value.ejecutado_por" />
                    </div>
                    <div class="col-lg-6">
                        <label for="acta_ejecucion" class="form-label">Subir Acta</label>
                        <input
                            id="acta_ejecucion"
                            ref="entradaActa"
                            type="file"
                            class="form-control"
                            :class="{ 'is-invalid': form.errores.value.acta_ejecucion }"
                            accept="image/*,.pdf"
                            @change="elegirArchivo('acta_ejecucion', $event)"
                        >
                        <small v-if="tieneActa" class="text-muted">Ya tiene un acta cargada.</small>
                        <CampoError :mensaje="form.errores.value.acta_ejecucion" />
                    </div>
                </template>
            </div>
        </fieldset>

        <ModalPersonaRapida v-model="modalPersona" @creada="personaCreada" />
    </form>
</template>
