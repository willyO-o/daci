<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import CampoAcceso from '@/Components/Acceso/CampoAcceso.vue';
import TarjetaAcceso from '@/Components/Acceso/TarjetaAcceso.vue';

const form = useForm({ password: '' });

const confirmar = () => {
    form.post(route('password.confirm'), {
        onFinish: () => form.reset(),
    });
};
</script>

<template>
    <Head title="Confirmar contraseña" />

    <TarjetaAcceso texto="Es un área protegida. Confirme su contraseña para continuar.">
        <form class="acceso-formulario" novalidate @submit.prevent="confirmar">
            <CampoAcceso
                id="password"
                v-model="form.password"
                tipo="password"
                icono="ri-lock-2-fill"
                rotulo="Contraseña"
                autocompletar="current-password"
                :error="form.errors.password"
                enfocar
            />

            <button class="acceso-boton" type="submit" :disabled="form.processing">
                <span v-if="form.processing" class="spinner-border spinner-border-sm" aria-hidden="true" />
                {{ form.processing ? 'Confirmando…' : 'Confirmar' }}
            </button>
        </form>
    </TarjetaAcceso>
</template>
