<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import CampoError from '@/Components/Ui/CampoError.vue';

defineProps({
    status: { type: String, default: null },
});

const form = useForm({ email: '' });

const enviar = () => form.post(route('password.email'));
</script>

<template>
    <Head title="Recuperar contraseña" />

    <div class="card mt-4">
        <div class="card-body p-4">
            <div class="text-center mt-2">
                <h5 class="text-primary">¿Olvidó su contraseña?</h5>
                <p class="text-muted">Le enviaremos un enlace para restablecerla.</p>
                <i class="ri-mail-send-line display-5 text-success" />
            </div>

            <div v-if="status" class="alert alert-success text-center mb-2 mx-2" role="status">{{ status }}</div>
            <div v-else class="alert alert-warning text-center mb-2 mx-2" role="alert">
                Ingrese su correo y le llegarán las instrucciones.
            </div>

            <div class="p-2">
                <form novalidate @submit.prevent="enviar">
                    <div class="mb-4">
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

                    <div class="text-center mt-4">
                        <button class="btn btn-success w-100" type="submit" :disabled="form.processing">
                            {{ form.processing ? 'Enviando…' : 'Enviar enlace' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="mt-4 text-center">
        <p class="mb-0">
            ¿La recordó?
            <Link :href="route('login')" class="fw-semibold text-primary text-decoration-underline">Iniciar sesión</Link>
        </p>
    </div>
</template>
