<script setup>
import { computed, onBeforeUnmount, ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import PageHeader from '@/Components/Layout/PageHeader.vue';
import BuscadorRemoto from '@/Components/Ui/BuscadorRemoto.vue';
import CampoError from '@/Components/Ui/CampoError.vue';
import SelectorCatalogo from '@/Components/Ui/SelectorCatalogo.vue';
import { ESTADOS_CIVILES, fechaInput, hoyIso, urlArchivo } from '@/Utils/formato';

/**
 * Registrar / editar un registro criminal (`registro-criminal/formulario.blade.php`). Al registrar se busca la
 * persona (o se crea con los datos escritos); al editar, los datos personales quedan de sólo lectura porque se
 * corrigen en Personas (el sistema anterior tampoco los guardaba desde aquí).
 */
const props = defineProps({
    registro: { type: Object, default: null },
    paises: { type: Array, required: true },
    divisiones: { type: Array, required: true },
});

const editando = computed(() => Boolean(props.registro?.id));
const persona = props.registro?.persona ?? {};
const listaDivisiones = ref([...props.divisiones]);
const hoy = hoyIso();

const valoresIniciales = () => ({
    id_persona: props.registro?.id_persona ?? null,
    fecha_registro: props.registro?.fecha_registro ?? hoy,
    ci: persona.ci ?? '',
    complemento: persona.complemento ?? '',
    nombres: persona.nombres ?? '',
    apellidos: persona.apellidos ?? '',
    nombre_supuesto: props.registro?.nombre_supuesto ?? '',
    alias: props.registro?.alias ?? '',
    telefono: props.registro?.telefono ?? '',
    cud: props.registro?.cud ?? '',
    especialidad: props.registro?.especialidad ?? '',
    id_pais: persona.id_pais ?? '',
    lugar_nacimiento: persona.lugar_nacimiento ?? '',
    fecha_nacimiento: fechaInput(persona.fecha_nacimiento),
    edad_aproximada: props.registro?.edad_aproximada ?? '',
    genero: persona.genero ?? '',
    estatura: props.registro?.estatura ?? '',
    peso: props.registro?.peso ?? '',
    caracteristicas_particulares: props.registro?.caracteristicas_particulares ?? '',
    estado_civil: persona.estado_civil ?? '',
    nombre_conyuge: props.registro?.nombre_conyuge ?? '',
    hijos: props.registro?.hijos ?? '',
    domicilio: props.registro?.domicilio ?? '',
    id_division: props.registro?.id_division ?? null,
    ocupacion: persona.ocupacion ?? '',
    rasgos: props.registro?.rasgos ?? '',
    modus_operandi: props.registro?.modus_operandi ?? '',
    zonas_opera: props.registro?.zonas_opera ?? '',
    observaciones: props.registro?.observaciones ?? '',
    foto_frente: null,
    foto_perfil: null,
});

const form = useForm(valoresIniciales());

// Vista previa de las fotos
const vistaFrente = ref(urlArchivo(props.registro?.foto_frente));
const vistaPerfil = ref(urlArchivo(props.registro?.foto_perfil));
const entradaFrente = ref(null);
const entradaPerfil = ref(null);
const urlsCreadas = [];

const elegirFoto = (campo, evento) => {
    const archivo = evento.target.files?.[0] ?? null;
    form[campo] = archivo;
    const url = archivo ? URL.createObjectURL(archivo) : null;
    if (url) {
        urlsCreadas.push(url);
    }
    if (campo === 'foto_frente') {
        vistaFrente.value = url ?? urlArchivo(props.registro?.foto_frente);
    } else {
        vistaPerfil.value = url ?? urlArchivo(props.registro?.foto_perfil);
    }
};

onBeforeUnmount(() => urlsCreadas.forEach((url) => URL.revokeObjectURL(url)));

// Al elegir una persona existente se completan sus datos (como el autocompletado del sistema anterior).
const personaElegida = (p) => {
    if (!p) {
        form.id_persona = null;

        return;
    }
    Object.assign(form, {
        ci: p.ci ?? '',
        complemento: p.complemento ?? '',
        nombres: p.nombres ?? '',
        apellidos: p.apellidos ?? '',
        domicilio: p.domicilio ?? form.domicilio,
        lugar_nacimiento: p.lugar_nacimiento ?? '',
        nombre_conyuge: p.nombre_conyuge ?? form.nombre_conyuge,
        ocupacion: p.ocupacion ?? '',
        estado_civil: p.estado_civil ?? '',
        id_pais: p.id_pais ?? '',
        genero: p.genero ?? '',
        fecha_nacimiento: fechaInput(p.fecha_nacimiento),
    });
};

const limpiar = () => {
    form.defaults(valoresIniciales());
    form.reset();
    vistaFrente.value = null;
    vistaPerfil.value = null;
    if (entradaFrente.value) {
        entradaFrente.value.value = '';
    }
    if (entradaPerfil.value) {
        entradaPerfil.value.value = '';
    }
};

const guardar = (accion) => {
    if (editando.value) {
        form.transform((datos) => ({ ...datos, _method: 'put' })).post(route('registro-criminal.update', props.registro.id), { forceFormData: true });

        return;
    }
    form.transform((datos) => ({ ...datos, accion })).post(route('registro-criminal.store'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => accion === 'guardar_nuevo' && limpiar(),
    });
};

// Errores de campos que no muestran su mensaje al lado (largos máximos, fotos que no son imagen…).
const CAMPOS_CON_MENSAJE = ['foto_frente', 'foto_perfil', 'fecha_registro', 'nombres', 'alias', 'edad_aproximada', 'genero', 'id_division'];
const erroresSinCampo = computed(() =>
    Object.entries(form.errors)
        .filter(([campo]) => !CAMPOS_CON_MENSAJE.includes(campo))
        .map(([, mensaje]) => mensaje),
);

const titulo = computed(() => (editando.value ? `Editar Registro Nro. ${props.registro.nro_registro}` : 'Nuevo Registro Criminal'));
</script>

<template>
    <div>
        <PageHeader :titulo="titulo" :migas="[{ etiqueta: 'Registros', ruta: 'registro-criminal.index' }, { etiqueta: editando ? 'Editar' : 'Nuevo' }]" />

        <form novalidate @submit.prevent="guardar('guardar')">
            <div v-if="erroresSinCampo.length" class="alert alert-danger" role="alert">
                <p class="fw-semibold mb-1">Revise los datos antes de guardar:</p>
                <ul class="mb-0 ps-3">
                    <li v-for="mensaje in erroresSinCampo" :key="mensaje">{{ mensaje }}</li>
                </ul>
            </div>
            <div class="row">
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header"><h5 class="card-title mb-0">Fotografías</h5></div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div v-for="foto in [{ campo: 'foto_frente', etiqueta: 'Foto de frente', vista: vistaFrente }, { campo: 'foto_perfil', etiqueta: 'Foto de perfil', vista: vistaPerfil }]" :key="foto.campo" class="col-6">
                                    <label class="form-label">{{ foto.etiqueta }}</label>
                                    <label :for="foto.campo" class="d-block cursor-pointer">
                                        <span class="avatar-title bg-light rounded w-100 border" style="min-height: 150px">
                                            <img v-if="foto.vista" :src="foto.vista" :alt="foto.etiqueta" class="img-fluid rounded" style="max-height: 220px">
                                            <span v-else class="text-muted text-center"><i class="ri-image-add-line fs-1 d-block" />Seleccionar imagen</span>
                                        </span>
                                    </label>
                                    <input
                                        :id="foto.campo"
                                        :ref="(el) => (foto.campo === 'foto_frente' ? (entradaFrente = el) : (entradaPerfil = el))"
                                        type="file"
                                        class="d-none"
                                        accept="image/png, image/gif, image/jpeg, image/webp"
                                        @change="elegirFoto(foto.campo, $event)"
                                    >
                                    <CampoError :mensaje="form.errors[foto.campo]" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="!editando" class="card">
                        <div class="card-body">
                            <label class="form-label">Buscar Persona</label>
                            <BuscadorRemoto
                                v-model="form.id_persona"
                                :url="route('personas.search')"
                                placeholder="Buscar por CI-complemento, Nombre, apellido, nombres o alias"
                                :mapear="(p) => ({ id: p.id, texto: `${p.nombres} ${p.apellidos ?? ''} - ${p.ci || 'Sin CI'}` })"
                                @seleccionado="personaElegida"
                            />
                            <small class="text-muted">Si la persona no existe, complete sus datos abajo y se registrará.</small>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            <div v-if="editando" class="alert alert-info py-2 small">Los datos personales se corrigen en <strong>Personas</strong>.</div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label" for="fecha_registro">Fecha de Registro <span class="text-danger">*</span></label>
                                    <input id="fecha_registro" v-model="form.fecha_registro" type="date" class="form-control" :class="{ 'is-invalid': form.errors.fecha_registro }" :max="hoy">
                                    <CampoError :mensaje="form.errors.fecha_registro" />
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label" for="ci">Nro. C.I.:</label>
                                    <input id="ci" v-model="form.ci" maxlength="25" v-mayuscula type="text" class="form-control" :disabled="editando || Boolean(form.id_persona)" placeholder="Ingrese número de C.I.">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="complemento">Complemento</label>
                                    <input id="complemento" v-model="form.complemento" maxlength="40" v-mayuscula type="text" class="form-control" :disabled="editando || Boolean(form.id_persona)" placeholder="Ingrese complemento">
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="nombres">Nombres <span class="text-danger">*</span></label>
                                    <input id="nombres" v-model="form.nombres" maxlength="150" v-mayuscula type="text" class="form-control" :class="{ 'is-invalid': form.errors.nombres }" :disabled="editando || Boolean(form.id_persona)" placeholder="Ingrese nombre(s)">
                                    <CampoError :mensaje="form.errors.nombres" />
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="apellidos">Apellidos</label>
                                    <input id="apellidos" v-model="form.apellidos" maxlength="150" v-mayuscula type="text" class="form-control" :disabled="editando || Boolean(form.id_persona)" placeholder="Ingrese apellido(s)">
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="nombre_supuesto">Nombre Supuesto</label>
                                    <input id="nombre_supuesto" v-model="form.nombre_supuesto" maxlength="250" v-mayuscula type="text" class="form-control" placeholder="(opcional)">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="alias">Alias</label>
                                    <input id="alias" v-model="form.alias" v-mayuscula type="text" class="form-control" :class="{ 'is-invalid': form.errors.alias }" maxlength="20" placeholder="(opcional)">
                                    <CampoError :mensaje="form.errors.alias" />
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="telefono">Teléfono</label>
                                    <input id="telefono" v-model="form.telefono" maxlength="25" type="text" class="form-control" placeholder="(opcional)">
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="cud">CUD</label>
                                    <input id="cud" v-model="form.cud" maxlength="255" v-mayuscula type="text" class="form-control" placeholder="(opcional)">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex gap-2 mb-4">
                        <button type="submit" class="btn btn-primary" :disabled="form.processing">
                            <span v-if="form.processing" class="spinner-border spinner-border-sm me-1" role="status" />
                            <i v-else class="ri-save-3-line align-middle me-1" /> Guardar
                        </button>
                        <button v-if="!editando" type="button" class="btn btn-info" :disabled="form.processing" @click="guardar('guardar_nuevo')">
                            <i class="ri-save-3-line align-middle me-1" /> Guardar y Registrar Nuevo
                        </button>
                        <Link :href="route('registro-criminal.index')" class="btn btn-secondary">Cancelar</Link>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label" for="especialidad">Especialidad</label>
                                    <input id="especialidad" v-model="form.especialidad" maxlength="250" v-mayuscula type="text" class="form-control">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="id_pais">Nacionalidad</label>
                                    <select id="id_pais" v-model="form.id_pais" class="form-select" :disabled="editando || Boolean(form.id_persona)">
                                        <option value="">Seleccione un país</option>
                                        <option v-for="pais in paises" :key="pais.id" :value="pais.id">{{ pais.gentilicio?.toUpperCase() }}</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="lugar_nacimiento">Lugar de Nacimiento</label>
                                    <input id="lugar_nacimiento" v-model="form.lugar_nacimiento" maxlength="250" v-mayuscula type="text" class="form-control" :disabled="editando || Boolean(form.id_persona)" placeholder="(opcional)">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="fecha_nacimiento">Fecha de Nacimiento</label>
                                    <input id="fecha_nacimiento" v-model="form.fecha_nacimiento" type="date" class="form-control" :disabled="editando || Boolean(form.id_persona)" :max="hoy">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="edad_aproximada">Edad aproximada</label>
                                    <input id="edad_aproximada" v-model="form.edad_aproximada" v-mayuscula type="text" class="form-control" :class="{ 'is-invalid': form.errors.edad_aproximada }" maxlength="20" placeholder="(opcional)">
                                    <CampoError :mensaje="form.errors.edad_aproximada" />
                                </div>
                                <div class="col-12">
                                    <span class="form-label d-block">Género <span class="text-danger">*</span></span>
                                    <div class="form-check form-check-inline">
                                        <input id="genero1" v-model="form.genero" class="form-check-input" type="radio" value="MASCULINO" :disabled="editando">
                                        <label class="form-check-label" for="genero1">Masculino</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input id="genero2" v-model="form.genero" class="form-check-input" type="radio" value="FEMENINO" :disabled="editando">
                                        <label class="form-check-label" for="genero2">Femenino</label>
                                    </div>
                                    <CampoError :mensaje="form.errors.genero" />
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="estatura">Estatura</label>
                                    <input id="estatura" v-model="form.estatura" maxlength="255" v-mayuscula type="text" class="form-control" placeholder="(opcional)">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="peso">Peso</label>
                                    <input id="peso" v-model="form.peso" maxlength="255" v-mayuscula type="text" class="form-control" placeholder="(opcional)">
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="caracteristicas_particulares">Características particulares</label>
                                    <textarea id="caracteristicas_particulares" v-model="form.caracteristicas_particulares" v-mayuscula class="form-control" rows="2" />
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="estado_civil">Estado civil</label>
                                    <select id="estado_civil" v-model="form.estado_civil" class="form-select" :disabled="editando || Boolean(form.id_persona)">
                                        <option value="">Seleccionar estado civil</option>
                                        <option v-for="(etiqueta, valor) in ESTADOS_CIVILES" :key="valor" :value="valor">{{ etiqueta }}</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="nombre_conyuge">Nombre del cónyuge</label>
                                    <input id="nombre_conyuge" v-model="form.nombre_conyuge" maxlength="250" v-mayuscula type="text" class="form-control" placeholder="(opcional)">
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="hijos">Hijos</label>
                                    <textarea id="hijos" v-model="form.hijos" v-mayuscula class="form-control" rows="3" />
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="domicilio">Domicilio</label>
                                    <input id="domicilio" v-model="form.domicilio" v-mayuscula type="text" class="form-control" maxlength="250">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="id_division">División <span class="text-danger">*</span></label>
                                    <SelectorCatalogo
                                        v-model="form.id_division"
                                        id-campo="id_division"
                                        :opciones="listaDivisiones"
                                        campo-texto="division"
                                        placeholder="Seleccione una división"
                                        :url-nuevo="route('divisiones.store')"
                                        etiqueta-entidad="división"
                                        :invalido="Boolean(form.errors.id_division)"
                                        @creado="(nueva) => listaDivisiones.push(nueva)"
                                    />
                                    <CampoError :mensaje="form.errors.id_division" />
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="ocupacion">Ocupación</label>
                                    <input id="ocupacion" v-model="form.ocupacion" maxlength="150" v-mayuscula type="text" class="form-control" :disabled="editando || Boolean(form.id_persona)" placeholder="(opcional)">
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="rasgos">Rasgos</label>
                                    <input id="rasgos" v-model="form.rasgos" v-mayuscula type="text" class="form-control">
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="modus_operandi">Modus Operandi</label>
                                    <textarea id="modus_operandi" v-model="form.modus_operandi" v-mayuscula class="form-control" rows="3" />
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="zonas_opera">Zonas que Operan</label>
                                    <textarea id="zonas_opera" v-model="form.zonas_opera" v-mayuscula class="form-control" rows="3" />
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="observaciones">Observaciones</label>
                                    <textarea id="observaciones" v-model="form.observaciones" v-mayuscula class="form-control" rows="3" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</template>
