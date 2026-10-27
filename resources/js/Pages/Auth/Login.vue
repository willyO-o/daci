<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import CampoAcceso from '@/Components/Acceso/CampoAcceso.vue';
import TarjetaAcceso from '@/Components/Acceso/TarjetaAcceso.vue';

defineProps({
    status: { type: String, default: null },
});

const page = usePage();

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const ingresar = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Iniciar sesión" />

    <TarjetaAcceso>
        <p v-if="status" class="acceso-aviso acceso-aviso-exito" role="status">
            <i class="ri-checkbox-circle-line" aria-hidden="true" />{{ status }}
        </p>
        <p v-if="page.props.flash?.message" class="acceso-aviso acceso-aviso-alerta" role="alert">
            <i class="ri-alert-line" aria-hidden="true" />{{ page.props.flash.message }}
        </p>

        <form class="acceso-formulario" novalidate @submit.prevent="ingresar">
            <CampoAcceso
                id="email"
                v-model="form.email"
                tipo="email"
                icono="ri-user-3-fill"
                rotulo="Correo electrónico"
                autocompletar="username"
                :error="form.errors.email"
                enfocar
            />
            <CampoAcceso
                id="password"
                v-model="form.password"
                tipo="password"
                icono="ri-lock-2-fill"
                rotulo="Contraseña"
                autocompletar="current-password"
                :error="form.errors.password"
            />

            <div class="acceso-opciones">
                <label class="acceso-recordar" for="remember">
                    <input id="remember" v-model="form.remember" type="checkbox">
                    Recordarme
                </label>
                <span class="acceso-ayuda">¿Olvidó su contraseña? Solicítela al administrador.</span>
            </div>

            <button class="acceso-boton" type="submit" :disabled="form.processing">
                <template v-if="form.processing">
                    <span class="spinner-border spinner-border-sm" aria-hidden="true" />
                    Ingresando…
                </template>
                <template v-else>
                    Ingresar <i class="ri-login-box-line" aria-hidden="true" />
                </template>
            </button>
        </form>

        <p class="acceso-seguro"><i class="ri-shield-keyhole-fill" aria-hidden="true" />Acceso seguro</p>
    </TarjetaAcceso>
</template>
