<script setup>
import { computed, ref, watch } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { navegacion } from '@/Navegacion/navegacion';
import { usePermisos } from '@/Composables/usePermisos';
import { useTema } from '@/Composables/useTema';

const { cumple } = usePermisos();
const { cerrarMenuMovil } = useTema();
const page = usePage();

const patronesDe = (item) => [item.activo ?? item.ruta].flat().filter(Boolean);

// `page.url` cambia en cada visita: así el ítem activo se recalcula aunque el layout no se vuelva a montar.
const estaActivo = (item) => {
    void page.url;

    return patronesDe(item).some((patron) => route().current(patron));
};

// Un ítem se muestra si su ruta existe y el rol tiene el permiso que esa ruta exige en el servidor.
const permitido = (item) => route().has(item.ruta) && cumple(item.permiso);

const visible = (item) => (item.hijos ? item.hijos.some(permitido) : permitido(item));

const secciones = computed(() =>
    navegacion
        .map((seccion) => ({
            ...seccion,
            items: seccion.items
                .filter(visible)
                .map((item) => (item.hijos ? { ...item, hijos: item.hijos.filter(permitido) } : item)),
        }))
        .filter((seccion) => seccion.items.length > 0),
);

const abiertos = ref({});

const grupoActivo = (item) => item.hijos?.some(estaActivo);

const alternarGrupo = (item) => {
    abiertos.value[item.etiqueta] = !estaAbierto(item);
};

const estaAbierto = (item) => abiertos.value[item.etiqueta] ?? grupoActivo(item);

watch(() => page.url, cerrarMenuMovil);
</script>

<template>
    <div class="app-menu navbar-menu">
        <div class="navbar-brand-box mt-3">
            <Link :href="route('dashboard')" class="logo logo-dark">
                <span class="logo-sm"><img src="/felcc/img/felcc.png" alt="FELCC" width="40"></span>
                <span class="logo-lg"><img src="/felcc/img/felcc.png" alt="FELCC" width="70"></span>
            </Link>
            <Link :href="route('dashboard')" class="logo logo-light">
                <span class="logo-sm"><img src="/felcc/img/felcc.png" alt="FELCC" width="40"></span>
                <span class="logo-lg"><img src="/felcc/img/felcc.png" alt="FELCC" width="70"></span>
            </Link>
        </div>

        <div id="scrollbar" class="menu-desplazable">
            <div class="container-fluid">
                <ul id="navbar-nav" class="navbar-nav">
                    <template v-for="seccion in secciones" :key="seccion.titulo">
                        <li class="menu-title"><span>{{ seccion.titulo }}</span></li>

                        <li v-for="item in seccion.items" :key="item.etiqueta" class="nav-item">
                            <template v-if="item.hijos">
                                <a
                                    href="#"
                                    class="nav-link menu-link"
                                    :class="{ active: grupoActivo(item), collapsed: !estaAbierto(item) }"
                                    role="button"
                                    :aria-expanded="estaAbierto(item)"
                                    @click.prevent="alternarGrupo(item)"
                                >
                                    <i :class="item.icono" /> <span>{{ item.etiqueta }}</span>
                                </a>
                                <div class="collapse menu-dropdown" :class="{ show: estaAbierto(item) }">
                                    <ul class="nav nav-sm flex-column">
                                        <li v-for="hijo in item.hijos" :key="hijo.etiqueta" class="nav-item">
                                            <Link :href="route(hijo.ruta)" class="nav-link" :class="{ active: estaActivo(hijo) }">
                                                {{ hijo.etiqueta }}
                                            </Link>
                                        </li>
                                    </ul>
                                </div>
                            </template>

                            <Link
                                v-else
                                :href="route(item.ruta)"
                                class="nav-link menu-link"
                                :class="{ active: estaActivo(item) }"
                                :aria-current="estaActivo(item) ? 'page' : undefined"
                            >
                                <i :class="item.icono" /> <span>{{ item.etiqueta }}</span>
                            </Link>
                        </li>
                    </template>
                </ul>
            </div>
        </div>

        <div class="sidebar-background" />
    </div>
</template>

<style scoped>
/* La plantilla usa SimpleBar para desplazar el menú; aquí basta el scroll nativo con la barra discreta. */
:global([data-sidebar-size='lg'] .navbar-menu .menu-desplazable) {
    height: 100%;
    overflow-y: auto;
    scrollbar-width: thin;
    scrollbar-color: rgba(255, 255, 255, 0.18) transparent;
}
</style>
