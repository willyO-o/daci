<script setup>
import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import PageHeader from '@/Components/Layout/PageHeader.vue';
import BaseModal from '@/Components/Ui/BaseModal.vue';
import CampoError from '@/Components/Ui/CampoError.vue';
import { useNotificacion } from '@/Composables/useNotificacion';
import { usePermisos } from '@/Composables/usePermisos';
import { cifra } from '@/Utils/formato';

const props = defineProps({
    roles: { type: Array, required: true },
    /** @type {Record<string, { etiqueta: string, permisos: Record<string, string> }>} */
    modulos: { type: Object, required: true },
    totalPermisos: { type: Number, required: true },
});

const { puede } = usePermisos();
const { confirmar } = useNotificacion();
const gestiona = computed(() => puede('roles.gestionar'));

const modal = ref(false);
const editando = ref(null);

const form = useForm({ name: '', descripcion: '', permisos: [] });

const etiquetaPermiso = (slug) => {
    for (const modulo of Object.values(props.modulos)) {
        if (modulo.permisos[slug]) {
            return `${modulo.etiqueta}: ${modulo.permisos[slug].toLowerCase()}`;
        }
    }

    return slug;
};

const abrirNuevo = () => {
    editando.value = null;
    form.reset();
    form.clearErrors();
    modal.value = true;
};

const abrirEditar = (rol) => {
    editando.value = rol;
    form.clearErrors();
    form.name = rol.name;
    form.descripcion = rol.descripcion ?? '';
    form.permisos = [...rol.permisos];
    modal.value = true;
};

const permisosDe = (modulo) => Object.keys(props.modulos[modulo].permisos);

const estadoModulo = (modulo) => {
    const lista = permisosDe(modulo);
    const marcados = lista.filter((permiso) => form.permisos.includes(permiso)).length;

    return { todos: marcados === lista.length, alguno: marcados > 0 && marcados < lista.length, marcados, total: lista.length };
};

const alternarModulo = (modulo) => {
    const lista = permisosDe(modulo);
    form.permisos = estadoModulo(modulo).todos
        ? form.permisos.filter((permiso) => !lista.includes(permiso))
        : [...new Set([...form.permisos, ...lista])];
};

const marcarTodos = (marcar) => {
    form.permisos = marcar ? Object.values(props.modulos).flatMap((modulo) => Object.keys(modulo.permisos)) : [];
};

const guardar = () => {
    const opciones = { preserveScroll: true, onSuccess: () => (modal.value = false) };

    if (editando.value) {
        form.put(route('roles.update', editando.value.id), opciones);
    } else {
        form.post(route('roles.store'), opciones);
    }
};

const eliminar = async (rol) => {
    if (await confirmar({ titulo: `¿Eliminar el rol «${rol.name}»?`, texto: 'Esta acción no se puede deshacer.', confirmar: 'Sí, eliminar', peligro: true })) {
        router.delete(route('roles.destroy', rol.id), { preserveScroll: true });
    }
};
</script>

<template>
    <div>
        <PageHeader titulo="Roles y Permisos" :migas="[{ etiqueta: 'Roles y Permisos' }]">
            <template v-if="gestiona" #acciones>
                <button type="button" class="btn btn-primary" @click="abrirNuevo">
                    <i class="ri-add-line align-middle me-1" /> Nuevo Rol
                </button>
            </template>
        </PageHeader>

        <div class="row">
            <div v-for="rol in roles" :key="rol.id" class="col-xxl-4 col-lg-6">
                <div class="card card-height-100">
                    <div class="card-header d-flex align-items-start gap-2">
                        <div class="flex-grow-1">
                            <h5 class="card-title mb-1 text-capitalize">{{ rol.name.replaceAll('_', ' ') }}</h5>
                            <p class="text-muted mb-0 small">{{ rol.descripcion || 'Sin descripción' }}</p>
                        </div>
                        <span v-if="rol.es_base" class="badge bg-soft-secondary text-secondary flex-none">Rol base</span>
                    </div>
                    <div class="card-body">
                        <div class="d-flex gap-4 mb-3">
                            <div>
                                <p class="text-muted mb-0 small">Usuarios</p>
                                <h5 class="mb-0 cifra">{{ cifra(rol.usuarios) }}</h5>
                            </div>
                            <div>
                                <p class="text-muted mb-0 small">Permisos</p>
                                <h5 class="mb-0 cifra">
                                    {{ rol.es_superadmin ? 'Todos' : `${cifra(rol.permisos.length)} de ${cifra(totalPermisos)}` }}
                                </h5>
                            </div>
                        </div>

                        <p v-if="rol.es_superadmin" class="text-muted small mb-0">
                            Acceso total al sistema, incluidos los permisos que se agreguen en el futuro. No se edita.
                        </p>
                        <div v-else-if="rol.permisos.length" class="d-flex flex-wrap gap-1">
                            <span v-for="permiso in rol.permisos.slice(0, 8)" :key="permiso" class="badge bg-soft-primary text-primary fw-normal">
                                {{ etiquetaPermiso(permiso) }}
                            </span>
                            <span v-if="rol.permisos.length > 8" class="badge bg-light text-muted fw-normal">+{{ rol.permisos.length - 8 }} más</span>
                        </div>
                        <p v-else class="text-muted small mb-0">Este rol todavía no tiene permisos.</p>
                    </div>
                    <div v-if="gestiona && !rol.es_superadmin" class="card-footer d-flex justify-content-end gap-2">
                        <button v-if="!rol.es_base" type="button" class="btn btn-sm btn-soft-danger" @click="eliminar(rol)">
                            <i class="ri-delete-bin-line align-bottom me-1" /> Eliminar
                        </button>
                        <button type="button" class="btn btn-sm btn-soft-primary" @click="abrirEditar(rol)">
                            <i class="ri-shield-keyhole-line align-bottom me-1" /> Editar permisos
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <BaseModal v-model="modal" :titulo="editando ? `Editar rol: ${editando.name}` : 'Nuevo Rol'" tamano="xl" estatico desplazable>
            <form id="formRol" novalidate @submit.prevent="guardar">
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <label for="nombreRol" class="form-label">Nombre <span class="text-danger">*</span></label>
                        <input
                            id="nombreRol"
                            v-model="form.name"
                            type="text"
                            class="form-control"
                            :class="{ 'is-invalid': form.errors.name }"
                            :disabled="editando?.es_base"
                            placeholder="p. ej. analista_daci"
                        >
                        <small v-if="editando?.es_base" class="text-muted">Los roles base conservan su nombre.</small>
                        <small v-else class="text-muted">Se guarda en minúsculas y con guion bajo.</small>
                        <CampoError :mensaje="form.errors.name" />
                    </div>
                    <div class="col-md-8">
                        <label for="descripcionRol" class="form-label">Descripción</label>
                        <input id="descripcionRol" v-model="form.descripcion" type="text" class="form-control" :class="{ 'is-invalid': form.errors.descripcion }" placeholder="Para qué sirve este rol">
                        <CampoError :mensaje="form.errors.descripcion" />
                    </div>
                </div>

                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                    <h6 class="mb-0">Permisos <span class="text-muted fw-normal cifra">({{ form.permisos.length }} de {{ totalPermisos }})</span></h6>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-light" @click="marcarTodos(true)">Marcar todos</button>
                        <button type="button" class="btn btn-sm btn-light" @click="marcarTodos(false)">Quitar todos</button>
                    </div>
                </div>
                <CampoError :mensaje="form.errors.permisos" />

                <div class="row g-3">
                    <div v-for="(modulo, clave) in modulos" :key="clave" class="col-lg-4 col-md-6">
                        <div class="border rounded p-3 h-100">
                            <div class="form-check mb-2">
                                <input
                                    :id="`modulo-${clave}`"
                                    class="form-check-input"
                                    type="checkbox"
                                    :checked="estadoModulo(clave).todos"
                                    :indeterminate.prop="estadoModulo(clave).alguno"
                                    @change="alternarModulo(clave)"
                                >
                                <label class="form-check-label fw-semibold" :for="`modulo-${clave}`">
                                    {{ modulo.etiqueta }}
                                    <span class="text-muted fw-normal small cifra">{{ estadoModulo(clave).marcados }}/{{ estadoModulo(clave).total }}</span>
                                </label>
                            </div>
                            <div v-for="(etiqueta, permiso) in modulo.permisos" :key="permiso" class="form-check ms-3">
                                <input :id="`permiso-${permiso}`" v-model="form.permisos" class="form-check-input" type="checkbox" :value="permiso">
                                <label class="form-check-label" :for="`permiso-${permiso}`">{{ etiqueta }}</label>
                            </div>
                        </div>
                    </div>
                </div>
            </form>

            <template #pie="{ cerrar }">
                <button type="button" class="btn btn-light" @click="cerrar">Cancelar</button>
                <button type="submit" form="formRol" class="btn btn-primary" :disabled="form.processing">
                    <i class="align-middle me-1" :class="form.processing ? 'ri-loader-4-line' : 'ri-save-3-line'" />
                    {{ form.processing ? 'Guardando…' : 'Guardar' }}
                </button>
            </template>
        </BaseModal>
    </div>
</template>
