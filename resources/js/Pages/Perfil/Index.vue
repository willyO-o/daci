<script setup>
import { reactive } from 'vue';
import { useForm } from '@inertiajs/vue3';
import PageHeader from '@/Components/Layout/PageHeader.vue';
import CampoError from '@/Components/Ui/CampoError.vue';
import { iniciales } from '@/Utils/formato';

defineProps({
    usuario: { type: Object, required: true },
});

const visibles = reactive({ current_password: false, password: false, password_confirmation: false });

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const guardar = () => {
    form.post(route('perfil.password'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
};

const campos = [
    { nombre: 'current_password', etiqueta: 'Contraseña Actual', ayuda: 'Ingrese contraseña actual', autocompletar: 'current-password' },
    { nombre: 'password', etiqueta: 'Nueva Contraseña', ayuda: 'Ingrese nueva contraseña', autocompletar: 'new-password' },
    { nombre: 'password_confirmation', etiqueta: 'Confirmar Contraseña', ayuda: 'Confirme nueva contraseña', autocompletar: 'new-password' },
];

const colorRol = (rol) => ({ superadmin: 'danger', administrador: 'primary' })[rol] ?? 'warning';
</script>

<template>
    <div>
        <PageHeader titulo="Mi Perfil" :migas="[{ etiqueta: 'Mi Perfil' }]" />

        <div class="row">
            <div class="col-xxl-3">
                <div class="card">
                    <div class="card-body p-4">
                        <div class="text-center">
                            <div class="avatar-lg mx-auto mb-4">
                                <div class="avatar-title bg-soft-primary text-primary rounded-circle fs-24">{{ iniciales(usuario.name) }}</div>
                            </div>
                            <h5 class="fs-16 mb-1">{{ usuario.name }}</h5>
                            <span v-if="usuario.rol" class="badge text-capitalize" :class="`bg-${colorRol(usuario.rol)}`">{{ usuario.rol }}</span>
                            <span v-else class="badge bg-secondary">Sin Rol</span>
                        </div>
                        <div class="table-responsive mt-4">
                            <table class="table table-borderless mb-0">
                                <tbody>
                                    <tr>
                                        <th class="ps-0" scope="row">Nombre:</th>
                                        <td class="text-muted">{{ usuario.name }}</td>
                                    </tr>
                                    <tr>
                                        <th class="ps-0" scope="row">Email:</th>
                                        <td class="text-muted text-break">{{ usuario.email }}</td>
                                    </tr>
                                    <tr>
                                        <th class="ps-0" scope="row">Registro:</th>
                                        <td class="text-muted cifra">{{ usuario.creado ?? '—' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xxl-9">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><i class="ri-lock-password-line align-middle me-1" /> Cambiar Contraseña</h5>
                    </div>
                    <div class="card-body">
                        <form novalidate @submit.prevent="guardar">
                            <div class="row g-3">
                                <div v-for="campo in campos" :key="campo.nombre" class="col-lg-4">
                                    <label :for="campo.nombre" class="form-label">{{ campo.etiqueta }} <span class="text-danger">*</span></label>
                                    <div class="position-relative">
                                        <input
                                            :id="campo.nombre"
                                            v-model="form[campo.nombre]"
                                            :type="visibles[campo.nombre] ? 'text' : 'password'"
                                            class="form-control pe-5"
                                            :class="{ 'is-invalid': form.errors[campo.nombre] }"
                                            :placeholder="campo.ayuda"
                                            :autocomplete="campo.autocompletar"
                                        >
                                        <button
                                            class="btn btn-link position-absolute end-0 top-0 text-decoration-none text-muted"
                                            type="button"
                                            :aria-label="visibles[campo.nombre] ? 'Ocultar' : 'Mostrar'"
                                            @click="visibles[campo.nombre] = !visibles[campo.nombre]"
                                        >
                                            <i class="align-middle" :class="visibles[campo.nombre] ? 'ri-eye-off-fill' : 'ri-eye-fill'" />
                                        </button>
                                    </div>
                                    <CampoError :mensaje="form.errors[campo.nombre]" />
                                </div>
                                <div class="col-lg-12 text-end">
                                    <button type="submit" class="btn btn-primary" :disabled="form.processing">
                                        <i class="align-middle me-1" :class="form.processing ? 'ri-loader-4-line' : 'ri-save-3-line'" />
                                        {{ form.processing ? 'Guardando…' : 'Cambiar Contraseña' }}
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
