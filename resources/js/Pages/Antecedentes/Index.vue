<script setup>
import { onMounted, reactive, ref, watch } from 'vue';
import { Link } from '@inertiajs/vue3';
import PageHeader from '@/Components/Layout/PageHeader.vue';
import BaseModal from '@/Components/Ui/BaseModal.vue';
import ListaInfinita from '@/Components/Ui/ListaInfinita.vue';
import VisorImagenes from '@/Components/Ui/VisorImagenes.vue';
import DetalleRegistro from '@/Components/RegistroCriminal/DetalleRegistro.vue';
import FichaRegistro from '@/Components/RegistroCriminal/FichaRegistro.vue';
import TarjetaRegistro from '@/Components/RegistroCriminal/TarjetaRegistro.vue';
import { useCargaInfinita } from '@/Composables/useCargaInfinita';
import { useNotificacion } from '@/Composables/useNotificacion';
import { usePermisos } from '@/Composables/usePermisos';
import { useVistaGuardada } from '@/Composables/useVistaGuardada';
import { FILTROS_REGISTRO } from '@/Utils/registroCriminal';
import { http } from '@/Utils/http';
import { ESPERA_BUSQUEDA } from '@/Utils/espera';

/**
 * Listado de Faltas y Contravenciones: el mismo de Registro Criminal, con las rutas y
 * permisos del módulo que llegan en `modulo`.
 */
const props = defineProps({
    modulo: { type: Object, required: true },
});

const { puede } = usePermisos();
const { confirmar, exito, errorDeRespuesta } = useNotificacion();

const filtros = reactive({ search: '', filtro: '' });
const vista = useVistaGuardada(`${props.modulo.clave}ViewMode`);
const lista = useCargaInfinita(() => route(props.modulo.rutas.index), () => ({ ...filtros }));

onMounted(() => lista.reiniciar());

// Igual que antes: espera ESPERA_BUSQUEDA (900 ms) y no busca con menos de 3 caracteres.
let temporizador = null;
watch(
    () => filtros.search,
    (texto) => {
        clearTimeout(temporizador);
        if (texto.trim() !== '' && texto.trim().length < 3) {
            return;
        }
        temporizador = setTimeout(() => lista.reiniciar(), ESPERA_BUSQUEDA);
    },
);
watch(() => filtros.filtro, () => filtros.search && lista.reiniciar());

const modalDetalle = ref(false);
const detalle = ref(null);
const verDetalle = async (registro) => {
    detalle.value = null;
    modalDetalle.value = true;
    try {
        detalle.value = (await http.get(route(props.modulo.rutas.show, registro.id))).data;
    } catch (err) {
        modalDetalle.value = false;
        errorDeRespuesta(err);
    }
};

const modalFicha = ref(false);
const registroFicha = ref(null);
const abrirFicha = (registro) => {
    registroFicha.value = registro;
    modalFicha.value = true;
};

const eliminar = async (registro) => {
    const ok = await confirmar({
        titulo: `¿Eliminar el registro Nro. ${registro.nro_registro}?`,
        texto: `${registro.persona.nombres} ${registro.persona.apellidos ?? ''}. Se borran también sus fotos y fichas; no se puede deshacer.`,
        confirmar: '¡Sí, eliminar!',
        peligro: true,
    });
    if (!ok) {
        return;
    }
    try {
        const { data } = await http.delete(route(props.modulo.rutas.destroy, registro.id));
        lista.quitar(registro.id);
        exito(data.success, 'Registro Eliminado');
    } catch (err) {
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
        <PageHeader :titulo="`Listado de ${modulo.titulo}`" :migas="[{ etiqueta: modulo.titulo }]">
            <template v-if="puede(modulo.permisos.crear)" #acciones>
                <Link :href="route(modulo.rutas.create)" class="btn btn-primary"><i class="ri-add-line align-middle me-1" /> Nuevo Registro</Link>
            </template>
        </PageHeader>

        <div class="card">
            <div class="card-header">
                <div class="row g-2 align-items-center">
                    <div class="col-lg-4">
                        <div class="input-group">
                            <span class="input-group-text bg-primary text-white border-primary"><i class="ri-search-line" /></span>
                            <input v-model="filtros.search" type="search" class="form-control" :placeholder="`Buscar en ${modulo.titulo.toLowerCase()}...`" :aria-label="`Buscar en ${modulo.titulo}`">
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
                            <button type="button" class="btn btn-outline-primary" :class="{ active: vista === 'grid' }" @click="vista = 'grid'"><i class="ri-grid-fill" /> Grid</button>
                            <button type="button" class="btn btn-outline-primary" :class="{ active: vista === 'list' }" @click="vista = 'list'"><i class="ri-list-check" /> Lista</button>
                        </div>
                    </div>
                </div>
            </div>

            <ListaInfinita :lista="lista" :texto-vacio="`No se encontraron ${modulo.titulo.toLowerCase()}`">
                <template #default="{ elemento }">
                    <TarjetaRegistro
                        :registro="elemento"
                        :vista="vista"
                        :puede-editar="puede(modulo.permisos.editar)"
                        :puede-eliminar="puede(modulo.permisos.eliminar)"
                        :puede-ficha="puede(modulo.permisos.ficha)"
                        :ruta-editar="modulo.rutas.edit"
                        @ver="verDetalle"
                        @ficha="abrirFicha"
                        @eliminar="eliminar"
                        @ver-imagenes="verImagenes"
                    />
                </template>
            </ListaInfinita>
        </div>

        <BaseModal v-model="modalDetalle" titulo="Detalles del Registro" tamano="xl" centrado>
            <DetalleRegistro v-if="detalle" :registro="detalle" :ruta-registros="modulo.rutas.showByCodigo" :titulo-registros="`Otras ${modulo.titulo}`" />
            <div v-else class="text-center py-5"><div class="spinner-border text-primary" role="status" /><p class="text-muted mt-2 mb-0">Cargando detalles...</p></div>
            <template #pie="{ cerrar }">
                <button type="button" class="btn btn-light" @click="cerrar">Cerrar</button>
            </template>
        </BaseModal>

        <BaseModal v-model="modalFicha" titulo="Ficha de Registro" tamano="xl" estatico cuerpo-clase="bg-light">
            <FichaRegistro
                v-if="registroFicha"
                :registro-id="registroFicha.id"
                :ruta-vista-previa="modulo.rutas['vista-previa']"
                :ruta-guardar="modulo.rutas['ficha.store']"
                campo-registro="registro_id"
            />
            <template #pie="{ cerrar }">
                <button type="button" class="btn btn-light" @click="cerrar">Cerrar</button>
            </template>
        </BaseModal>

        <VisorImagenes v-model="visor" :rutas="imagenes" />
    </div>
</template>
