<script setup>
import { onMounted, reactive, ref, watch } from 'vue';
import PageHeader from '@/Components/Layout/PageHeader.vue';
import BaseModal from '@/Components/Ui/BaseModal.vue';
import RangoFechas from '@/Components/Ui/RangoFechas.vue';
import VisorImagenes from '@/Components/Ui/VisorImagenes.vue';
import DetalleMandamiento from '@/Components/Mandamientos/DetalleMandamiento.vue';
import FormularioMandamiento from '@/Components/Mandamientos/FormularioMandamiento.vue';
import ListadoMandamientos from '@/Components/Mandamientos/ListadoMandamientos.vue';
import { useCargaInfinita } from '@/Composables/useCargaInfinita';
import { useNotificacion } from '@/Composables/useNotificacion';
import { usePermisos } from '@/Composables/usePermisos';
import { useVistaGuardada } from '@/Composables/useVistaGuardada';
import { FILTROS_MANDAMIENTO } from '@/Utils/mandamiento';
import { http } from '@/Utils/http';
import { guiaFormularioMandamiento } from '@/Utils/guias';
import { ESPERA_BUSQUEDA } from '@/Utils/espera';

const props = defineProps({
    estados: { type: Array, default: () => [] },
    juzgados: { type: Array, required: true },
    delitos: { type: Array, required: true },
    tiposMandamiento: { type: Array, required: true },
});

const { puede } = usePermisos();
const { confirmar, exito, errorDeRespuesta } = useNotificacion();

const filtros = reactive({ search: '', tipo_filtro: '', fecha_inicio: '', fecha_fin: '' });
const vista = useVistaGuardada('mandamientosViewMode');
const lista = useCargaInfinita(() => route('mandamientos.index'), () => ({ ...filtros }));

onMounted(() => lista.reiniciar());

// La búsqueda espera ESPERA_BUSQUEDA (900 ms) y no se lanza con 1 solo carácter (igual que antes).
let temporizador = null;
watch(
    () => filtros.search,
    (texto) => {
        clearTimeout(temporizador);
        if (texto.trim() !== '' && texto.trim().length < 2) {
            return;
        }
        temporizador = setTimeout(() => lista.reiniciar(), ESPERA_BUSQUEDA);
    },
);
watch(() => filtros.tipo_filtro, () => filtros.search && lista.reiniciar());
const cambioFechas = () => lista.reiniciar();

// Formulario
const modalFormulario = ref(false);
const editando = ref(null);
const formulario = ref(null);
const abrirNuevo = () => {
    editando.value = null;
    modalFormulario.value = true;
};
const abrirEditar = (mandamiento) => {
    editando.value = mandamiento;
    modalFormulario.value = true;
};
const guardado = (datos) => {
    if (datos) {
        lista.actualizar(datos);
    }
    modalFormulario.value = false;
};

// Detalle
const modalDetalle = ref(false);
const detalle = ref(null);
const verDetalle = async (mandamiento) => {
    detalle.value = null;
    modalDetalle.value = true;
    try {
        const { data } = await http.get(route('mandamientos.show', mandamiento.id));
        detalle.value = data;
    } catch (err) {
        modalDetalle.value = false;
        errorDeRespuesta(err);
    }
};

const eliminar = async (mandamiento) => {
    const ok = await confirmar({
        titulo: '¿Estás seguro de eliminar este mandamiento?',
        texto: `HR: ${mandamiento.hoja_ruta ?? '-'} · ${mandamiento.nombre_completo}`,
        confirmar: '¡Sí, eliminar!',
        peligro: true,
    });
    if (!ok) {
        return;
    }
    try {
        const { data } = await http.delete(route('mandamientos.destroy', mandamiento.id));
        lista.quitar(mandamiento.id);
        exito(data.success, 'Mandamiento Eliminado');
    } catch (err) {
        errorDeRespuesta(err);
    }
};

// Fotos
const visor = ref(false);
const imagenes = ref([]);
const verImagenes = (rutas) => {
    if (rutas?.length) {
        imagenes.value = rutas;
        visor.value = true;
    }
};
</script>

<template>
    <div>
        <PageHeader titulo="Lista de Mandamientos" :migas="[{ etiqueta: 'Mandamientos' }]">
            <template v-if="puede('mandamientos.crear')" #acciones>
                <button type="button" class="btn btn-primary" @click="abrirNuevo">
                    <i class="ri-add-line align-middle me-1" /> Nuevo Mandamiento
                </button>
            </template>
        </PageHeader>

        <div class="card">
            <div class="card-header">
                <div class="row g-2 align-items-center">
                    <div class="col-lg-4">
                        <div class="input-group">
                            <span class="input-group-text bg-primary text-white border-primary"><i class="ri-search-line" /></span>
                            <input v-model="filtros.search" type="search" class="form-control" placeholder="Buscar mandamiento..." aria-label="Buscar mandamiento">
                        </div>
                    </div>
                    <div class="col-lg-2">
                        <select v-model="filtros.tipo_filtro" class="form-select" aria-label="Filtrar por">
                            <option value="">Filtrar por</option>
                            <option v-for="filtro in FILTROS_MANDAMIENTO" :key="filtro.valor" :value="filtro.valor">{{ filtro.etiqueta }}</option>
                        </select>
                    </div>
                    <div class="col-lg">
                        <RangoFechas v-model:inicio="filtros.fecha_inicio" v-model:fin="filtros.fecha_fin" @cambio="cambioFechas" />
                    </div>
                    <div class="col-lg-auto">
                        <div class="btn-group" role="group" aria-label="Vista">
                            <button type="button" class="btn btn-outline-primary" :class="{ active: vista === 'grid' }" @click="vista = 'grid'">
                                <i class="ri-grid-fill" /> Grid
                            </button>
                            <button type="button" class="btn btn-outline-primary" :class="{ active: vista === 'list' }" @click="vista = 'list'">
                                <i class="ri-list-check" /> Lista
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <ListadoMandamientos
                :lista="lista"
                :vista="vista"
                :puede-editar="puede('mandamientos.editar')"
                :puede-eliminar="puede('mandamientos.eliminar')"
                @ver="verDetalle"
                @editar="abrirEditar"
                @eliminar="eliminar"
                @ver-imagenes="verImagenes"
            />
        </div>

        <BaseModal v-model="modalFormulario" :titulo="editando ? 'Editar Mandamiento' : 'Registrar Mandamiento'" tamano="lg" centrado estatico :guia="guiaFormularioMandamiento">
            <FormularioMandamiento
                ref="formulario"
                :mandamiento="editando"
                :estados="estados"
                :juzgados="juzgados"
                :delitos="delitos"
                :tipos-mandamiento="tiposMandamiento"
                @guardado="guardado"
            />
            <template #pie="{ cerrar }">
                <button type="button" class="btn btn-light" @click="cerrar">Cancelar</button>
                <button type="submit" form="formMandamiento" class="btn btn-primary" data-tour="f-guardar" :disabled="formulario?.procesando">
                    <i class="align-middle me-1" :class="formulario?.procesando ? 'ri-loader-4-line' : 'ri-save-3-line'" />
                    {{ formulario?.procesando ? 'Guardando…' : 'Guardar' }}
                </button>
            </template>
        </BaseModal>

        <BaseModal v-model="modalDetalle" titulo="Detalles del Mandamiento" tamano="xl" centrado>
            <DetalleMandamiento v-if="detalle" :mandamiento="detalle" />
            <div v-else class="text-center py-5"><div class="spinner-border text-primary" role="status" /><p class="text-muted mt-2 mb-0">Cargando detalles...</p></div>
            <template #pie="{ cerrar }">
                <button type="button" class="btn btn-light" @click="cerrar">Cerrar</button>
            </template>
        </BaseModal>

        <VisorImagenes v-model="visor" :rutas="imagenes" />
    </div>
</template>
