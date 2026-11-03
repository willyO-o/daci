<script setup>
import { ref, watch } from 'vue';
import FormularioCaso from '@/Components/Casos/FormularioCaso.vue';
import BuscadorRemoto from '@/Components/Ui/BuscadorRemoto.vue';
import CampoError from '@/Components/Ui/CampoError.vue';

/**
 * Selector del caso / CUD de un formulario (teléfono, vehículo, vínculo vehículo–persona, registro criminal, faltas).
 * v-model = `id_caso`. Busca en la tabla `caso` y, con «Nuevo», abre debajo el alta rápida (`FormularioCaso rapido`):
 * el caso creado (o el que ya existía con ese código) queda seleccionado. El caso es opcional.
 *
 *   <SelectorCaso v-model="form.id_caso" :codigo="registro?.caso_codigo" :texto-anterior="registro?.id_caso ? null : registro?.cud"
 *       :error="form.errors.id_caso" @seleccionado="(c) => …" />
 */
const props = defineProps({
    modelValue: { type: [Number, String, null], default: null },
    /** Código del caso ya elegido (al editar). */
    codigo: { type: String, default: '' },
    /** Texto heredado de una fila sin caso vinculado: se avisa y, si no se elige otro, el servidor lo conserva. */
    textoAnterior: { type: String, default: null },
    error: { type: String, default: null },
    idCampo: { type: String, default: undefined },
    placeholder: { type: String, default: 'Buscar caso o CUD…' },
});

const emit = defineEmits(['update:modelValue', 'seleccionado']);

const texto = ref(props.codigo ?? '');
const nuevoAbierto = ref(false);
// Se vuelve a montar el buscador para mostrar el caso recién creado aunque su texto no haya cambiado.
const version = ref(0);

watch(() => props.codigo, (valor) => {
    texto.value = valor ?? '';
});
watch(() => props.modelValue, (valor) => {
    if (valor === null || valor === '' || valor === undefined) {
        texto.value = '';
    }
});

const mapear = (c) => ({
    id: c.id,
    texto: c.codigo,
    detalle: [c.estado === 'INACTIVO' ? 'INACTIVO' : null, c.descripcion].filter(Boolean).join(' · ') || null,
});

const alElegir = (caso) => {
    texto.value = caso?.codigo ?? '';
    emit('seleccionado', caso ? { id: caso.id, codigo: caso.codigo } : null);
};

const alCrear = (caso) => {
    nuevoAbierto.value = false;
    texto.value = caso.codigo;
    version.value++;
    emit('update:modelValue', caso.id);
    emit('seleccionado', { id: caso.id, codigo: caso.codigo });
};
</script>

<template>
    <div>
        <div class="d-flex gap-2 align-items-start">
            <div class="flex-grow-1">
                <BuscadorRemoto
                    :key="version"
                    :model-value="modelValue"
                    :url="route('casos.buscar')"
                    :texto-inicial="texto"
                    :minimo="1"
                    :placeholder="placeholder"
                    :mapear="mapear"
                    :invalido="Boolean(error)"
                    :id-campo="idCampo"
                    @update:model-value="(valor) => emit('update:modelValue', valor)"
                    @seleccionado="alElegir"
                />
            </div>
            <button type="button" class="btn btn-soft-primary text-nowrap" :class="{ active: nuevoAbierto }" title="Registrar un caso nuevo" :aria-expanded="nuevoAbierto" @click="nuevoAbierto = !nuevoAbierto">
                <i class="ri-add-line align-middle" /> Nuevo
            </button>
        </div>
        <CampoError :mensaje="error" />
        <small v-if="!modelValue && textoAnterior" class="d-block text-warning mt-1">
            <i class="ri-error-warning-line align-middle" /> Registrado como texto: «{{ textoAnterior }}», sin caso vinculado. Elija o cree el caso; si lo deja vacío, ese texto se conserva.
        </small>
        <div v-if="nuevoAbierto" class="mt-2">
            <FormularioCaso rapido @guardado="alCrear" @cancelar="nuevoAbierto = false" />
        </div>
    </div>
</template>
