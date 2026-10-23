<script setup>
import { computed, reactive, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import PageHeader from '@/Components/Layout/PageHeader.vue';
import BaseModal from '@/Components/Ui/BaseModal.vue';
import CampoError from '@/Components/Ui/CampoError.vue';
import EstadoVacio from '@/Components/Ui/EstadoVacio.vue';
import FilasCargando from '@/Components/Ui/FilasCargando.vue';
import Paginacion from '@/Components/Ui/Paginacion.vue';
import { useListado } from '@/Composables/useListado';
import { useNotificacion } from '@/Composables/useNotificacion';
import { usePermisos } from '@/Composables/usePermisos';
import { fecha } from '@/Utils/formato';

const props = defineProps({
    usuarios: { type: Object, required: true },
    roles: { type: Array, required: true },
    filtros: { type: Object, default: () => ({}) },
});

const { puede } = usePermisos();
const { confirmar } = useNotificacion();
const { filtros, cargando, irAPagina } = useListado(
    'usuarios.index',
    { search: props.filtros.search ?? '', role_id: props.filtros.role_id ?? '' },
    ['usuarios'],
);

const coloresRol = { superadmin: 'danger', administrador: 'primary' };
const colorRol = (nombre) => coloresRol[nombre] ?? 'secondary';
const numero = (indice) => (props.usuarios.from ?? 1) + indice;

const modal = ref(false);
const editando = ref(null);
const verClave = reactive({ password: false, confirmacion: false });

const form = useForm({
    name: '',
    email: '',
    role_id: '',
    password: '',
    password_confirmation: '',
});

const tituloModal = computed(() => (editando.value ? 'Editar Usuario' : 'Registrar Usuario'));

const abrirNuevo = () => {
    editando.value = null;
    form.reset();
    form.clearErrors();
    modal.value = true;
};

const abrirEditar = (usuario) => {
    editando.value = usuario;
    form.clearErrors();
    form.name = usuario.name;
    form.email = usuario.email;
    form.role_id = usuario.rol?.id ?? '';
    form.password = '';
    form.password_confirmation = '';
    modal.value = true;
};

const guardar = () => {
    const opciones = { preserveScroll: true, onSuccess: () => (modal.value = false) };

    if (editando.value) {
        form.put(route('usuarios.update', editando.value.id), opciones);
    } else {
        form.post(route('usuarios.store'), opciones);
    }
};

const cambiarEstado = async (usuario) => {
    const desactivar = usuario.is_active;
    const ok = await confirmar({
        titulo: desactivar ? `¿Desactivar a ${usuario.name}?` : `¿Activar a ${usuario.name}?`,
        texto: desactivar ? 'No podrá iniciar sesión hasta que se vuelva a activar.' : 'Podrá volver a iniciar sesión.',
        confirmar: desactivar ? 'Sí, desactivar' : 'Sí, activar',
        peligro: desactivar,
    });

    if (ok) {
        router.post(route('usuarios.toggle-status', usuario.id), {}, { preserveScroll: true });
    }
};

const eliminar = async (usuario) => {
    const ok = await confirmar({
        titulo: `¿Eliminar a ${usuario.name}?`,
        texto: 'Está a punto de eliminar este usuario. Esta acción no se puede deshacer.',
        confirmar: 'Sí, eliminar',
        peligro: true,
    });

    if (ok) {
        router.delete(route('usuarios.destroy', usuario.id), { preserveScroll: true });
    }
};
</script>

<template>
    <div>
        <PageHeader titulo="Gestión de Usuarios" :migas="[{ etiqueta: 'Usuarios' }]">
            <template v-if="puede('usuarios.crear')" #acciones>
                <button type="button" class="btn btn-primary" @click="abrirNuevo">
                    <i class="ri-add-line align-middle me-1" /> Nuevo Usuario
                </button>
            </template>
        </PageHeader>

        <div class="card">
            <div class="card-header">
                <div class="row g-2">
                    <div class="col-md-4">
                        <div class="input-group">
                            <span class="input-group-text bg-primary text-white border-primary"><i class="ri-search-line" /></span>
                            <input v-model="filtros.search" type="search" class="form-control" placeholder="Buscar usuario..." aria-label="Buscar usuario">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select v-model="filtros.role_id" class="form-select" aria-label="Filtrar por rol">
                            <option value="">Todos los Roles</option>
                            <option v-for="rol in roles" :key="rol.id" :value="String(rol.id)" class="text-capitalize">{{ rol.name }}</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th scope="col">#</th>
                                <th scope="col">Nombre</th>
                                <th scope="col">Correo Electrónico</th>
                                <th scope="col">Rol</th>
                                <th scope="col">Estado</th>
                                <th scope="col">Fecha de Registro</th>
                                <th scope="col" class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <FilasCargando v-if="cargando" :columnas="7" />
                            <template v-else>
                                <tr v-for="(usuario, indice) in usuarios.data" :key="usuario.id">
                                    <td class="text-muted">{{ numero(indice) }}</td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-xs me-2 flex-shrink-0">
                                                <div class="avatar-title bg-soft-primary text-primary rounded-circle fs-13">
                                                    {{ usuario.name?.charAt(0).toUpperCase() ?? 'U' }}
                                                </div>
                                            </div>
                                            <span class="fw-medium">{{ usuario.name }}</span>
                                        </div>
                                    </td>
                                    <td>{{ usuario.email }}</td>
                                    <td>
                                        <span v-if="usuario.rol" class="badge text-capitalize" :class="`bg-${colorRol(usuario.rol.name)}`">{{ usuario.rol.name }}</span>
                                        <span v-else class="badge bg-secondary">Sin Rol</span>
                                    </td>
                                    <td>
                                        <span class="badge" :class="usuario.is_active ? 'bg-soft-success text-success' : 'bg-soft-danger text-danger'">
                                            {{ usuario.is_active ? 'Activo' : 'Inactivo' }}
                                        </span>
                                    </td>
                                    <td class="cifra">{{ fecha(usuario.created_at) }}</td>
                                    <td class="text-center">
                                        <div class="d-flex gap-2 justify-content-center">
                                            <button
                                                v-if="puede('usuarios.activar')"
                                                type="button"
                                                class="btn btn-sm"
                                                :class="usuario.is_active ? 'btn-soft-warning' : 'btn-soft-success'"
                                                :title="usuario.is_active ? 'Desactivar' : 'Activar'"
                                                :aria-label="usuario.is_active ? `Desactivar a ${usuario.name}` : `Activar a ${usuario.name}`"
                                                @click="cambiarEstado(usuario)"
                                            >
                                                <i class="align-bottom" :class="usuario.is_active ? 'ri-forbid-2-line' : 'ri-check-double-line'" />
                                            </button>
                                            <button v-if="puede('usuarios.editar')" type="button" class="btn btn-sm btn-soft-info" title="Editar" :aria-label="`Editar a ${usuario.name}`" @click="abrirEditar(usuario)">
                                                <i class="ri-pencil-fill align-bottom" />
                                            </button>
                                            <button v-if="puede('usuarios.eliminar')" type="button" class="btn btn-sm btn-soft-danger" title="Eliminar" :aria-label="`Eliminar a ${usuario.name}`" @click="eliminar(usuario)">
                                                <i class="ri-delete-bin-fill align-bottom" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <EstadoVacio
                    v-if="!cargando && !usuarios.data.length"
                    titulo="No se encontraron usuarios"
                    :texto="filtros.search || filtros.role_id ? 'Pruebe con otra búsqueda o quite el filtro de rol.' : ''"
                />

                <Paginacion :paginado="usuarios" entidad="usuarios" :deshabilitado="cargando" @ir="irAPagina" />
            </div>
        </div>

        <BaseModal v-model="modal" :titulo="tituloModal" centrado estatico>
            <form id="formUsuario" novalidate @submit.prevent="guardar">
                <div class="row g-3">
                    <div class="col-md-12">
                        <label for="name" class="form-label">Nombre Completo <span class="text-danger">*</span></label>
                        <input id="name" v-model="form.name" type="text" class="form-control" :class="{ 'is-invalid': form.errors.name }" placeholder="Ingrese nombre completo">
                        <CampoError :mensaje="form.errors.name" />
                    </div>
                    <div class="col-md-12">
                        <label for="email" class="form-label">Correo Electrónico <span class="text-danger">*</span></label>
                        <input id="email" v-model="form.email" type="email" class="form-control" :class="{ 'is-invalid': form.errors.email }" placeholder="Ingrese correo electrónico" autocomplete="off">
                        <CampoError :mensaje="form.errors.email" />
                    </div>
                    <div class="col-md-12">
                        <label for="role_id" class="form-label">Rol <span class="text-danger">*</span></label>
                        <select id="role_id" v-model="form.role_id" class="form-select text-capitalize" :class="{ 'is-invalid': form.errors.role_id }">
                            <option value="">Seleccione un rol</option>
                            <option v-for="rol in roles" :key="rol.id" :value="rol.id">{{ rol.name }}</option>
                        </select>
                        <CampoError :mensaje="form.errors.role_id" />
                    </div>
                    <div class="col-md-6">
                        <label for="password" class="form-label">Contraseña <span v-if="!editando" class="text-danger">*</span></label>
                        <div class="position-relative">
                            <input
                                id="password"
                                v-model="form.password"
                                :type="verClave.password ? 'text' : 'password'"
                                class="form-control pe-5"
                                :class="{ 'is-invalid': form.errors.password }"
                                :placeholder="editando ? 'Dejar vacío para mantener' : 'Ingrese contraseña'"
                                autocomplete="new-password"
                            >
                            <button type="button" class="btn btn-link position-absolute end-0 top-0 text-decoration-none text-muted" aria-label="Mostrar u ocultar contraseña" @click="verClave.password = !verClave.password">
                                <i class="align-middle" :class="verClave.password ? 'ri-eye-off-fill' : 'ri-eye-fill'" />
                            </button>
                        </div>
                        <CampoError :mensaje="form.errors.password" />
                    </div>
                    <div class="col-md-6">
                        <label for="password_confirmation" class="form-label">Confirmar Contraseña <span v-if="!editando" class="text-danger">*</span></label>
                        <div class="position-relative">
                            <input
                                id="password_confirmation"
                                v-model="form.password_confirmation"
                                :type="verClave.confirmacion ? 'text' : 'password'"
                                class="form-control pe-5"
                                placeholder="Confirme contraseña"
                                autocomplete="new-password"
                            >
                            <button type="button" class="btn btn-link position-absolute end-0 top-0 text-decoration-none text-muted" aria-label="Mostrar u ocultar confirmación" @click="verClave.confirmacion = !verClave.confirmacion">
                                <i class="align-middle" :class="verClave.confirmacion ? 'ri-eye-off-fill' : 'ri-eye-fill'" />
                            </button>
                        </div>
                    </div>
                </div>
            </form>

            <template #pie="{ cerrar }">
                <button type="button" class="btn btn-light" @click="cerrar">Cancelar</button>
                <button type="submit" form="formUsuario" class="btn btn-primary" :disabled="form.processing">
                    <i class="align-middle me-1" :class="form.processing ? 'ri-loader-4-line' : 'ri-save-3-line'" />
                    {{ form.processing ? 'Guardando…' : 'Guardar' }}
                </button>
            </template>
        </BaseModal>
    </div>
</template>
