<script setup>
import { computed, reactive, ref } from 'vue';
import PageHeader from '@/Components/Layout/PageHeader.vue';
import BaseModal from '@/Components/Ui/BaseModal.vue';
import ListaInfinita from '@/Components/Ui/ListaInfinita.vue';
import VisorImagenes from '@/Components/Ui/VisorImagenes.vue';
import DetalleRegistro from '@/Components/RegistroCriminal/DetalleRegistro.vue';
import TarjetaRegistro from '@/Components/RegistroCriminal/TarjetaRegistro.vue';
import { useCargaInfinita } from '@/Composables/useCargaInfinita';
import { useNotificacion } from '@/Composables/useNotificacion';
import { useVistaGuardada } from '@/Composables/useVistaGuardada';
import { FILTROS_REGISTRO } from '@/Utils/registroCriminal';
import { http } from '@/Utils/http';
import { nuevoIdentificador } from '@/Utils/uuid';

/**
 * Consulta de Faltas y Contravenciones, igual que la de Registro Criminal: filtro
 * obligatorio y al menos 4 caracteres. Cada búsqueda nueva y los detalles que se abran quedan en la Auditoría de
 * consultas del módulo.
 */
const props = defineProps({
    modulo: { type: Object, required: true },
});

const { errorDeRespuesta } = useNotificacion();

const filtros = reactive({ search: '', filtro: '' });
const consultados = reactive({ search: null, filtro: null });
const identificador = ref(null);
const buscado = ref(false);
const vista = useVistaGuardada(`${props.modulo.clave}ViewMode`);

const lista = useCargaInfinita(() => route(props.modulo.rutas.consulta), () => ({ ...consultados, identificador: identificador.value }));

const valido = computed(() => filtros.filtro !== '' && filtros.search.trim().length >= 4);

const buscar = () => {
    if (!valido.value) {
        return;
    }
    const nuevo = consultados.filtro !== filtros.filtro || consultados.search !== filtros.search;
    if (nuevo) {
        consultados.filtro = filtros.filtro;
        consultados.search = filtros.search;
        identificador.value = nuevoIdentificador();
    }
    buscado.value = true;
    lista.reiniciar(nuevo ? { nuevo_filtro: 1 } : {});
};

const modalDetalle = ref(false);
const detalle = ref(null);
const verDetalle = async (registro) => {
    detalle.value = null;
    modalDetalle.value = true;
    try {
        detalle.value = (await http.get(route(props.modulo.rutas.show, registro.id), { params: { identificador: identificador.value } })).data;
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
        <PageHeader :titulo="`Consultar ${modulo.titulo}`" :migas="[{ etiqueta: 'Consultas' }, { etiqueta: modulo.titulo }]" />

        <p class="mb-2">
            Para realizar una búsqueda, seleccione un filtro y escriba al menos 4 caracteres en el campo de búsqueda, luego
            haga clic en el botón de búsqueda.
        </p>

        <div class="card">
            <div class="card-header">
                <form class="row g-2 align-items-center" @submit.prevent="buscar">
                    <div class="col-lg-4">
                        <div class="input-group">
                            <input v-model="filtros.search" type="search" class="form-control" :placeholder="`Buscar en ${modulo.titulo.toLowerCase()}...`" :aria-label="`Buscar en ${modulo.titulo}`">
                            <button type="submit" class="btn btn-primary" :disabled="!valido"><i class="ri-search-line" /> Buscar</button>
                        </div>
                    </div>
                    <div class="col-lg-3">
                        <select v-model="filtros.filtro" class="form-select" aria-label="Filtrar por">
                            <option value="">Filtrar por</option>
                            <option v-for="filtro in FILTROS_REGISTRO" :key="filtro.valor" :value="filtro.valor">{{ filtro.etiqueta }}</option>
                        </select>
                    </div>
                    <div class="col-lg-auto ms-lg-auto">
                        <div class="btn-group" role="group" aria-label="Vista">
                            <button type="button" class="btn btn-outline-primary" :class="{ active: vista === 'grid' }" title="Cuadrícula" @click="vista = 'grid'"><i class="ri-grid-fill" /></button>
                            <button type="button" class="btn btn-outline-primary" :class="{ active: vista === 'list' }" title="Lista" @click="vista = 'list'"><i class="ri-list-check" /></button>
                        </div>
                    </div>
                </form>
            </div>

            <ListaInfinita v-if="buscado" :lista="lista" :texto-vacio="`No se encontraron ${modulo.titulo.toLowerCase()}`" ayuda-vacio="Revise el texto buscado o pruebe con otro filtro.">
                <template #default="{ elemento }">
                    <TarjetaRegistro :registro="elemento" :vista="vista" @ver="verDetalle" @ver-imagenes="verImagenes" />
                </template>
            </ListaInfinita>
            <div v-else class="card-body text-center text-muted py-5">
                <i class="ri-file-search-line fs-1 d-block mb-2" />
                Elija un filtro y escriba al menos 4 caracteres para consultar.
            </div>
        </div>

        <BaseModal v-model="modalDetalle" titulo="Detalles del Registro" tamano="xl" centrado>
            <DetalleRegistro v-if="detalle" :registro="detalle" :identificador="identificador" :ruta-registros="modulo.rutas.showByCodigo" :titulo-registros="`Otras ${modulo.titulo}`" />
            <div v-else class="text-center py-5"><div class="spinner-border text-primary" role="status" /><p class="text-muted mt-2 mb-0">Cargando detalles...</p></div>
            <template #pie="{ cerrar }">
                <button type="button" class="btn btn-light" @click="cerrar">Cerrar</button>
            </template>
        </BaseModal>

        <VisorImagenes v-model="visor" :rutas="imagenes" />
    </div>
</template>
