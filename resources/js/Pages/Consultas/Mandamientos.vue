<script setup>
import { computed, reactive, ref } from 'vue';
import PageHeader from '@/Components/Layout/PageHeader.vue';
import BaseModal from '@/Components/Ui/BaseModal.vue';
import BotonAyuda from '@/Components/Ui/BotonAyuda.vue';
import RangoFechas from '@/Components/Ui/RangoFechas.vue';
import VisorImagenes from '@/Components/Ui/VisorImagenes.vue';
import DetalleMandamiento from '@/Components/Mandamientos/DetalleMandamiento.vue';
import ListadoMandamientos from '@/Components/Mandamientos/ListadoMandamientos.vue';
import ModalEstadoMandamiento from '@/Components/Mandamientos/ModalEstadoMandamiento.vue';
import { useCargaInfinita } from '@/Composables/useCargaInfinita';
import { useNotificacion } from '@/Composables/useNotificacion';
import { usePermisos } from '@/Composables/usePermisos';
import { useTour } from '@/Composables/useTour';
import { useVistaGuardada } from '@/Composables/useVistaGuardada';
import { FILTROS_MANDAMIENTO } from '@/Utils/mandamiento';
import { http } from '@/Utils/http';
import { nuevoIdentificador } from '@/Utils/uuid';

/**
 * Consulta de mandamientos: se busca con un filtro elegido y al menos 4 caracteres. Cada búsqueda nueva lleva un
 * identificador que el servidor registra en la Auditoría de consultas junto con los detalles que se abran.
 */
defineProps({
    /** Estados más usados, para sugerir en el modal de estado (vacío sin el permiso). */
    estados: { type: Array, default: () => [] },
});

const { errorDeRespuesta } = useNotificacion();
const { puede } = usePermisos();

// Guía de la pantalla: sólo se abre con el ícono de ayuda. Los pasos de los resultados aparecen si ya hay resultados
// y los botones que dependen de un permiso, sólo si el usuario los ve.
const { iniciar: iniciarGuia } = useTour();
const SIN_RESULTADOS = {
    titulo: 'Resultados',
    texto: 'Aquí aparecerán los mandamientos encontrados. Haga una búsqueda y vuelva a abrir la guía para conocer qué puede hacer con cada resultado.',
};
const abrirGuia = () =>
    iniciarGuia([
        {
            titulo: 'Consultar Mandamientos',
            texto: 'Desde esta pantalla busca mandamientos por un dato de la persona o del mandamiento. Cada búsqueda nueva y cada detalle que abra quedan registrados en la Auditoría de consultas.',
        },
        { elemento: '[data-tour="filtro"]', titulo: '1. Filtrar por', texto: 'Elija primero en qué dato buscar: C.I., nombre completo, apellidos, hoja de ruta, estado, delito, juzgado…' },
        { elemento: '[data-tour="texto"]', titulo: '2. Texto a buscar', texto: 'Escriba lo que busca: al menos 4 caracteres. Para el C.I. puede incluir el complemento.' },
        { elemento: '[data-tour="fechas"]', titulo: '3. Rango de fechas (opcional)', texto: 'Limita la búsqueda a los mandamientos registrados entre esas fechas.' },
        { elemento: '[data-tour="buscar"]', titulo: '4. Buscar', texto: 'Se habilita cuando eligió un filtro y escribió 4 caracteres o más. También puede presionar Enter.' },
        { elemento: '[data-tour="vista"]', titulo: 'Vista', texto: 'Muestra los resultados en cuadrícula o en lista. El sistema recuerda su elección.', lado: 'left' },
        { elemento: '[data-tour="resultados"] [data-tour="tarjeta"]', titulo: 'Resultados', texto: 'Cada tarjeta es un mandamiento encontrado, con la persona, el delito, el tipo, el estado y el juzgado. Al bajar se cargan más.', lado: 'top', siNoExiste: SIN_RESULTADOS },
        { elemento: '[data-tour="tarjeta-ver"]', titulo: 'Ver detalles', texto: 'Abre el detalle completo: datos del mandamiento, de la persona y del estado de ejecución.' },
        { elemento: '[data-tour="tarjeta-estado"]', titulo: 'Estado de ejecución', texto: 'Cambia el estado del mandamiento (también con el lápiz junto al estado). Si escribe EJECUTADO, pide la fecha, quién lo ejecutó y el acta.' },
        { elemento: '[data-tour="tarjeta-archivo"]', titulo: 'Archivo del mandamiento', texto: 'Abre el PDF o la imagen del mandamiento original.' },
        { elemento: '[data-tour="tarjeta-foto"]', titulo: 'Fotos de la persona', texto: 'Haga clic en la foto para verla ampliada, con las demás fotos de la persona.', lado: 'right' },
        { elemento: '[data-tour="ayuda"]', titulo: 'Guía', texto: 'Puede volver a ver esta guía cuando quiera con este botón.', lado: 'left' },
    ]);

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

// Estado de ejecución (permiso `mandamientos.estado`): actualiza la tarjeta sin repetir la búsqueda.
const modalEstado = ref(false);
const enEstado = ref(null);
const abrirEstado = (mandamiento) => {
    enEstado.value = mandamiento;
    modalEstado.value = true;
};
const estadoGuardado = (datos) => {
    if (datos) {
        lista.actualizar(datos);
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
        <PageHeader titulo="Consultar Mandamientos" :migas="[{ etiqueta: 'Consultas' }, { etiqueta: 'Mandamientos' }]">
            <template #acciones>
                <BotonAyuda @click="abrirGuia" />
            </template>
        </PageHeader>

        <div class="card">
            <div class="card-header">
                <form class="row g-2 align-items-center" @submit.prevent="buscar">
                    <div class="col-lg-4">
                        <div class="input-group">
                            <input v-model="filtros.search" type="search" class="form-control" placeholder="Buscar mandamiento..." aria-label="Buscar mandamiento" data-tour="texto">
                            <button type="submit" class="btn btn-primary" :disabled="!valido" data-tour="buscar"><i class="ri-search-line" /> Buscar</button>
                        </div>
                    </div>
                    <div class="col-lg-2">
                        <select v-model="filtros.tipo_filtro" class="form-select" aria-label="Filtrar por" data-tour="filtro">
                            <option value="">Filtrar por</option>
                            <option v-for="filtro in FILTROS" :key="filtro.valor" :value="filtro.valor">{{ filtro.etiqueta }}</option>
                        </select>
                    </div>
                    <div class="col-lg" data-tour="fechas">
                        <RangoFechas v-model:inicio="filtros.fecha_inicio" v-model:fin="filtros.fecha_fin" />
                    </div>
                    <div class="col-lg-auto">
                        <div class="btn-group" role="group" aria-label="Vista" data-tour="vista">
                            <button type="button" class="btn btn-outline-primary" :class="{ active: vista === 'grid' }" title="Cuadrícula" @click="vista = 'grid'"><i class="ri-grid-fill" /></button>
                            <button type="button" class="btn btn-outline-primary" :class="{ active: vista === 'list' }" title="Lista" @click="vista = 'list'"><i class="ri-list-check" /></button>
                        </div>
                    </div>
                </form>
            </div>

            <ListadoMandamientos
                v-if="buscado"
                data-tour="resultados"
                :lista="lista"
                :vista="vista"
                ayuda-vacio="Revise el texto buscado o pruebe con otro filtro."
                :puede-cambiar-estado="puede('mandamientos.estado')"
                @ver="verDetalle"
                @cambiar-estado="abrirEstado"
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

        <ModalEstadoMandamiento v-model="modalEstado" :mandamiento="enEstado" :estados="estados" @guardado="estadoGuardado" />

        <VisorImagenes v-model="visor" :rutas="imagenes" />
    </div>
</template>
