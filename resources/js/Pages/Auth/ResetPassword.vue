<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import CampoError from '@/Components/Ui/CampoError.vue';

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

    <div class="card mt-4">
        <div class="card-body p-4">
            <div class="text-center mt-2">
                <h5 class="text-primary">Nueva contraseña</h5>
                <p class="text-muted">Debe ser distinta de la anterior.</p>
            </div>

            <div class="p-2">
                <form novalidate @submit.prevent="guardar">
                    <div class="mb-3">
                        <label for="email" class="form-label">Correo Electrónico</label>
                        <input id="email" v-model="form.email" type="email" class="form-control" :class="{ 'is-invalid': form.errors.email }" autocomplete="username" required>
                        <CampoError :mensaje="form.errors.email" />
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Contraseña</label>
                        <input id="password" v-model="form.password" type="password" class="form-control" :class="{ 'is-invalid': form.errors.password }" autocomplete="new-password" required autofocus>
                        <CampoError :mensaje="form.errors.password" />
                    </div>
                    <div class="mb-3">
                        <label for="password_confirmation" class="form-label">Confirmar contraseña</label>
                        <input id="password_confirmation" v-model="form.password_confirmation" type="password" class="form-control" :class="{ 'is-invalid': form.errors.password_confirmation }" autocomplete="new-password" required>
                        <CampoError :mensaje="form.errors.password_confirmation" />
                    </div>
                    <div class="mt-4">
                        <button class="btn btn-success w-100" type="submit" :disabled="form.processing">
                            {{ form.processing ? 'Guardando…' : 'Restablecer contraseña' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
