<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import CampoError from '@/Components/Ui/CampoError.vue';

const form = useForm({ password: '' });

const confirmar = () => {
    form.post(route('password.confirm'), {
        onFinish: () => form.reset(),
    });
};
</script>

<template>
    <Head title="Confirmar contraseña" />

    <div class="card mt-4">
        <div class="card-body p-4">
            <div class="text-center mt-2">
                <h5 class="text-primary">Confirme su contraseña</h5>
                <p class="text-muted">Es un área protegida. Confirme su contraseña para continuar.</p>
            </div>

            <div class="p-2">
                <form novalidate @submit.prevent="confirmar">
                    <div class="mb-3">
                        <label for="password" class="form-label">Contraseña</label>
                        <input id="password" v-model="form.password" type="password" class="form-control" :class="{ 'is-invalid': form.errors.password }" autocomplete="current-password" required autofocus>
                        <CampoError :mensaje="form.errors.password" />
                    </div>
                    <div class="mt-4">
                        <button class="btn btn-success w-100" type="submit" :disabled="form.processing">Confirmar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
