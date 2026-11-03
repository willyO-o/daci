<script setup>
import { computed, ref } from 'vue';
import VisorImagenes from '@/Components/Ui/VisorImagenes.vue';
import { useNotificacion } from '@/Composables/useNotificacion';
import { tamano } from '@/Utils/evidencias';
import { http } from '@/Utils/http';

/**
 * Archivos de una evidencia o de un artículo. Las imágenes se ven en miniatura y se amplían con el visor; los PDF se
 * abren en otra pestaña. Todos se sirven por la ruta protegida (`archivo.url`), nunca por `/storage`.
 *
 *   <GaleriaArchivos :archivos="lista" :tipos="reglas.tiposArchivo" eliminable @eliminado="(id) => …" />
 *   <GaleriaArchivos :archivos="fotos" :tipos="reglas.tiposArchivo" publicable @cambiado="(a) => …" />
 */
const props = defineProps({
    archivos: { type: Array, default: () => [] },
    /** `reglas.tiposArchivo`: etiqueta de cada tipo. */
    tipos: { type: Object, default: () => ({}) },
    /** Muestra «Quitar» (al editar). */
    eliminable: { type: Boolean, default: false },
    /** Muestra el interruptor «Pública» en las fotografías del artículo (quien publica). */
    publicable: { type: Boolean, default: false },
    vacio: { type: String, default: 'Sin archivos adjuntos.' },
});

const emit = defineEmits(['eliminado', 'cambiado']);

const { confirmar, exito, errorDeRespuesta } = useNotificacion();

const imagenes = computed(() => props.archivos.filter((a) => a.es_imagen));
const documentos = computed(() => props.archivos.filter((a) => !a.es_imagen));
const visor = ref(false);
const inicial = ref(0);
const ocupado = ref(null);

const ampliar = (archivo) => {
    inicial.value = imagenes.value.findIndex((a) => a.id === archivo.id);
    visor.value = true;
};

const etiqueta = (archivo) => props.tipos[archivo.tipo]?.etiqueta ?? archivo.tipo;

const eliminar = async (archivo) => {
    if (!(await confirmar({ titulo: `¿Quitar «${archivo.nombre_archivo}»?`, texto: 'Dejará de verse en la evidencia.', confirmar: 'Sí, quitar', peligro: true }))) {
        return;
    }
    ocupado.value = archivo.id;
    try {
        await http.delete(route('evidencias.archivos.destroy', archivo.id));
        exito('Archivo quitado.');
        emit('eliminado', archivo.id);
    } catch (err) {
        errorDeRespuesta(err);
    } finally {
        ocupado.value = null;
    }
};

const puedeSerPublica = (archivo) => props.publicable && archivo.tipo === 'FOTO_ARTICULO' && archivo.evidencia_articulo_id;

const alternarPublica = async (archivo) => {
    ocupado.value = archivo.id;
    try {
        const { data } = await http.patch(route('evidencias.archivos.publico', archivo.id), { es_publico: !archivo.es_publico });
        emit('cambiado', { ...archivo, es_publico: data.es_publico });
    } catch (err) {
        errorDeRespuesta(err);
    } finally {
        ocupado.value = null;
    }
};
</script>

<template>
    <div>
        <p v-if="!archivos.length" class="text-muted small mb-0">{{ vacio }}</p>

        <div v-if="imagenes.length" class="d-flex flex-wrap gap-2" :class="{ 'mb-2': documentos.length }">
            <figure v-for="archivo in imagenes" :key="archivo.id" class="galeria-evidencia mb-0">
                <button type="button" class="galeria-evidencia__miniatura" :title="`Ampliar: ${archivo.nombre_archivo}`" @click="ampliar(archivo)">
                    <img :src="archivo.url" :alt="`${etiqueta(archivo)}: ${archivo.nombre_archivo}`" loading="lazy">
                    <span v-if="archivo.es_publico" class="badge bg-success galeria-evidencia__marca">Pública</span>
                </button>
                <figcaption class="small text-muted text-truncate" :title="etiqueta(archivo)">{{ etiqueta(archivo) }}</figcaption>
                <div v-if="eliminable || puedeSerPublica(archivo)" class="d-flex gap-1 mt-1">
                    <div v-if="puedeSerPublica(archivo)" class="form-check form-switch mb-0 small">
                        <input
                            :id="`publica-${archivo.id}`"
                            class="form-check-input"
                            type="checkbox"
                            :checked="archivo.es_publico"
                            :disabled="ocupado === archivo.id"
                            @change="alternarPublica(archivo)"
                        >
                        <label class="form-check-label" :for="`publica-${archivo.id}`">Pública</label>
                    </div>
                    <button v-if="eliminable" type="button" class="btn btn-sm btn-soft-danger py-0 px-1 ms-auto" :disabled="ocupado === archivo.id" :aria-label="`Quitar ${archivo.nombre_archivo}`" @click="eliminar(archivo)">
                        <i class="ri-delete-bin-line" />
                    </button>
                </div>
            </figure>
        </div>

        <ul v-if="documentos.length" class="list-unstyled mb-0">
            <li v-for="archivo in documentos" :key="archivo.id" class="d-flex align-items-center gap-2 py-1">
                <i class="ri-file-pdf-2-line fs-18 text-danger" aria-hidden="true" />
                <a :href="archivo.url" target="_blank" rel="noopener" class="text-truncate">{{ archivo.nombre_archivo }}</a>
                <span class="text-muted small text-nowrap">{{ etiqueta(archivo) }}<template v-if="archivo.tamano"> · {{ tamano(archivo.tamano) }}</template></span>
                <i v-if="archivo.privado" class="ri-lock-line text-muted" title="Documento interno: no se publica" aria-label="Documento interno" />
                <button v-if="eliminable" type="button" class="btn btn-sm btn-soft-danger py-0 px-1 ms-auto" :disabled="ocupado === archivo.id" :aria-label="`Quitar ${archivo.nombre_archivo}`" @click="eliminar(archivo)">
                    <i class="ri-delete-bin-line" />
                </button>
            </li>
        </ul>

        <VisorImagenes v-model="visor" :rutas="imagenes.map((a) => a.url)" :inicial="inicial" />
    </div>
</template>

<style scoped>
.galeria-evidencia {
    width: 112px;
}

.galeria-evidencia__miniatura {
    position: relative;
    display: block;
    width: 112px;
    height: 112px;
    padding: 0;
    overflow: hidden;
    border: 1px solid var(--vz-border-color);
    border-radius: var(--vz-border-radius);
    background: var(--vz-light);
    cursor: zoom-in;
}

.galeria-evidencia__miniatura img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

.galeria-evidencia__miniatura:hover img,
.galeria-evidencia__miniatura:focus-visible img {
    transform: scale(1.04);
}

.galeria-evidencia__miniatura:focus-visible {
    outline: 2px solid var(--vz-primary);
    outline-offset: 2px;
}

.galeria-evidencia__marca {
    position: absolute;
    left: 4px;
    bottom: 4px;
}

@media (prefers-reduced-motion: reduce) {
    .galeria-evidencia__miniatura img {
        transition: none;
    }
}
</style>
