<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import CampoAcceso from '@/Components/Acceso/CampoAcceso.vue';
import TarjetaAcceso from '@/Components/Acceso/TarjetaAcceso.vue';

defineProps({
    status: { type: String, default: null },
});

const form = useForm({ email: '' });

const enviar = () => form.post(route('password.email'));
</script>

<template>
    <Head title="Recuperar contraseña" />

    <TarjetaAcceso texto="Escriba su correo y le enviaremos un enlace para restablecer la contraseña.">
        <p v-if="status" class="acceso-aviso acceso-aviso-exito" role="status">
            <i class="ri-mail-check-line" aria-hidden="true" />{{ status }}
        </p>

        <form class="acceso-formulario" novalidate @submit.prevent="enviar">
            <CampoAcceso
                id="email"
                v-model="form.email"
                tipo="email"
                icono="ri-mail-fill"
                rotulo="Correo electrónico"
                autocompletar="username"
                :error="form.errors.email"
                enfocar
            />

            <button class="acceso-boton" type="submit" :disabled="form.processing">
                <span v-if="form.processing" class="spinner-border spinner-border-sm" aria-hidden="true" />
                {{ form.processing ? 'Enviando…' : 'Enviar enlace' }}
                <i v-if="!form.processing" class="ri-mail-send-line" aria-hidden="true" />
            </button>
        </form>

        <p class="acceso-seguro">
            ¿La recordó?&nbsp;<Link :href="route('login')" class="acceso-enlace">Iniciar sesión</Link>
        </p>
    </TarjetaAcceso>
</template>
