<script setup>
import { watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AppTopbar from '@/Components/Layout/AppTopbar.vue';
import AppSidebar from '@/Components/Layout/AppSidebar.vue';
import AppFooter from '@/Components/Layout/AppFooter.vue';
import { useTema } from '@/Composables/useTema';
import { useNotificacion } from '@/Composables/useNotificacion';

/**
 * Layout PERSISTENTE del sistema (asignado en app.js a toda página fuera de `Auth/`): la barra superior y el menú
 * lateral se montan una sola vez; al navegar sólo cambia el contenido del slot.
 */
const page = usePage();
const { cerrarMenuMovil, observarAncho } = useTema();
observarAncho();
const { exito, error, aviso } = useNotificacion();

// Mensajes de sesión (`->with('success', …)`) como toast, igual que el `notification()` del sistema anterior.
watch(
    () => page.props.flash,
    (flash) => {
        if (flash?.success) {
            exito(flash.success);
        }
        if (flash?.error) {
            error(flash.error);
        }
        if (flash?.message) {
            aviso(flash.message);
        }
    },
    { immediate: true },
);
</script>

<template>
    <div id="layout-wrapper">
        <AppTopbar />
        <AppSidebar />
        <div class="vertical-overlay" @click="cerrarMenuMovil" />

        <div class="main-content">
            <div class="page-content">
                <div class="container-fluid">
                    <slot />
                </div>
            </div>
            <AppFooter />
        </div>
    </div>
</template>
