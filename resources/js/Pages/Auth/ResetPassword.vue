<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import CampoAcceso from '@/Components/Acceso/CampoAcceso.vue';
import TarjetaAcceso from '@/Components/Acceso/TarjetaAcceso.vue';

const props = defineProps({
    email: { type: String, required: true },
    token: { type: String, required: true },
});

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const guardar = () => {
    form.post(route('password.store'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <Head title="Restablecer contraseña" />

    <TarjetaAcceso texto="Elija una contraseña nueva, distinta de la anterior.">
        <form class="acceso-formulario" novalidate @submit.prevent="guardar">
            <CampoAcceso
                id="email"
                v-model="form.email"
                tipo="email"
                icono="ri-user-3-fill"
                rotulo="Correo electrónico"
                autocompletar="username"
                :error="form.errors.email"
            />
            <CampoAcceso
                id="password"
                v-model="form.password"
                tipo="password"
                icono="ri-lock-2-fill"
                rotulo="Contraseña nueva"
                autocompletar="new-password"
                :error="form.errors.password"
                enfocar
            />
            <CampoAcceso
                id="password_confirmation"
                v-model="form.password_confirmation"
                tipo="password"
                icono="ri-lock-password-fill"
                rotulo="Confirmar contraseña"
                autocompletar="new-password"
                :error="form.errors.password_confirmation"
            />

            <button class="acceso-boton" type="submit" :disabled="form.processing">
                <span v-if="form.processing" class="spinner-border spinner-border-sm" aria-hidden="true" />
                {{ form.processing ? 'Guardando…' : 'Restablecer contraseña' }}
            </button>
        </form>
    </TarjetaAcceso>
</template>
