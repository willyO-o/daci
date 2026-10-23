<script setup>
import { Head, Link } from '@inertiajs/vue3';

/**
 * Título de la página con su ruta de migas (la caja `page-title-box` de la plantilla) y, a la derecha o debajo,
 * las acciones principales de la pantalla (slot `acciones`). También fija el <title> del navegador.
 *
 *   <PageHeader titulo="Gestión de Personas" :migas="[{ etiqueta: 'Personas' }]">
 *       <template #acciones>…</template>
 *   </PageHeader>
 */
defineProps({
    titulo: { type: String, required: true },
    /** @type {{ etiqueta: string, ruta?: string, href?: string }[]} — la última es la página actual */
    migas: { type: Array, default: () => [] },
});
</script>

<template>
    <div>
        <Head :title="titulo" />
        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
            <h4 class="mb-sm-0">{{ titulo }}</h4>
            <div class="page-title-right">
                <ol class="breadcrumb m-0">
                    <li class="breadcrumb-item"><Link :href="route('dashboard')">Dashboard</Link></li>
                    <li
                        v-for="(miga, indice) in migas"
                        :key="miga.etiqueta"
                        class="breadcrumb-item"
                        :class="{ active: indice === migas.length - 1 }"
                    >
                        <Link v-if="indice < migas.length - 1 && (miga.ruta || miga.href)" :href="miga.href ?? route(miga.ruta)">
                            {{ miga.etiqueta }}
                        </Link>
                        <template v-else>{{ miga.etiqueta }}</template>
                    </li>
                </ol>
            </div>
        </div>
        <div v-if="$slots.acciones" class="d-flex flex-wrap justify-content-end gap-2 mb-3">
            <slot name="acciones" />
        </div>
    </div>
</template>
