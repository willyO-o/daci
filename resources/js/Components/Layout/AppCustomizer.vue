<script setup>
import { ref } from 'vue';
import MiniaturaTema from '@/Components/Layout/MiniaturaTema.vue';
import { useTema } from '@/Composables/useTema';
import { ocultarPreloader } from '@/Utils/preloader';

/**
 * Panel «Personalizar tema» (el «Theme Customizer» de Velzon que tenía el sistema anterior): botón flotante con el
 * engranaje y panel lateral con los mismos ajustes, guardados en el navegador de cada usuario.
 *
 * No incluye la elección de layout horizontal / dos columnas: el menú de DACI sólo tiene el marcado vertical.
 */
const { ajustes, ajustar, restablecer } = useTema();

const MENU_OSCURO = { opciones: 'bg-soft-light' };

const SECCIONES = [
    {
        atributo: 'data-layout-mode',
        titulo: 'Esquema de color',
        ayuda: 'Elija el esquema claro u oscuro.',
        opciones: [
            { valor: 'light', texto: 'Claro' },
            { valor: 'dark', texto: 'Oscuro', oscuro: true, miniatura: { lateral: 'bg-soft-light', opciones: 'bg-soft-light', superior: 'bg-soft-light' } },
        ],
    },
    {
        atributo: 'data-layout-width',
        titulo: 'Ancho del layout',
        ayuda: 'Elija entre ancho completo o encajado.',
        opciones: [
            { valor: 'fluid', texto: 'Completo' },
            { valor: 'boxed', texto: 'Encajado', etiqueta: 'px-2', miniatura: { encajado: true } },
        ],
    },
    {
        atributo: 'data-layout-position',
        titulo: 'Posición del layout',
        ayuda: 'Elija si el menú y la barra superior quedan fijos o se desplazan con la página.',
        botones: true,
        opciones: [
            { valor: 'fixed', texto: 'Fijo' },
            { valor: 'scrollable', texto: 'Desplazable' },
        ],
    },
    {
        atributo: 'data-topbar',
        titulo: 'Color de la barra superior',
        ayuda: 'Elija una barra superior clara u oscura.',
        opciones: [
            { valor: 'light', texto: 'Clara' },
            { valor: 'dark', texto: 'Oscura', miniatura: { superior: 'bg-dark' } },
        ],
    },
    {
        atributo: 'data-sidebar-size',
        titulo: 'Tamaño del menú',
        ayuda: 'Elija el tamaño del menú lateral.',
        opciones: [
            { valor: 'lg', texto: 'Normal' },
            { valor: 'md', texto: 'Compacto', miniatura: { menu: 'compacto' } },
            { valor: 'sm', texto: 'Pequeño (sólo íconos)', miniatura: { menu: 'iconos' } },
            { valor: 'sm-hover', texto: 'Pequeño, se abre al pasar', miniatura: { menu: 'iconos' } },
        ],
    },
    {
        atributo: 'data-layout-style',
        titulo: 'Vista del menú',
        ayuda: 'Elija el menú normal o separado bajo la barra superior.',
        opciones: [
            { valor: 'default', texto: 'Normal' },
            { valor: 'detached', texto: 'Separado', miniatura: { separado: true } },
        ],
    },
    {
        atributo: 'data-sidebar',
        titulo: 'Color del menú',
        ayuda: 'Elija el color del menú lateral.',
        opciones: [
            { valor: 'light', texto: 'Claro', miniatura: { lateral: 'bg-white border-end' } },
            { valor: 'dark', texto: 'Oscuro', miniatura: { lateral: 'bg-primary', ...MENU_OSCURO } },
        ],
        degradados: ['gradient', 'gradient-2', 'gradient-3', 'gradient-4'],
    },
];

const IMAGENES = ['img-1', 'img-2', 'img-3', 'img-4'];

const esDegradado = (valor) => String(valor).startsWith('gradient');
const verDegradados = ref(esDegradado(ajustes['data-sidebar']));
const claseDegradado = (valor) => `bg-vertical-${valor}`;

const elegirPreloader = (valor) => {
    ajustar('data-preloader', valor);
    // Igual que la plantilla: al activarlo se muestra un instante para ver cómo queda.
    if (valor === 'enable') {
        ocultarPreloader({ mostrarAntes: true });
    }
};
</script>

<template>
    <div>
        <div class="customizer-setting d-none d-md-block">
            <button
                type="button"
                class="btn-info btn-rounded shadow-lg btn btn-icon btn-lg p-2"
                data-bs-toggle="offcanvas"
                data-bs-target="#panel-personalizar-tema"
                aria-controls="panel-personalizar-tema"
                aria-label="Personalizar tema"
            >
                <i class="mdi mdi-spin mdi-cog-outline fs-22" />
            </button>
        </div>

        <div id="panel-personalizar-tema" class="offcanvas offcanvas-end border-0" tabindex="-1" aria-labelledby="titulo-personalizar-tema">
            <div class="d-flex align-items-center bg-primary bg-gradient p-3 offcanvas-header">
                <h5 id="titulo-personalizar-tema" class="m-0 me-2 text-white">Personalizar tema</h5>
                <button type="button" class="btn-close btn-close-white ms-auto" data-bs-dismiss="offcanvas" aria-label="Cerrar" />
            </div>

            <div class="offcanvas-body p-0">
                <div class="h-100 overflow-auto">
                    <div class="p-4">
                        <template v-for="(seccion, indice) in SECCIONES" :key="seccion.atributo">
                            <h6 class="mb-0 fw-semibold text-uppercase fs-12" :class="{ 'mt-4': indice > 0 }">{{ seccion.titulo }}</h6>
                            <p class="text-muted">{{ seccion.ayuda }}</p>

                            <div v-if="seccion.botones" class="btn-group radio" role="group" :aria-label="seccion.titulo">
                                <template v-for="(opcion, posicion) in seccion.opciones" :key="opcion.valor">
                                    <input
                                        :id="`${seccion.atributo}-${opcion.valor}`"
                                        type="radio"
                                        class="btn-check"
                                        :name="seccion.atributo"
                                        :value="opcion.valor"
                                        :checked="ajustes[seccion.atributo] === opcion.valor"
                                        @change="ajustar(seccion.atributo, opcion.valor)"
                                    >
                                    <label class="btn btn-light w-sm" :class="{ 'ms-0': posicion > 0 }" :for="`${seccion.atributo}-${opcion.valor}`">{{ opcion.texto }}</label>
                                </template>
                            </div>

                            <template v-else>
                                <div class="row">
                                    <div v-for="opcion in seccion.opciones" :key="opcion.valor" class="col-4">
                                        <div class="form-check card-radio" :class="{ dark: opcion.oscuro }">
                                            <input
                                                :id="`${seccion.atributo}-${opcion.valor}`"
                                                class="form-check-input"
                                                type="radio"
                                                :name="seccion.atributo"
                                                :value="opcion.valor"
                                                :checked="ajustes[seccion.atributo] === opcion.valor"
                                                @change="ajustar(seccion.atributo, opcion.valor)"
                                            >
                                            <label
                                                class="form-check-label p-0 avatar-md w-100 shadow-sm"
                                                :class="[opcion.etiqueta, { 'bg-dark': opcion.oscuro }]"
                                                :for="`${seccion.atributo}-${opcion.valor}`"
                                            >
                                                <MiniaturaTema v-bind="opcion.miniatura ?? {}" />
                                            </label>
                                        </div>
                                        <h5 class="fs-13 text-center mt-2">{{ opcion.texto }}</h5>
                                    </div>

                                    <div v-if="seccion.degradados" class="col-4">
                                        <button
                                            class="btn btn-link avatar-md w-100 p-0 overflow-hidden border"
                                            :class="{ 'border-primary': esDegradado(ajustes[seccion.atributo]) }"
                                            type="button"
                                            aria-controls="degradados-menu"
                                            :aria-expanded="verDegradados"
                                            @click="verDegradados = !verDegradados"
                                        >
                                            <MiniaturaTema lateral="bg-vertical-gradient" opciones="bg-soft-light" />
                                        </button>
                                        <h5 class="fs-13 text-center mt-2">Degradado</h5>
                                    </div>
                                </div>

                                <div v-if="seccion.degradados" id="degradados-menu" v-show="verDegradados">
                                    <div class="d-flex gap-2 flex-wrap img-switch p-2 px-3 bg-light rounded">
                                        <div v-for="degradado in seccion.degradados" :key="degradado" class="form-check sidebar-setting card-radio">
                                            <input
                                                :id="`${seccion.atributo}-${degradado}`"
                                                class="form-check-input"
                                                type="radio"
                                                :name="seccion.atributo"
                                                :value="degradado"
                                                :checked="ajustes[seccion.atributo] === degradado"
                                                @change="ajustar(seccion.atributo, degradado)"
                                            >
                                            <label class="form-check-label p-0 avatar-xs rounded-circle" :for="`${seccion.atributo}-${degradado}`" :aria-label="`Degradado ${degradado}`">
                                                <span class="avatar-title rounded-circle" :class="claseDegradado(degradado)" />
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </template>

                        <h6 class="mt-4 mb-0 fw-semibold text-uppercase fs-12">Imagen del menú</h6>
                        <p class="text-muted">Elija una imagen de fondo para el menú lateral.</p>
                        <div class="d-flex gap-2 flex-wrap img-switch">
                            <div class="form-check sidebar-setting card-radio">
                                <input
                                    id="data-sidebar-image-none"
                                    class="form-check-input"
                                    type="radio"
                                    name="data-sidebar-image"
                                    value="none"
                                    :checked="ajustes['data-sidebar-image'] === 'none'"
                                    @change="ajustar('data-sidebar-image', 'none')"
                                >
                                <label class="form-check-label p-0 avatar-sm h-auto" for="data-sidebar-image-none" aria-label="Sin imagen">
                                    <span class="avatar-md w-auto bg-light d-flex align-items-center justify-content-center">
                                        <i class="ri-close-fill fs-20" />
                                    </span>
                                </label>
                            </div>
                            <div v-for="imagen in IMAGENES" :key="imagen" class="form-check sidebar-setting card-radio">
                                <input
                                    :id="`data-sidebar-image-${imagen}`"
                                    class="form-check-input"
                                    type="radio"
                                    name="data-sidebar-image"
                                    :value="imagen"
                                    :checked="ajustes['data-sidebar-image'] === imagen"
                                    @change="ajustar('data-sidebar-image', imagen)"
                                >
                                <label class="form-check-label p-0 avatar-sm h-auto" :for="`data-sidebar-image-${imagen}`">
                                    <img :src="`/assets/images/sidebar/${imagen}.jpg`" :alt="`Fondo ${imagen}`" class="avatar-md w-auto object-cover">
                                </label>
                            </div>
                        </div>

                        <h6 class="mt-4 mb-0 fw-semibold text-uppercase fs-12">Pantalla de carga</h6>
                        <p class="text-muted">Mostrar un indicador mientras se abre el sistema.</p>
                        <div class="row">
                            <div v-for="opcion in [{ valor: 'enable', texto: 'Activada' }, { valor: 'disable', texto: 'Desactivada' }]" :key="opcion.valor" class="col-4">
                                <div class="form-check sidebar-setting card-radio">
                                    <input
                                        :id="`data-preloader-${opcion.valor}`"
                                        class="form-check-input"
                                        type="radio"
                                        name="data-preloader"
                                        :value="opcion.valor"
                                        :checked="ajustes['data-preloader'] === opcion.valor"
                                        @change="elegirPreloader(opcion.valor)"
                                    >
                                    <label class="form-check-label p-0 avatar-md w-100 position-relative" :for="`data-preloader-${opcion.valor}`">
                                        <MiniaturaTema />
                                        <span v-if="opcion.valor === 'enable'" class="position-absolute top-50 start-50 translate-middle">
                                            <span class="spinner-border text-primary avatar-xxs m-auto" role="presentation" />
                                        </span>
                                    </label>
                                </div>
                                <h5 class="fs-13 text-center mt-2">{{ opcion.texto }}</h5>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="offcanvas-footer border-top p-3 text-center">
                <button type="button" class="btn btn-light w-100" @click="restablecer">Restablecer</button>
            </div>
        </div>
    </div>
</template>
