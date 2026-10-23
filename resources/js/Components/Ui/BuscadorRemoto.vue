<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { esCancelacion, http } from '@/Utils/http';

/**
 * Selector con búsqueda en el servidor (reemplaza a select2 con `ajax` del sistema anterior). Guarda el `id` en
 * v-model y emite el registro completo en `seleccionado`.
 *
 *   <BuscadorRemoto v-model="form.id_persona" :url="route('personas.search')" :texto-inicial="persona?.nombre"
 *       :mapear="(p) => ({ id: p.id, texto: `${p.nombres} ${p.apellidos} - CI: ${p.ci ?? ''}` })" />
 */
const props = defineProps({
    modelValue: { type: [Number, String, null], default: null },
    url: { type: String, required: true },
    /** Texto del valor ya elegido al abrir un formulario de edición. */
    textoInicial: { type: String, default: '' },
    placeholder: { type: String, default: 'Escriba para buscar…' },
    minimo: { type: Number, default: 2 },
    /** Convierte cada resultado del servidor a `{ id, texto, detalle? }`. */
    mapear: { type: Function, default: (item) => ({ id: item.id, texto: item.text ?? item.texto ?? String(item.id) }) },
    /** Parámetros extra de la búsqueda (p. ej. `{ id }` para excluir el registro actual). */
    parametros: { type: Object, default: () => ({}) },
    /** Nombre del parámetro de texto que espera el servidor. */
    parametroTexto: { type: String, default: 'q' },
    invalido: { type: Boolean, default: false },
    deshabilitado: { type: Boolean, default: false },
    permitirLimpiar: { type: Boolean, default: true },
    idCampo: { type: String, default: undefined },
});

const emit = defineEmits(['update:modelValue', 'seleccionado']);

const texto = ref(props.textoInicial);
const elegido = ref(props.textoInicial);
const resultados = ref([]);
const abierto = ref(false);
const buscando = ref(false);
const fallo = ref(false);
const resaltado = ref(-1);
let temporizador = null;
let controlador = null;

watch(
    () => props.textoInicial,
    (valor) => {
        texto.value = valor;
        elegido.value = valor;
    },
);

watch(
    () => props.modelValue,
    (valor) => {
        if (valor === null || valor === '' || valor === undefined) {
            texto.value = '';
            elegido.value = '';
        }
    },
);

const faltanLetras = computed(() => texto.value.trim().length < props.minimo);

const buscar = async () => {
    controlador?.abort();
    controlador = new AbortController();
    buscando.value = true;
    fallo.value = false;

    try {
        const { data } = await http.get(props.url, {
            params: { [props.parametroTexto]: texto.value.trim(), ...props.parametros },
            signal: controlador.signal,
        });
        const lista = Array.isArray(data) ? data : (data.datos ?? data.data ?? []);
        resultados.value = lista.map((item) => ({ ...props.mapear(item), original: item }));
        resaltado.value = resultados.value.length ? 0 : -1;
    } catch (err) {
        if (!esCancelacion(err)) {
            fallo.value = true;
            resultados.value = [];
        }
    } finally {
        buscando.value = false;
    }
};

const alEscribir = () => {
    abierto.value = true;
    clearTimeout(temporizador);
    if (faltanLetras.value) {
        resultados.value = [];

        return;
    }
    temporizador = setTimeout(buscar, 250);
};

const elegir = (opcion) => {
    elegido.value = opcion.texto;
    texto.value = opcion.texto;
    abierto.value = false;
    emit('update:modelValue', opcion.id);
    emit('seleccionado', opcion.original);
};

const limpiar = () => {
    texto.value = '';
    elegido.value = '';
    resultados.value = [];
    emit('update:modelValue', null);
    emit('seleccionado', null);
};

const alSalir = () => {
    // Pequeña espera para que el clic en una opción llegue antes de cerrar.
    setTimeout(() => {
        abierto.value = false;
        texto.value = elegido.value;
    }, 150);
};

const mover = (paso) => {
    if (!abierto.value || !resultados.value.length) {
        return;
    }
    resaltado.value = (resaltado.value + paso + resultados.value.length) % resultados.value.length;
};

const confirmar = () => {
    if (abierto.value && resultados.value[resaltado.value]) {
        elegir(resultados.value[resaltado.value]);
    }
};

onBeforeUnmount(() => {
    clearTimeout(temporizador);
    controlador?.abort();
});
</script>

<template>
    <div class="buscador-remoto position-relative">
        <div class="position-relative">
            <input
                :id="idCampo"
                v-model="texto"
                type="search"
                class="form-control pe-5"
                :class="{ 'is-invalid': invalido }"
                :placeholder="placeholder"
                :disabled="deshabilitado"
                autocomplete="off"
                role="combobox"
                :aria-expanded="abierto"
                @input="alEscribir"
                @focus="texto && !faltanLetras && (abierto = true)"
                @blur="alSalir"
                @keydown.down.prevent="mover(1)"
                @keydown.up.prevent="mover(-1)"
                @keydown.enter.prevent="confirmar"
                @keydown.esc="abierto = false"
            >
            <button
                v-if="permitirLimpiar && modelValue && !deshabilitado"
                type="button"
                class="btn btn-link position-absolute end-0 top-0 text-muted shadow-none"
                aria-label="Quitar selección"
                @mousedown.prevent="limpiar"
            >
                <i class="ri-close-line" />
            </button>
        </div>

        <div class="dropdown-menu shadow" :class="{ show: abierto }" role="listbox">
            <span v-if="faltanLetras" class="dropdown-item-text text-muted small">
                Escriba al menos {{ minimo }} caracteres
            </span>
            <span v-else-if="buscando" class="dropdown-item-text text-muted small">
                <span class="spinner-border spinner-border-sm me-2" role="status" /> Buscando…
            </span>
            <span v-else-if="fallo" class="dropdown-item-text text-danger small">
                No se pudo buscar. Revise la conexión e intente de nuevo.
            </span>
            <span v-else-if="!resultados.length" class="dropdown-item-text text-muted small">Sin resultados</span>
            <button
                v-for="(opcion, indice) in resultados"
                v-else
                :key="opcion.id"
                type="button"
                class="dropdown-item text-wrap"
                :class="{ activo: indice === resaltado }"
                role="option"
                :aria-selected="indice === resaltado"
                @mousedown.prevent="elegir(opcion)"
            >
                {{ opcion.texto }}
                <small v-if="opcion.detalle" class="d-block text-muted">{{ opcion.detalle }}</small>
            </button>
        </div>
    </div>
</template>
