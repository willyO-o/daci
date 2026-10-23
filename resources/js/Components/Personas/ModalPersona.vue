<script setup>
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import BaseModal from '@/Components/Ui/BaseModal.vue';
import CampoError from '@/Components/Ui/CampoError.vue';
import SubirArchivos from '@/Components/Ui/SubirArchivos.vue';
import { useNotificacion } from '@/Composables/useNotificacion';
import { ESTADOS_CIVILES, GENEROS, fechaInput, urlArchivo } from '@/Utils/formato';
import { http } from '@/Utils/http';

/**
 * Alta y edición de una persona (el modal «Registrar / Editar Persona» del sistema anterior).
 */
const props = defineProps({
    modelValue: { type: Boolean, default: false },
    /** Persona a editar; `null` para registrar una nueva. */
    persona: { type: Object, default: null },
    paises: { type: Array, required: true },
});

const emit = defineEmits(['update:modelValue', 'guardado', 'fotos-cambiadas']);

const { confirmar, exito, errorDeRespuesta } = useNotificacion();

const CAMPOS_VACIOS = {
    nombres: '',
    apellidos: '',
    ci: '',
    complemento: '',
    telefono: '',
    alias: '',
    fecha_nacimiento: '',
    lugar_nacimiento: '',
    id_pais: '',
    genero: '',
    estado_civil: '',
    ocupacion: '',
    grupo_sanguineo: '',
    nombre_conyuge: '',
    padre: '',
    madre: '',
    domicilio: '',
    datos_segip: '',
    responsable: '',
    url_documento: '',
    fotos: [],
};

const form = useForm({ ...CAMPOS_VACIOS });
const fotosActuales = ref([]);
const ciDuplicado = ref(null);

const abierto = computed({
    get: () => props.modelValue,
    set: (valor) => emit('update:modelValue', valor),
});

const editando = computed(() => Boolean(props.persona?.id));

// Al registrar, la nacionalidad empieza en «boliviano/a» como en el sistema anterior.
const idBolivia = computed(() => props.paises.find((pais) => pais.gentilicio?.trim().toLowerCase() === 'boliviano/a')?.id ?? '');

const cargar = () => {
    form.clearErrors();
    ciDuplicado.value = null;

    if (editando.value) {
        const datos = Object.fromEntries(Object.keys(CAMPOS_VACIOS).map((campo) => [campo, props.persona[campo] ?? '']));
        form.defaults({ ...datos, fecha_nacimiento: fechaInput(props.persona.fecha_nacimiento), fotos: [] });
        fotosActuales.value = [...(props.persona.multimedia ?? [])];
    } else {
        form.defaults({ ...CAMPOS_VACIOS, id_pais: idBolivia.value, fotos: [] });
        fotosActuales.value = [];
    }

    form.reset();
};

watch(
    () => props.modelValue,
    (valor) => valor && cargar(),
    { immediate: true },
);

let temporizadorCi = null;
watch(
    () => [form.ci, form.complemento],
    ([ci, complemento]) => {
        clearTimeout(temporizadorCi);
        ciDuplicado.value = null;
        if (!ci || ci.trim().length < 4) {
            return;
        }
        temporizadorCi = setTimeout(async () => {
            try {
                const { data } = await http.get(route('personas.check-ci'), {
                    params: { ci: ci.trim(), complemento: complemento?.trim() || undefined, excluir: props.persona?.id },
                });
                ciDuplicado.value = data.data;
            } catch {
                ciDuplicado.value = null;
            }
        }, 350);
    },
);

const guardar = (registrarOtra = false) => {
    const opciones = {
        forceFormData: true,
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            emit('guardado');
            if (registrarOtra) {
                form.defaults({ ...CAMPOS_VACIOS, id_pais: idBolivia.value, fotos: [] });
                form.reset();
                ciDuplicado.value = null;
            } else {
                abierto.value = false;
            }
        },
    };

    if (editando.value) {
        form.transform((datos) => ({ ...datos, _method: 'put' })).post(route('personas.update', props.persona.id), opciones);
    } else {
        form.transform((datos) => datos).post(route('personas.store'), opciones);
    }
};

const eliminarFoto = async (foto) => {
    if (!(await confirmar({ titulo: '¿Eliminar foto?', texto: 'Esta acción no se puede deshacer', confirmar: 'Sí, eliminar', peligro: true }))) {
        return;
    }
    try {
        await http.delete(route('multimedia.destroy', foto.id));
        fotosActuales.value = fotosActuales.value.filter((actual) => actual.id !== foto.id);
        emit('fotos-cambiadas');
        exito('Foto eliminada correctamente', 'Eliminada');
    } catch (err) {
        errorDeRespuesta(err);
    }
};
</script>

<template>
    <BaseModal v-model="abierto" :titulo="editando ? 'Editar Persona' : 'Registrar Persona'" tamano="xl" estatico>
        <form id="formPersona" novalidate @submit.prevent="guardar(false)">
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="nombres" class="form-label">Nombres <span class="text-danger">*</span></label>
                    <input id="nombres" v-model="form.nombres" v-mayuscula type="text" class="form-control" :class="{ 'is-invalid': form.errors.nombres }" placeholder="Ingrese nombres">
                    <CampoError :mensaje="form.errors.nombres" />
                </div>
                <div class="col-md-6">
                    <label for="apellidos" class="form-label">Apellidos</label>
                    <input id="apellidos" v-model="form.apellidos" v-mayuscula type="text" class="form-control" :class="{ 'is-invalid': form.errors.apellidos }" placeholder="Ingrese apellidos">
                    <CampoError :mensaje="form.errors.apellidos" />
                </div>
                <div class="col-md-4">
                    <label for="ci" class="form-label">Cédula de Identidad</label>
                    <input id="ci" v-model="form.ci" v-mayuscula type="text" class="form-control" :class="{ 'is-invalid': form.errors.ci || ciDuplicado }" maxlength="20" placeholder="Ej: 1234567">
                    <CampoError :mensaje="form.errors.ci" />
                    <div v-if="ciDuplicado && !form.errors.ci" class="invalid-feedback d-block">
                        Ya existe una persona con este CI y complemento: {{ ciDuplicado.nombres }} {{ ciDuplicado.apellidos }}
                    </div>
                </div>
                <div class="col-md-2">
                    <label for="complemento" class="form-label">Complemento</label>
                    <input id="complemento" v-model="form.complemento" v-mayuscula type="text" class="form-control" :class="{ 'is-invalid': form.errors.complemento }" maxlength="40" placeholder="Ej: A, B, etc">
                    <CampoError :mensaje="form.errors.complemento" />
                </div>
                <div class="col-md-6">
                    <label for="telefono" class="form-label">Teléfono</label>
                    <input id="telefono" v-model="form.telefono" type="tel" class="form-control" :class="{ 'is-invalid': form.errors.telefono }" maxlength="25" placeholder="Ej: +591 1234567">
                    <CampoError :mensaje="form.errors.telefono" />
                </div>
                <div class="col-md-6">
                    <label for="alias" class="form-label">Alias</label>
                    <input id="alias" v-model="form.alias" v-mayuscula type="text" class="form-control" :class="{ 'is-invalid': form.errors.alias }" maxlength="25" placeholder="Ej: El Lobo, La Rata, etc.">
                    <CampoError :mensaje="form.errors.alias" />
                </div>
                <div class="col-md-6">
                    <label for="fecha_nacimiento" class="form-label">Fecha de Nacimiento</label>
                    <input id="fecha_nacimiento" v-model="form.fecha_nacimiento" type="date" class="form-control" :class="{ 'is-invalid': form.errors.fecha_nacimiento }">
                    <CampoError :mensaje="form.errors.fecha_nacimiento" />
                </div>
                <div class="col-md-6">
                    <label for="lugar_nacimiento" class="form-label">Lugar de Nacimiento</label>
                    <input id="lugar_nacimiento" v-model="form.lugar_nacimiento" v-mayuscula type="text" class="form-control" :class="{ 'is-invalid': form.errors.lugar_nacimiento }" maxlength="250" placeholder="Ciudad o región">
                    <CampoError :mensaje="form.errors.lugar_nacimiento" />
                </div>
                <div class="col-md-6">
                    <label for="id_pais" class="form-label">Nacionalidad</label>
                    <select id="id_pais" v-model="form.id_pais" class="form-select" :class="{ 'is-invalid': form.errors.id_pais }">
                        <option value="">Seleccione una nacionalidad</option>
                        <option v-for="pais in paises" :key="pais.id" :value="pais.id">{{ pais.gentilicio }}</option>
                    </select>
                    <CampoError :mensaje="form.errors.id_pais" />
                </div>
                <div class="col-md-6">
                    <label for="genero" class="form-label">Género</label>
                    <select id="genero" v-model="form.genero" class="form-select" :class="{ 'is-invalid': form.errors.genero }">
                        <option value="">Seleccione género</option>
                        <option v-for="(etiqueta, valor) in GENEROS" :key="valor" :value="valor">{{ etiqueta }}</option>
                    </select>
                    <CampoError :mensaje="form.errors.genero" />
                </div>
                <div class="col-md-6">
                    <label for="estado_civil" class="form-label">Estado Civil</label>
                    <select id="estado_civil" v-model="form.estado_civil" class="form-select" :class="{ 'is-invalid': form.errors.estado_civil }">
                        <option value="">Seleccione estado civil</option>
                        <option v-for="(etiqueta, valor) in ESTADOS_CIVILES" :key="valor" :value="valor">{{ etiqueta }}</option>
                    </select>
                    <CampoError :mensaje="form.errors.estado_civil" />
                </div>
                <div class="col-md-6">
                    <label for="ocupacion" class="form-label">Ocupación</label>
                    <input id="ocupacion" v-model="form.ocupacion" v-mayuscula type="text" class="form-control" :class="{ 'is-invalid': form.errors.ocupacion }" maxlength="150" placeholder="Profesión u oficio">
                    <CampoError :mensaje="form.errors.ocupacion" />
                </div>
                <div class="col-md-6">
                    <label for="grupo_sanguineo" class="form-label">Grupo Sanguíneo</label>
                    <input id="grupo_sanguineo" v-model="form.grupo_sanguineo" v-mayuscula type="text" class="form-control" :class="{ 'is-invalid': form.errors.grupo_sanguineo }" maxlength="150" placeholder="Ej: A+, O-, etc">
                    <CampoError :mensaje="form.errors.grupo_sanguineo" />
                </div>
                <div class="col-md-6">
                    <label for="nombre_conyuge" class="form-label">Nombre del Cónyuge</label>
                    <input id="nombre_conyuge" v-model="form.nombre_conyuge" v-mayuscula type="text" class="form-control" :class="{ 'is-invalid': form.errors.nombre_conyuge }" maxlength="250" placeholder="Nombre completo del cónyuge">
                    <CampoError :mensaje="form.errors.nombre_conyuge" />
                </div>
                <div class="col-md-6">
                    <label for="padre" class="form-label">Nombre del Padre</label>
                    <input id="padre" v-model="form.padre" v-mayuscula type="text" class="form-control" :class="{ 'is-invalid': form.errors.padre }" maxlength="250" placeholder="Nombre completo del padre">
                    <CampoError :mensaje="form.errors.padre" />
                </div>
                <div class="col-md-6">
                    <label for="madre" class="form-label">Nombre de la Madre</label>
                    <input id="madre" v-model="form.madre" v-mayuscula type="text" class="form-control" :class="{ 'is-invalid': form.errors.madre }" maxlength="250" placeholder="Nombre completo de la madre">
                    <CampoError :mensaje="form.errors.madre" />
                </div>
                <div class="col-md-6">
                    <label for="domicilio" class="form-label">Domicilio</label>
                    <textarea id="domicilio" v-model="form.domicilio" class="form-control" :class="{ 'is-invalid': form.errors.domicilio }" rows="3" maxlength="500" placeholder="Dirección completa" />
                    <CampoError :mensaje="form.errors.domicilio" />
                </div>
                <div class="col-md-6">
                    <label for="datos_segip" class="form-label">Datos Segip</label>
                    <textarea id="datos_segip" v-model="form.datos_segip" class="form-control" :class="{ 'is-invalid': form.errors.datos_segip }" rows="3" placeholder="Datos del Segip" />
                    <CampoError :mensaje="form.errors.datos_segip" />
                </div>
                <div class="col-md-6">
                    <label for="responsable" class="form-label">Nombre del responsable</label>
                    <input id="responsable" v-model="form.responsable" v-mayuscula type="text" class="form-control" :class="{ 'is-invalid': form.errors.responsable }" maxlength="200" placeholder="Nombre completo del responsable">
                    <CampoError :mensaje="form.errors.responsable" />
                </div>
                <div class="col-md-6">
                    <label for="url_documento" class="form-label">URL del Documento</label>
                    <input id="url_documento" v-model="form.url_documento" type="url" class="form-control" :class="{ 'is-invalid': form.errors.url_documento }" maxlength="500" placeholder="URL del documento en drive, nube, etc.">
                    <CampoError :mensaje="form.errors.url_documento" />
                </div>

                <div class="col-12">
                    <label class="form-label">Fotos <small class="text-muted">(JPEG, PNG, WebP, JPG - máx 2MB por archivo)</small></label>
                    <SubirArchivos
                        v-model="form.fotos"
                        :maximo="4"
                        :tamano-maximo-mb="2"
                        acepta="image/jpeg,image/png,image/webp,image/jpg"
                        texto="Arrastra o sube hasta 4 fotos (opcional)"
                        :error="form.errors.fotos || form.errors['fotos.0']"
                    />

                    <div v-if="fotosActuales.length" class="mt-3">
                        <label class="form-label">Fotos Actuales:</label>
                        <div class="d-flex flex-wrap gap-2">
                            <div v-for="foto in fotosActuales" :key="foto.id" class="position-relative">
                                <img :src="urlArchivo(foto.ruta)" alt="Foto de la persona" class="rounded border miniatura-archivo">
                                <button type="button" class="btn btn-sm btn-danger position-absolute top-0 end-0 m-1 py-0 px-1" aria-label="Eliminar foto" @click="eliminarFoto(foto)">
                                    <i class="ri-delete-bin-fill" />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>

        <template #pie="{ cerrar }">
            <button type="button" class="btn btn-light" @click="cerrar">Cancelar</button>
            <button v-if="!editando" type="button" class="btn btn-info" :disabled="form.processing" @click="guardar(true)">
                <i class="ri-save-3-line align-middle me-1" /> Guardar y Registrar Nuevo
            </button>
            <button type="submit" form="formPersona" class="btn btn-primary" :disabled="form.processing">
                <i class="align-middle me-1" :class="form.processing ? 'ri-loader-4-line' : 'ri-save-3-line'" />
                {{ form.processing ? 'Guardando…' : 'Guardar' }}
            </button>
        </template>
    </BaseModal>
</template>
