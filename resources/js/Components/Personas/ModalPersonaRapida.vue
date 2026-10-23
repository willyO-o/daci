<script setup>
import { computed, watch } from 'vue';
import BaseModal from '@/Components/Ui/BaseModal.vue';
import CampoError from '@/Components/Ui/CampoError.vue';
import SubirArchivos from '@/Components/Ui/SubirArchivos.vue';
import { useFormularioJson } from '@/Composables/useFormularioJson';
import { useNotificacion } from '@/Composables/useNotificacion';

/**
 * «Añadir Nueva Persona» desde otro formulario (mandamientos): sólo los datos mínimos y hasta 3 fotos. Devuelve la
 * persona creada en el evento `creada` para seleccionarla.
 */
const props = defineProps({
    modelValue: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue', 'creada']);

const { exito, errorDeRespuesta } = useNotificacion();

const abierto = computed({
    get: () => props.modelValue,
    set: (valor) => emit('update:modelValue', valor),
});

const form = useFormularioJson({
    ci: '',
    nombres: '',
    apellidos: '',
    telefono: '',
    fecha_nacimiento: '',
    domicilio: '',
    fotos: [],
});

watch(abierto, (valor) => valor && form.reiniciar());

const guardar = async () => {
    try {
        const { data } = await form.enviar(route('personas.store'));
        exito('La persona se guardó correctamente.', 'Persona agregada');
        emit('creada', data.datos);
        abierto.value = false;
    } catch (err) {
        if (err.response?.status !== 422) {
            errorDeRespuesta(err);
        }
    }
};
</script>

<template>
    <BaseModal v-model="abierto" titulo="Añadir Nueva Persona" centrado estatico>
        <form id="formPersonaRapida" novalidate @submit.prevent="guardar">
            <div class="row g-2">
                <div class="col-12">
                    <input v-model="form.datos.ci" v-mayuscula type="text" class="form-control" :class="{ 'is-invalid': form.errores.value.ci }" placeholder="Cédula de Identidad (opcional)" aria-label="Cédula de Identidad">
                    <CampoError :mensaje="form.errores.value.ci" />
                </div>
                <div class="col-12">
                    <input v-model="form.datos.nombres" v-mayuscula type="text" class="form-control" :class="{ 'is-invalid': form.errores.value.nombres }" placeholder="Nombres *" aria-label="Nombres">
                    <CampoError :mensaje="form.errores.value.nombres" />
                </div>
                <div class="col-12">
                    <input v-model="form.datos.apellidos" v-mayuscula type="text" class="form-control" :class="{ 'is-invalid': form.errores.value.apellidos }" placeholder="Apellidos" aria-label="Apellidos">
                    <CampoError :mensaje="form.errores.value.apellidos" />
                </div>
                <div class="col-12">
                    <input v-model="form.datos.telefono" v-solo-numeros type="tel" class="form-control" :class="{ 'is-invalid': form.errores.value.telefono }" placeholder="Número de Celular (opcional)" aria-label="Número de celular">
                    <CampoError :mensaje="form.errores.value.telefono" />
                </div>
                <div class="col-12">
                    <label class="form-label small text-muted mb-1" for="rapidaNacimiento">Fecha de Nacimiento (opcional)</label>
                    <input id="rapidaNacimiento" v-model="form.datos.fecha_nacimiento" type="date" class="form-control" :class="{ 'is-invalid': form.errores.value.fecha_nacimiento }">
                    <CampoError :mensaje="form.errores.value.fecha_nacimiento" />
                </div>
                <div class="col-12">
                    <textarea v-model="form.datos.domicilio" class="form-control" rows="3" placeholder="Dirección (opcional)" aria-label="Dirección" />
                </div>
                <div class="col-12">
                    <SubirArchivos v-model="form.datos.fotos" :maximo="3" :tamano-maximo-mb="2" acepta="image/*" texto="Arrastra o sube hasta 3 imágenes (opcional)" :error="form.errores.value['fotos.0']" />
                </div>
            </div>
        </form>
        <template #pie="{ cerrar }">
            <button type="button" class="btn btn-light" @click="cerrar">Cancelar</button>
            <button type="submit" form="formPersonaRapida" class="btn btn-primary" :disabled="form.procesando.value">
                {{ form.procesando.value ? 'Guardando…' : 'Guardar' }}
            </button>
        </template>
    </BaseModal>
</template>
