<script setup>
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import BuscadorRemoto from '@/Components/Ui/BuscadorRemoto.vue';
import CampoError from '@/Components/Ui/CampoError.vue';

/**
 * Formulario de IMEI (alta y edición); el IMEI puede quedar vinculado a varios teléfonos. Lo usan el modal del
 * listado y la página «Registrar IMEI». El botón de guardar va fuera (`<button type="submit" :form="idFormulario">`).
 * Tras registrar, queda limpio para el siguiente.
 */
const props = defineProps({
    /** IMEI a editar (fila del listado, con `telefonos`); `null` para registrar. */
    imei: { type: Object, default: null },
    idFormulario: { type: String, default: 'formImei' },
});

const emit = defineEmits(['guardado']);

const telefonosElegidos = ref([]);
const telefonoPorAgregar = ref(null);
const form = useForm({ imei: '', caracteristicas: '', telefono_id: [] });

const editando = computed(() => Boolean(props.imei?.id));
const textoTelefono = (telefono) =>
    `${telefono.numero_celular}${telefono.persona ? ` - ${telefono.persona.nombres} ${telefono.persona.apellidos ?? ''}` : ''}`;

const cargar = () => {
    const i = props.imei;
    telefonosElegidos.value = editando.value ? (i.telefonos ?? []).map((telefono) => ({ id: telefono.id, texto: textoTelefono(telefono) })) : [];
    form.defaults(
        editando.value
            ? { imei: i.imei, caracteristicas: i.caracteristicas ?? '', telefono_id: telefonosElegidos.value.map((t) => t.id) }
            : { imei: '', caracteristicas: '', telefono_id: [] },
    );
    form.reset();
    form.clearErrors();
    telefonoPorAgregar.value = null;
};

watch(() => props.imei, cargar, { immediate: true });

const agregarTelefono = (telefono) => {
    if (telefono && !telefonosElegidos.value.some((t) => t.id === telefono.id)) {
        telefonosElegidos.value.push({ id: telefono.id, texto: telefono.text });
    }
    telefonoPorAgregar.value = null;
};
const quitarTelefono = (id) => {
    telefonosElegidos.value = telefonosElegidos.value.filter((t) => t.id !== id);
};

const guardar = () => {
    form.telefono_id = telefonosElegidos.value.map((t) => t.id);
    const opciones = {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            if (!editando.value) {
                cargar();
            }
            emit('guardado');
        },
    };
    if (editando.value) {
        form.put(route('imeis.update', props.imei.id), opciones);
    } else {
        form.post(route('imeis.store'), opciones);
    }
};

defineExpose({ guardar, procesando: computed(() => form.processing) });
</script>

<template>
    <form :id="idFormulario" novalidate @submit.prevent="guardar">
        <div class="mb-3">
            <label for="imei" class="form-label">IMEI *</label>
            <input id="imei" v-model="form.imei" v-sin-espacios type="text" class="form-control" :class="{ 'is-invalid': form.errors.imei }" placeholder="Ingresa el IMEI">
            <CampoError :mensaje="form.errors.imei" />
        </div>
        <div class="mb-3">
            <label for="caracteristicas" class="form-label">Características</label>
            <textarea id="caracteristicas" v-model="form.caracteristicas" class="form-control" :class="{ 'is-invalid': form.errors.caracteristicas }" rows="3" placeholder="Características del IMEI" />
            <CampoError :mensaje="form.errors.caracteristicas" />
        </div>
        <div>
            <label class="form-label">Vincular Teléfonos</label>
            <BuscadorRemoto
                v-model="telefonoPorAgregar"
                :url="route('imeis.telefonos-search')"
                :minimo="3"
                placeholder="Número o nombre (mínimo 3 caracteres)"
                :permitir-limpiar="false"
                @seleccionado="agregarTelefono"
            />
            <div class="d-flex flex-wrap gap-1 mt-2">
                <span v-for="telefono in telefonosElegidos" :key="telefono.id" class="badge bg-soft-primary text-primary d-inline-flex align-items-center gap-1 py-1">
                    {{ telefono.texto }}
                    <button type="button" class="btn-close btn-close-sm" style="font-size: 0.55rem" :aria-label="`Quitar ${telefono.texto}`" @click="quitarTelefono(telefono.id)" />
                </span>
                <small v-if="!telefonosElegidos.length" class="text-muted">-- Sin vincular --</small>
            </div>
            <CampoError :mensaje="form.errors.telefono_id || form.errors['telefono_id.0']" />
        </div>
    </form>
</template>
