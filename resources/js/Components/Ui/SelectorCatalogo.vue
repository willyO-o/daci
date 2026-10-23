<script setup>
import { computed, ref, watch } from 'vue';
import { useNotificacion } from '@/Composables/useNotificacion';
import { http } from '@/Utils/http';

/**
 * Select con búsqueda de un catálogo corto (juzgados, delitos, tipos de mandamiento, divisiones) que permite agregar
 * un valor nuevo si no existe (select2 con `tags` del sistema anterior). El valor nuevo se guarda en MAYÚSCULAS.
 *
 *   <SelectorCatalogo v-model="form.id_juzgado" :opciones="juzgados" campo-texto="nombre_juzgado"
 *       :url-nuevo="route('juzgados.store')" campo-nuevo="nombre_juzgado" @creado="(j) => juzgados.push(j)" />
 */
const props = defineProps({
    modelValue: { type: [Number, String, null], default: null },
    opciones: { type: Array, required: true },
    campoTexto: { type: String, required: true },
    placeholder: { type: String, default: 'Seleccione…' },
    /** Ruta para crear un valor; sin ella no se ofrece «añadir nuevo». */
    urlNuevo: { type: String, default: null },
    campoNuevo: { type: String, default: null },
    etiquetaEntidad: { type: String, default: 'valor' },
    invalido: { type: Boolean, default: false },
    idCampo: { type: String, default: undefined },
});

const emit = defineEmits(['update:modelValue', 'creado']);

const { exito, error } = useNotificacion();

const abierto = ref(false);
const texto = ref('');
const resaltado = ref(0);
const guardando = ref(false);

const seleccionado = computed(() => props.opciones.find((opcion) => String(opcion.id) === String(props.modelValue)));
const textoSeleccionado = computed(() => seleccionado.value?.[props.campoTexto] ?? '');

watch(textoSeleccionado, (valor) => {
    if (!abierto.value) {
        texto.value = valor;
    }
}, { immediate: true });

const normal = (valor) => (valor ?? '').toString().trim().toUpperCase();

const filtradas = computed(() => {
    const buscado = normal(texto.value);
    const lista = buscado && buscado !== normal(textoSeleccionado.value)
        ? props.opciones.filter((opcion) => normal(opcion[props.campoTexto]).includes(buscado))
        : props.opciones;

    return lista.slice(0, 50);
});

const puedeCrear = computed(() => {
    const buscado = normal(texto.value);

    return Boolean(props.urlNuevo && buscado && buscado !== normal(textoSeleccionado.value)
        && !props.opciones.some((opcion) => normal(opcion[props.campoTexto]) === buscado));
});

const abrir = () => {
    abierto.value = true;
    resaltado.value = 0;
};

const elegir = (opcion) => {
    emit('update:modelValue', opcion.id);
    texto.value = opcion[props.campoTexto];
    abierto.value = false;
};

const crear = async () => {
    const nuevo = normal(texto.value);
    guardando.value = true;
    try {
        const { data } = await http.post(props.urlNuevo, { [props.campoNuevo ?? props.campoTexto]: nuevo });
        const registro = data.data ?? data;
        emit('creado', registro);
        elegir(registro);
        exito(`${props.etiquetaEntidad.charAt(0).toUpperCase()}${props.etiquetaEntidad.slice(1)} agregado correctamente`);
    } catch {
        error(`Error al agregar el ${props.etiquetaEntidad}`);
    } finally {
        guardando.value = false;
    }
};

const cerrar = () => {
    setTimeout(() => {
        abierto.value = false;
        texto.value = textoSeleccionado.value;
    }, 150);
};

const limpiar = () => {
    emit('update:modelValue', null);
    texto.value = '';
};

const total = computed(() => filtradas.value.length + (puedeCrear.value ? 1 : 0));

const mover = (paso) => {
    if (total.value) {
        resaltado.value = (resaltado.value + paso + total.value) % total.value;
    }
};

const confirmar = () => {
    if (resaltado.value < filtradas.value.length) {
        elegir(filtradas.value[resaltado.value]);
    } else if (puedeCrear.value) {
        crear();
    }
};
</script>

<template>
    <div class="buscador-remoto position-relative">
        <input
            :id="idCampo"
            v-model="texto"
            type="text"
            class="form-control pe-5"
            :class="{ 'is-invalid': invalido }"
            :placeholder="placeholder"
            autocomplete="off"
            role="combobox"
            :aria-expanded="abierto"
            @focus="abrir"
            @input="abrir"
            @blur="cerrar"
            @keydown.down.prevent="mover(1)"
            @keydown.up.prevent="mover(-1)"
            @keydown.enter.prevent="confirmar"
            @keydown.esc="abierto = false"
        >
        <button
            v-if="modelValue"
            type="button"
            class="btn btn-link position-absolute end-0 top-0 text-muted shadow-none"
            aria-label="Quitar selección"
            @mousedown.prevent="limpiar"
        >
            <i class="ri-close-line" />
        </button>

        <div class="dropdown-menu shadow" :class="{ show: abierto }" role="listbox">
            <button
                v-for="(opcion, indice) in filtradas"
                :key="opcion.id"
                type="button"
                class="dropdown-item text-wrap"
                :class="{ activo: indice === resaltado, active: String(opcion.id) === String(modelValue) }"
                role="option"
                @mousedown.prevent="elegir(opcion)"
            >
                {{ opcion[campoTexto] }}
            </button>
            <button
                v-if="puedeCrear"
                type="button"
                class="dropdown-item text-primary"
                :class="{ activo: resaltado === filtradas.length }"
                :disabled="guardando"
                @mousedown.prevent="crear"
            >
                <i class="ri-add-line align-middle me-1" /> {{ texto.trim().toUpperCase() }} (añadir nuevo)
            </button>
            <span v-if="!filtradas.length && !puedeCrear" class="dropdown-item-text text-muted small">Sin resultados</span>
        </div>
    </div>
</template>
