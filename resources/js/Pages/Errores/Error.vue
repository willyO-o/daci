<script setup>
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';

/**
 * Página de error con el diseño de la plantilla (la `errors/404.blade.php` del sistema anterior), también para
 * 403, 419, 500 y 503. Sin menú lateral: se ve igual con o sin sesión.
 */
defineOptions({ layout: null });

const props = defineProps({
    estado: { type: Number, required: true },
});

const page = usePage();

const TEXTOS = {
    403: { titulo: 'Acceso no permitido', texto: 'Su rol no tiene permiso para ver esta página. Si lo necesita, pídalo al administrador.', imagen: '/assets/images/error.svg' },
    404: { titulo: '¡Uppsi! Página no encontrada', texto: '¡La página que estás buscando no está disponible!', imagen: '/assets/images/error400-cover.png' },
    419: { titulo: 'La sesión expiró', texto: 'Pasó demasiado tiempo sin actividad. Recargue la página e intente de nuevo.', imagen: '/assets/images/error.svg' },
    500: { titulo: 'Ocurrió un error en el sistema', texto: 'No se pudo completar la operación. Intente nuevamente; si se repite, avise al administrador.', imagen: '/assets/images/error500.png' },
    503: { titulo: 'Sistema en mantenimiento', texto: 'Estamos realizando tareas de mantenimiento. Vuelva a intentar en unos minutos.', imagen: '/assets/images/error500.png' },
};

const contenido = computed(() => TEXTOS[props.estado] ?? TEXTOS[500]);
const conSesion = computed(() => Boolean(page.props.auth?.user));
</script>

<template>
    <div class="auth-page-wrapper py-5 d-flex justify-content-center align-items-center min-vh-100">
        <Head :title="contenido.titulo" />
        <div class="auth-page-content overflow-hidden p-0">
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-xl-7 col-lg-8">
                        <div class="text-center">
                            <img :src="contenido.imagen" alt="" class="img-fluid" style="max-height: 360px">
                            <div class="mt-3">
                                <p class="text-muted mb-1 cifra">Error {{ estado }}</p>
                                <h3 class="text-uppercase">{{ contenido.titulo }}</h3>
                                <p class="text-muted mb-4">{{ contenido.texto }}</p>
                                <Link v-if="conSesion" :href="route('dashboard')" class="btn btn-success">
                                    <i class="mdi mdi-home me-1" />Regresar a la página principal
                                </Link>
                                <Link v-else :href="route('login')" class="btn btn-success">
                                    <i class="mdi mdi-login me-1" />Iniciar sesión
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
