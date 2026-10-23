<script setup>
import { computed, reactive, ref } from 'vue';
import PageHeader from '@/Components/Layout/PageHeader.vue';
import BaseModal from '@/Components/Ui/BaseModal.vue';
import RangoFechas from '@/Components/Ui/RangoFechas.vue';
import VisorImagenes from '@/Components/Ui/VisorImagenes.vue';
import DetalleMandamiento from '@/Components/Mandamientos/DetalleMandamiento.vue';
import ListadoMandamientos from '@/Components/Mandamientos/ListadoMandamientos.vue';
import { useCargaInfinita } from '@/Composables/useCargaInfinita';
import { useNotificacion } from '@/Composables/useNotificacion';
import { useVistaGuardada } from '@/Composables/useVistaGuardada';
import { FILTROS_MANDAMIENTO } from '@/Utils/mandamiento';
import { http } from '@/Utils/http';
import { nuevoIdentificador } from '@/Utils/uuid';

/**
 * Consulta de mandamientos: se busca con un filtro elegido y al menos 4 caracteres. Cada búsqueda nueva lleva un
 * identificador que el servidor registra en la Auditoría de consultas junto con los detalles que se abran.
 */
const { errorDeRespuesta } = useNotificacion();

const FILTROS = [
    FILTROS_MANDAMIENTO[0],
    { valor: 'nombre_completo', etiqueta: 'Nombre completo' },
    ...FILTROS_MANDAMIENTO.slice(1),
];

const filtros = reactive({ search: '', tipo_filtro: '', fecha_inicio: '', fecha_fin: '' });
const vista = useVistaGuardada('mandamientosViewMode');
const consultados = reactive({ search: null, tipo_filtro: null });
const identificador = ref(null);
const buscado = ref(false);

const lista = useCargaInfinita(
    () => route('consultas.mandamientos'),
    () => ({ ...consultados, fecha_inicio: filtros.fecha_inicio, fecha_fin: filtros.fecha_fin, identificador: identificador.value }),
);

const valido = computed(() => filtros.tipo_filtro !== '' && filtros.search.trim().length >= 4);

const buscar = () => {
    if (!valido.value) {
        return;
    }
    const nuevo = consultados.tipo_filtro !== filtros.tipo_filtro || consultados.search !== filtros.search;
    if (nuevo) {
        consultados.tipo_filtro = filtros.tipo_filtro;
        consultados.search = filtros.search;
        identificador.value = nuevoIdentificador();
    }
    buscado.value = true;
    lista.reiniciar(nuevo ? { nuevo_filtro: 1 } : {});
};

const modalDetalle = ref(false);
const detalle = ref(null);
const verDetalle = async (mandamiento) => {
    detalle.value = null;
    modalDetalle.value = true;
    try {
        const { data } = await http.get(route('mandamientos.show', mandamiento.id), { params: { identificador: identificador.value } });
        detalle.value = data;
    } catch (err) {
        modalDetalle.value = false;
        errorDeRespuesta(err);
    }
};

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
        <PageHeader titulo="Consultar Mandamientos" :migas="[{ etiqueta: 'Consultas' }, { etiqueta: 'Mandamientos' }]" />

        <p class="mb-2">
            Para realizar una búsqueda, seleccione un filtro y escriba al menos 4 caracteres en el campo de búsqueda, luego
            haga clic en el botón de búsqueda.
        </p>

        <div class="card">
            <div class="card-header">
                <form class="row g-2 align-items-center" @submit.prevent="buscar">
                    <div class="col-lg-4">
                        <div class="input-group">
                            <input v-model="filtros.search" type="search" class="form-control" placeholder="Buscar mandamiento..." aria-label="Buscar mandamiento">
                            <button type="submit" class="btn btn-primary" :disabled="!valido"><i class="ri-search-line" /> Buscar</button>
                        </div>
                    </div>
                    <div class="col-lg-2">
                        <select v-model="filtros.tipo_filtro" class="form-select" aria-label="Filtrar por">
                            <option value="">Filtrar por</option>
                            <option v-for="filtro in FILTROS" :key="filtro.valor" :value="filtro.valor">{{ filtro.etiqueta }}</option>
                        </select>
                    </div>
                    <div class="col-lg">
                        <RangoFechas v-model:inicio="filtros.fecha_inicio" v-model:fin="filtros.fecha_fin" />
                    </div>
                    <div class="col-lg-auto">
                        <div class="btn-group" role="group" aria-label="Vista">
                            <button type="button" class="btn btn-outline-primary" :class="{ active: vista === 'grid' }" title="Cuadrícula" @click="vista = 'grid'"><i class="ri-grid-fill" /></button>
                            <button type="button" class="btn btn-outline-primary" :class="{ active: vista === 'list' }" title="Lista" @click="vista = 'list'"><i class="ri-list-check" /></button>
                        </div>
                    </div>
                </form>
            </div>

            <ListadoMandamientos
                v-if="buscado"
                :lista="lista"
                :vista="vista"
                ayuda-vacio="Revise el texto buscado o pruebe con otro filtro."
                @ver="verDetalle"
                @ver-imagenes="verImagenes"
            />
            <div v-else class="card-body text-center text-muted py-5">
                <i class="ri-file-search-line fs-1 d-block mb-2" />
                Elija un filtro y escriba al menos 4 caracteres para consultar.
            </div>
        </div>

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
