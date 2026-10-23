<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';

/**
 * Zona para arrastrar o elegir archivos, con vista previa y los mismos límites que FilePond tenía en el sistema
 * anterior (cantidad, tamaño y tipo). v-model = File[].
 *
 *   <SubirArchivos v-model="form.fotos" :maximo="4" :tamano-maximo-mb="2" acepta="image/*" />
 */
const props = defineProps({
    modelValue: { type: Array, default: () => [] },
    maximo: { type: Number, default: 4 },
    tamanoMaximoMb: { type: Number, default: 2 },
    acepta: { type: String, default: 'image/*' },
    multiple: { type: Boolean, default: true },
    texto: { type: String, default: 'Arrastra o sube fotos (opcional)' },
    error: { type: [String, Array], default: null },
});

const emit = defineEmits(['update:modelValue']);

const entrada = ref(null);
const arrastrando = ref(false);
const avisos = ref([]);
const vistas = ref([]);

const tiposAceptados = computed(() => props.acepta.split(',').map((tipo) => tipo.trim()));

const tipoValido = (archivo) =>
    tiposAceptados.value.some((tipo) => {
        if (tipo.endsWith('/*')) {
            return archivo.type.startsWith(tipo.replace('*', ''));
        }
        if (tipo.startsWith('.')) {
            return archivo.name.toLowerCase().endsWith(tipo.toLowerCase());
        }

        return archivo.type === tipo;
    });

const regenerarVistas = () => {
    vistas.value.forEach((vista) => vista.url && URL.revokeObjectURL(vista.url));
    vistas.value = props.modelValue.map((archivo) => ({
        nombre: archivo.name,
        tamano: (archivo.size / 1024 / 1024).toFixed(2),
        url: archivo.type.startsWith('image/') ? URL.createObjectURL(archivo) : null,
    }));
};

watch(() => props.modelValue, regenerarVistas, { immediate: true });

const agregar = (lista) => {
    avisos.value = [];
    const actuales = props.multiple ? [...props.modelValue] : [];

    for (const archivo of lista) {
        if (actuales.length >= (props.multiple ? props.maximo : 1)) {
            avisos.value.push(`Máximo ${props.maximo} archivo(s).`);
            break;
        }
        if (!tipoValido(archivo)) {
            avisos.value.push(`«${archivo.name}»: tipo de archivo no permitido.`);
            continue;
        }
        if (archivo.size > props.tamanoMaximoMb * 1024 * 1024) {
            avisos.value.push(`«${archivo.name}»: supera ${props.tamanoMaximoMb} MB.`);
            continue;
        }
        actuales.push(archivo);
    }

    emit('update:modelValue', actuales);
};

const alElegir = (evento) => {
    agregar(Array.from(evento.target.files ?? []));
    evento.target.value = '';
};

const alSoltar = (evento) => {
    arrastrando.value = false;
    agregar(Array.from(evento.dataTransfer?.files ?? []));
};

const quitar = (indice) => {
    const lista = [...props.modelValue];
    lista.splice(indice, 1);
    emit('update:modelValue', lista);
};

onBeforeUnmount(() => vistas.value.forEach((vista) => vista.url && URL.revokeObjectURL(vista.url)));
</script>

<template>
    <div>
        <div
            class="zona-archivos p-3 text-center cursor-pointer"
            :class="{ arrastrando, 'border-danger': error }"
            role="button"
            tabindex="0"
            @click="entrada.click()"
            @keydown.enter.prevent="entrada.click()"
            @dragover.prevent="arrastrando = true"
            @dragleave.prevent="arrastrando = false"
            @drop.prevent="alSoltar"
        >
            <i class="ri-upload-cloud-2-line fs-1 text-muted d-block mb-1" />
            <span class="text-muted">{{ texto }}</span>
            <span class="d-block small text-muted">
                <span class="text-primary text-decoration-underline">Seleccionar</span>
                · hasta {{ multiple ? maximo : 1 }} de {{ tamanoMaximoMb }} MB
            </span>
            <input ref="entrada" type="file" class="d-none" :accept="acepta" :multiple="multiple" @change="alElegir">
        </div>

        <ul v-if="avisos.length" class="list-unstyled small text-danger mt-2 mb-0">
            <li v-for="aviso in avisos" :key="aviso">{{ aviso }}</li>
        </ul>
        <div v-if="error" class="invalid-feedback d-block">{{ Array.isArray(error) ? error[0] : error }}</div>

        <div v-if="vistas.length" class="d-flex flex-wrap gap-2 mt-2">
            <div v-for="(vista, indice) in vistas" :key="`${vista.nombre}-${indice}`" class="position-relative">
                <img v-if="vista.url" :src="vista.url" :alt="vista.nombre" class="miniatura-archivo rounded border">
                <div v-else class="miniatura-archivo rounded border d-flex flex-column align-items-center justify-content-center p-1">
                    <i class="ri-file-pdf-2-line fs-2 text-danger" />
                    <small class="text-truncate w-100 text-center">{{ vista.nombre }}</small>
                </div>
                <button
                    type="button"
                    class="btn btn-sm btn-danger position-absolute top-0 end-0 m-1 py-0 px-1"
                    :aria-label="`Quitar ${vista.nombre}`"
                    @click="quitar(indice)"
                >
                    <i class="ri-close-line" />
                </button>
                <small class="d-block text-muted text-center cifra">{{ vista.tamano }} MB</small>
            </div>
        </div>
    </div>
</template>
