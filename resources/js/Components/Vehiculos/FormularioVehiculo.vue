<script setup>
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import BuscadorRemoto from '@/Components/Ui/BuscadorRemoto.vue';
import CampoError from '@/Components/Ui/CampoError.vue';
import SubirArchivos from '@/Components/Ui/SubirArchivos.vue';
import { useNotificacion } from '@/Composables/useNotificacion';
import { usePermisos } from '@/Composables/usePermisos';
import { nombreCompleto, urlArchivo } from '@/Utils/formato';
import { http } from '@/Utils/http';

/**
 * Formulario de vehículo (alta y edición) con personas asociadas y fotos. Lo usan el modal del listado y la página
 * «Registrar Vehículo». El botón de guardar va fuera (`<button type="submit" :form="idFormulario">`). Tras
 * registrar, queda limpio para el siguiente.
 */
const props = defineProps({
    /** Vehículo a editar (fila del listado, con `personas` y `multimedia`); `null` para registrar. */
    vehiculo: { type: Object, default: null },
    idFormulario: { type: String, default: 'formVehiculo' },
});

const emit = defineEmits(['guardado', 'fotos-cambiadas']);

const TIPOS = ['BSISA', 'RUAT', 'SOAT', 'ANH', 'ITB'];

const { puede } = usePermisos();
const { confirmar, exito, errorDeRespuesta, aviso } = useNotificacion();

const textoPersona = (p) => `${p.nombres} ${p.apellidos ?? ''} - CI: ${p.ci ?? ''}`;

const VACIO = { placa: '', responsable: '', descripcion: '', caso_relacionado: '', personas_asociadas: [], fotosVehiculos: [] };

const fotosActuales = ref([]);
const personaElegida = ref(null);
const personaElegidaDatos = ref(null);
const tipoPersona = ref('');
const casoPersona = ref('');
// Al limpiar tras registrar, se vuelve a montar el buscador para que no quede la persona anterior escrita.
const versionBuscador = ref(0);
const form = useForm({ ...VACIO });

const editando = computed(() => Boolean(props.vehiculo?.id));

const cargar = () => {
    const v = props.vehiculo;
    fotosActuales.value = editando.value ? [...(v.multimedia ?? [])] : [];
    form.defaults(
        editando.value
            ? {
                placa: v.placa,
                responsable: v.responsable ?? '',
                descripcion: v.descripcion ?? '',
                caso_relacionado: v.caso_relacionado ?? '',
                personas_asociadas: (v.personas ?? []).map((p) => ({
                    persona_id: p.id,
                    nombre: nombreCompleto(p),
                    tipo: p.pivot?.tipo ?? '',
                    caso: p.pivot?.numero_informacion ?? '',
                })),
                fotosVehiculos: [],
            }
            : { ...VACIO, personas_asociadas: [], fotosVehiculos: [] },
    );
    form.reset();
    form.clearErrors();
    personaElegida.value = null;
    personaElegidaDatos.value = null;
    tipoPersona.value = '';
    casoPersona.value = '';
    versionBuscador.value++;
};

watch(() => props.vehiculo, cargar, { immediate: true });

const agregarPersona = () => {
    if (!personaElegida.value || !tipoPersona.value.trim()) {
        aviso('Seleccione una persona y el tipo de información.');

        return;
    }
    const tipo = tipoPersona.value.trim().toUpperCase();
    if (form.personas_asociadas.some((p) => p.persona_id === personaElegida.value && p.tipo === tipo)) {
        aviso('Esta persona ya está agregada con ese tipo.');

        return;
    }
    form.personas_asociadas.push({
        persona_id: personaElegida.value,
        nombre: personaElegidaDatos.value ? nombreCompleto(personaElegidaDatos.value) : '',
        tipo,
        caso: casoPersona.value.trim().toUpperCase(),
    });
    personaElegida.value = null;
    personaElegidaDatos.value = null;
    tipoPersona.value = '';
    casoPersona.value = '';
};
const quitarPersona = (indice) => form.personas_asociadas.splice(indice, 1);

const guardar = () => {
    const opciones = {
        forceFormData: true,
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            if (!editando.value) {
                cargar();
            }
            emit('guardado');
        },
    };
    const limpiar = (datos) => ({ ...datos, personas_asociadas: datos.personas_asociadas.map(({ persona_id, tipo, caso }) => ({ persona_id, tipo, caso })) });
    if (editando.value) {
        form.transform((datos) => ({ ...limpiar(datos), _method: 'put' })).post(route('vehiculos.update', props.vehiculo.id), opciones);
    } else {
        form.transform(limpiar).post(route('vehiculos.store'), opciones);
    }
};

const eliminarFoto = async (foto) => {
    if (!(await confirmar({ titulo: '¿Eliminar foto?', texto: 'Esta acción no se puede deshacer', confirmar: 'Sí, eliminar', peligro: true }))) {
        return;
    }
    try {
        await http.delete(route('multimedia.destroy', foto.id));
        fotosActuales.value = fotosActuales.value.filter((f) => f.id !== foto.id);
        exito('Foto eliminada correctamente', 'Eliminada');
        emit('fotos-cambiadas');
    } catch (err) {
        errorDeRespuesta(err);
    }
};

defineExpose({ guardar, procesando: computed(() => form.processing) });
</script>

<template>
    <form :id="idFormulario" novalidate @submit.prevent="guardar">
        <div class="row g-3">
            <div class="col-md-6">
                <label for="placa" class="form-label">Placa *</label>
                <input id="placa" v-model="form.placa" v-mayuscula v-sin-espacios type="text" class="form-control" :class="{ 'is-invalid': form.errors.placa }" placeholder="Ej: LAB-123">
                <CampoError :mensaje="form.errors.placa" />
            </div>
            <div class="col-md-6">
                <label for="responsable" class="form-label">Responsable</label>
                <input id="responsable" v-model="form.responsable" v-mayuscula type="text" class="form-control" :class="{ 'is-invalid': form.errors.responsable }" placeholder="Nombre del responsable">
                <CampoError :mensaje="form.errors.responsable" />
            </div>
            <div class="col-12">
                <label for="descripcion" class="form-label">Descripción</label>
                <textarea id="descripcion" v-model="form.descripcion" class="form-control" :class="{ 'is-invalid': form.errors.descripcion }" rows="2" maxlength="255" placeholder="Descripción del vehículo" />
                <CampoError :mensaje="form.errors.descripcion" />
            </div>
            <div class="col-12">
                <label for="caso_relacionado" class="form-label">Caso Relacionado</label>
                <input id="caso_relacionado" v-model="form.caso_relacionado" v-mayuscula type="text" class="form-control" :class="{ 'is-invalid': form.errors.caso_relacionado }" placeholder="Caso relacionado">
                <CampoError :mensaje="form.errors.caso_relacionado" />
            </div>
        </div>

        <hr>
        <h6 class="mb-3">Personas Asociadas</h6>
        <div class="mb-3">
            <label class="form-label">Buscar Persona</label>
            <BuscadorRemoto :key="versionBuscador" v-model="personaElegida" :url="route('personas.search')" placeholder="Nombre, apellido o CI" :mapear="(p) => ({ id: p.id, texto: textoPersona(p) })" @seleccionado="(p) => (personaElegidaDatos = p)" />
        </div>
        <div class="row g-3">
            <div class="col-md-6">
                <label for="tipoPersona" class="form-label">Tipo de Información *</label>
                <input id="tipoPersona" v-model="tipoPersona" v-mayuscula list="tiposPersona" class="form-control" placeholder="Seleccionar tipo">
                <datalist id="tiposPersona"><option v-for="tipo in TIPOS" :key="tipo" :value="tipo" /></datalist>
            </div>
            <div class="col-md-6">
                <label for="casoPersona" class="form-label">Caso (Opcional)</label>
                <input id="casoPersona" v-model="casoPersona" v-mayuscula type="text" class="form-control" placeholder="Ingresa el caso si aplica">
            </div>
        </div>
        <button type="button" class="btn btn-sm btn-secondary my-3" @click="agregarPersona"><i class="ri-add-line align-middle me-1" /> Agregar Persona</button>
        <div v-for="(persona, indice) in form.personas_asociadas" :key="`${persona.persona_id}-${persona.tipo}`" class="card mb-2 border">
            <div class="card-body p-2 d-flex justify-content-between align-items-center">
                <div>
                    <small class="fw-bold d-block">{{ persona.nombre || `Persona #${persona.persona_id}` }}</small>
                    <span class="badge badge-outline-secondary">{{ persona.tipo }}</span>
                    <template v-if="persona.caso">Caso: <span class="badge badge-outline-info">{{ persona.caso }}</span></template>
                </div>
                <button type="button" class="btn btn-sm btn-soft-danger" title="Quitar" @click="quitarPersona(indice)"><i class="ri-close-line align-bottom" /></button>
            </div>
        </div>
        <CampoError :mensaje="form.errors['personas_asociadas.0.persona_id'] || form.errors['personas_asociadas.0.tipo']" />

        <hr>
        <h6 class="mb-3">Fotos del Vehículo <small class="text-muted fw-normal">(JPEG, PNG, WebP, JPG - máx 2MB por archivo)</small></h6>
        <SubirArchivos v-model="form.fotosVehiculos" :maximo="10" :tamano-maximo-mb="2" acepta="image/jpeg,image/png,image/webp,image/jpg" texto="Arrastra o sube las fotos del vehículo" :error="form.errors['fotosVehiculos.0']" />
        <div v-if="fotosActuales.length" class="mt-3">
            <label class="form-label">Fotos Actuales:</label>
            <div class="d-flex flex-wrap gap-2">
                <div v-for="foto in fotosActuales" :key="foto.id" class="position-relative">
                    <img :src="urlArchivo(foto.ruta)" alt="Foto del vehículo" class="rounded border miniatura-archivo">
                    <button v-if="puede('vehiculos.editar')" type="button" class="btn btn-sm btn-danger position-absolute top-0 end-0 m-1 py-0 px-1" aria-label="Eliminar foto" @click="eliminarFoto(foto)"><i class="ri-delete-bin-fill" /></button>
                </div>
            </div>
        </div>
    </form>
</template>
