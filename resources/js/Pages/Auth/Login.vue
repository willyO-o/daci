<script setup>
import { ref } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import CampoError from '@/Components/Ui/CampoError.vue';

defineProps({
    canResetPassword: { type: Boolean, default: false },
    status: { type: String, default: null },
});

const page = usePage();
const verClave = ref(false);

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

    <div class="card mt-4">
        <div class="card-body p-4">
            <div class="text-center mt-2">
                <h5 class="text-primary">¡Bienvenido!</h5>
                <p class="text-muted">Inicia sesión para continuar</p>
            </div>

            <div v-if="status" class="alert alert-success" role="status">{{ status }}</div>
            <div v-if="page.props.flash?.message" class="alert alert-warning" role="alert">{{ page.props.flash.message }}</div>

            <div class="p-2 mt-4">
                <form novalidate @submit.prevent="ingresar">
                    <div class="mb-3">
                        <label for="email" class="form-label">Correo Electrónico</label>
                        <input
                            id="email"
                            v-model="form.email"
                            type="email"
                            class="form-control"
                            :class="{ 'is-invalid': form.errors.email }"
                            placeholder="Ingrese su correo electrónico"
                            autocomplete="username"
                            required
                            autofocus
                        >
                        <CampoError :mensaje="form.errors.email" />
                    </div>

                    <div class="mb-3">
                        <div v-if="canResetPassword" class="float-end">
                            <Link :href="route('password.request')" class="text-muted">¿Olvidó su contraseña?</Link>
                        </div>
                        <label class="form-label" for="password">Contraseña</label>
                        <div class="position-relative auth-pass-inputgroup">
                            <input
                                id="password"
                                v-model="form.password"
                                :type="verClave ? 'text' : 'password'"
                                class="form-control pe-5 password-input"
                                :class="{ 'is-invalid': form.errors.password }"
                                placeholder="Ingrese su contraseña"
                                autocomplete="current-password"
                                required
                            >
                            <button
                                class="btn btn-link position-absolute end-0 top-0 text-decoration-none text-muted shadow-none password-addon"
                                type="button"
                                :aria-label="verClave ? 'Ocultar contraseña' : 'Mostrar contraseña'"
                                @click="verClave = !verClave"
                            >
                                <i class="align-middle" :class="verClave ? 'ri-eye-off-fill' : 'ri-eye-fill'" />
                            </button>
                        </div>
                        <CampoError :mensaje="form.errors.password" />
                    </div>

                    <div class="form-check">
                        <input id="remember" v-model="form.remember" class="form-check-input" type="checkbox">
                        <label class="form-check-label" for="remember">Recordarme</label>
                    </div>

                    <div class="mt-4">
                        <button class="btn btn-success w-100" type="submit" :disabled="form.processing">
                            <span v-if="form.processing" class="spinner-border spinner-border-sm me-1" role="status" />
                            {{ form.processing ? 'Ingresando…' : 'Iniciar Sesión' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
