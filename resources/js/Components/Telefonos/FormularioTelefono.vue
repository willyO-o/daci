<script setup>
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import BuscadorRemoto from '@/Components/Ui/BuscadorRemoto.vue';
import CampoError from '@/Components/Ui/CampoError.vue';

/**
 * Formulario de teléfono (alta y edición). Lo usan el modal del listado y la página «Registrar Teléfono». El botón
 * de guardar va fuera (`<button type="submit" :form="idFormulario">`). Tras registrar, queda limpio para el siguiente.
 */
const props = defineProps({
    /** Teléfono a editar (fila del listado); `null` para registrar. */
    telefono: { type: Object, default: null },
    idFormulario: { type: String, default: 'formTelefono' },
});

const emit = defineEmits(['guardado']);

const VACIO = {
    numero_celular: '',
    empresa: '',
    persona_caso: '',
    caso: '',
    persona_id: null,
    respuesta_requerimiento: '',
    informacion: '',
    callapp: '',
    truecall: '',
    uninet: '',
};

const form = useForm({ ...VACIO });
const textoPersonaInicial = ref('');
// Al limpiar tras registrar, se vuelve a montar el buscador para que no quede la persona anterior escrita.
const versionBuscador = ref(0);

const editando = computed(() => Boolean(props.telefono?.id));
const textoPersona = (persona) => `${persona.nombres} ${persona.apellidos ?? ''}${persona.ci ? ` (${persona.ci})` : ''}`;

const cargar = () => {
    const t = props.telefono;
    form.defaults(editando.value ? Object.fromEntries(Object.keys(VACIO).map((campo) => [campo, t[campo] ?? VACIO[campo]])) : { ...VACIO });
    form.reset();
    form.clearErrors();
    textoPersonaInicial.value = editando.value && t.persona ? textoPersona(t.persona) : '';
    versionBuscador.value++;
};

watch(() => props.telefono, cargar, { immediate: true });

const guardar = () => {
    const opciones = {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            if (!editando.value) {
                cargar();
            }
            emit('guardado');
        },
    };
    if (editando.value) {
        form.put(route('telefonos.update', props.telefono.id), opciones);
    } else {
        form.post(route('telefonos.store'), opciones);
    }
};

defineExpose({ guardar, procesando: computed(() => form.processing) });
</script>

<template>
    <form :id="idFormulario" novalidate @submit.prevent="guardar">
        <div class="row g-3">
            <div class="col-md-6">
                <label for="numero_celular" class="form-label">Número Celular <span class="text-danger">*</span></label>
                <input id="numero_celular" v-model="form.numero_celular" v-solo-numeros type="text" class="form-control" :class="{ 'is-invalid': form.errors.numero_celular }" placeholder="Ej: 71234567">
                <CampoError :mensaje="form.errors.numero_celular" />
            </div>
            <div class="col-md-6">
                <label for="empresa" class="form-label">Empresa</label>
                <input id="empresa" v-model="form.empresa" v-mayuscula type="text" class="form-control" :class="{ 'is-invalid': form.errors.empresa }" placeholder="Ej: Viva, Entel, Tigo">
                <CampoError :mensaje="form.errors.empresa" />
            </div>
            <div class="col-md-6">
                <label for="persona_caso" class="form-label">Persona del caso</label>
                <input id="persona_caso" v-model="form.persona_caso" v-mayuscula type="text" class="form-control" :class="{ 'is-invalid': form.errors.persona_caso }" placeholder="Nombre de la persona o caso">
                <CampoError :mensaje="form.errors.persona_caso" />
            </div>
            <div class="col-md-6">
                <label for="caso" class="form-label">Caso</label>
                <input id="caso" v-model="form.caso" v-mayuscula type="text" class="form-control" :class="{ 'is-invalid': form.errors.caso }" placeholder="Referencia del caso">
                <CampoError :mensaje="form.errors.caso" />
            </div>
            <div class="col-md-6">
                <label class="form-label">Relacionar con Persona (opcional)</label>
                <BuscadorRemoto
                    :key="versionBuscador"
                    v-model="form.persona_id"
                    :url="route('personas.search')"
                    :texto-inicial="textoPersonaInicial"
                    placeholder="Seleccionar persona (opcional)"
                    :mapear="(p) => ({ id: p.id, texto: textoPersona(p) })"
                    :invalido="Boolean(form.errors.persona_id)"
                />
                <CampoError :mensaje="form.errors.persona_id" />
            </div>
            <div class="col-md-6">
                <label for="respuesta_requerimiento" class="form-label">Respuesta Requerimiento</label>
                <input id="respuesta_requerimiento" v-model="form.respuesta_requerimiento" type="text" class="form-control" :class="{ 'is-invalid': form.errors.respuesta_requerimiento }" placeholder="Estado de la respuesta">
                <CampoError :mensaje="form.errors.respuesta_requerimiento" />
            </div>
            <div class="col-md-6">
                <label for="informacion" class="form-label">Información</label>
                <input id="informacion" v-model="form.informacion" type="text" class="form-control" :class="{ 'is-invalid': form.errors.informacion }" placeholder="Información adicional sobre el teléfono">
                <CampoError :mensaje="form.errors.informacion" />
            </div>
            <div class="col-md-6">
                <label for="callapp" class="form-label">CallApp</label>
                <input id="callapp" v-model="form.callapp" type="text" class="form-control" :class="{ 'is-invalid': form.errors.callapp }" placeholder="Información CallApp">
            </div>
            <div class="col-md-6">
                <label for="truecall" class="form-label">TrueCall</label>
                <input id="truecall" v-model="form.truecall" type="text" class="form-control" :class="{ 'is-invalid': form.errors.truecall }" placeholder="Información TrueCall">
            </div>
            <div class="col-md-6">
                <label for="uninet" class="form-label">UniNet</label>
                <input id="uninet" v-model="form.uninet" type="text" class="form-control" :class="{ 'is-invalid': form.errors.uninet }" placeholder="Información UniNet">
            </div>
        </div>
    </form>
</template>
