<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { useTema } from '@/Composables/useTema';

const page = usePage();
const usuario = computed(() => page.props.auth.user);
const inicial = computed(() => (usuario.value?.name ?? '?').trim().charAt(0).toUpperCase());

const { modoOscuro, menuAbiertoMovil, alternarMenu, alternarModo, alternarPantallaCompleta } = useTema();
</script>

<template>
    <header id="page-topbar">
        <div class="layout-width">
            <div class="navbar-header">
                <div class="d-flex">
                    <div class="navbar-brand-box horizontal-logo">
                        <Link :href="route('dashboard')" class="logo logo-dark">
                            <span class="logo-sm"><img src="/felcc/img/felcc.png" alt="" height="22"></span>
                            <span class="logo-lg"><img src="/felcc/img/felcc.png" alt="" height="17"></span>
                        </Link>
                    </div>

                    <button
                        id="topnav-hamburger-icon"
                        type="button"
                        class="btn btn-sm px-3 fs-16 header-item vertical-menu-btn topnav-hamburger shadow-none"
                        aria-label="Mostrar u ocultar el menú"
                        @click="alternarMenu"
                    >
                        <span class="hamburger-icon" :class="{ open: menuAbiertoMovil }">
                            <span /><span /><span />
                        </span>
                    </button>
                </div>

                <div class="d-flex align-items-center">
                    <div class="ms-1 header-item d-none d-sm-flex">
                        <button
                            type="button"
                            class="btn btn-icon btn-topbar btn-ghost-secondary rounded-circle shadow-none"
                            aria-label="Pantalla completa"
                            title="Pantalla completa"
                            @click="alternarPantallaCompleta"
                        >
                            <i class="bx bx-fullscreen fs-22" />
                        </button>
                    </div>

                    <div class="ms-1 header-item d-none d-sm-flex">
                        <button
                            type="button"
                            class="btn btn-icon btn-topbar btn-ghost-secondary rounded-circle shadow-none"
                            :aria-label="modoOscuro ? 'Usar modo claro' : 'Usar modo oscuro'"
                            :title="modoOscuro ? 'Modo claro' : 'Modo oscuro'"
                            @click="alternarModo"
                        >
                            <i class="bx fs-22" :class="modoOscuro ? 'bx-sun' : 'bx-moon'" />
                        </button>
                    </div>

                    <div v-if="usuario" class="dropdown ms-sm-3 header-item topbar-user">
                        <button
                            id="page-header-user-dropdown"
                            type="button"
                            class="btn shadow-none"
                            data-bs-toggle="dropdown"
                            aria-haspopup="true"
                            aria-expanded="false"
                        >
                            <span class="d-flex align-items-center">
                                <span class="avatar-xs">
                                    <span class="avatar-title bg-soft-primary text-primary rounded-circle fs-14">{{ inicial }}</span>
                                </span>
                                <span class="text-start ms-xl-2">
                                    <span class="d-none d-xl-inline-block ms-1 fw-medium user-name-text">{{ usuario.name }}</span>
                                    <span class="d-none d-xl-block ms-1 fs-12 text-muted user-name-sub-text text-capitalize">
                                        {{ usuario.rol_descripcion ?? 'Usuario' }}
                                    </span>
                                </span>
                            </span>
                        </button>
                        <div class="dropdown-menu dropdown-menu-end">
                            <h6 class="dropdown-header">¡Bienvenido, {{ usuario.name }}!</h6>
                            <Link class="dropdown-item" :href="route('perfil')">
                                <i class="mdi mdi-account-circle text-muted fs-16 align-middle me-1" />
                                <span class="align-middle">Mi Perfil</span>
                            </Link>
                            <div class="dropdown-divider" />
                            <Link class="dropdown-item" :href="route('logout')" method="post" as="button">
                                <i class="mdi mdi-logout text-muted fs-16 align-middle me-1" />
                                <span class="align-middle">Cerrar Sesión</span>
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>
</template>
