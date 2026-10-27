<script setup>
import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import BaseModal from '@/Components/Ui/BaseModal.vue';
import BuscadorRemoto from '@/Components/Ui/BuscadorRemoto.vue';
import CampoError from '@/Components/Ui/CampoError.vue';
import { useNotificacion } from '@/Composables/useNotificacion';
import { http } from '@/Utils/http';

/**
 * «Eliminar Persona»: muestra cuántos datos tiene relacionados, permite migrarlos a otra persona y elegir entre
 * eliminación recuperable o definitiva (mismo flujo que `_frm-eliminar.blade.php`).
 */
const props = defineProps({
    modelValue: { type: Boolean, default: false },
    persona: { type: Object, default: null },
});

const emit = defineEmits(['update:modelValue']);

const { confirmar, errorDeRespuesta } = useNotificacion();

const abierto = computed({
    get: () => props.modelValue,
    set: (valor) => emit('update:modelValue', valor),
});

const resumen = ref(null);
const cargando = ref(false);
const enviando = ref(false);
const errores = ref({});
const personaMigrarId = ref(null);
const eliminarCompleto = ref(false);

const ETIQUETAS = {
    mandamientos: 'Mandamientos',
    registros_criminales: 'Registros criminales',
    faltas_contravenciones: 'Faltas y contravenciones',
    telefonos: 'Teléfonos',
    multimedia: 'Multimedia',
    vehiculos: 'Vehículos',
    inspeccion_tecnica: 'Inspección técnica',
};

watch(
    () => props.modelValue,
    async (valor) => {
        if (!valor || !props.persona) {
            return;
        }
        resumen.value = null;
        errores.value = {};
        personaMigrarId.value = null;
        eliminarCompleto.value = false;
        cargando.value = true;
        try {
            const { data } = await http.get(route('personas.showDeleteModal', props.persona.id));
            resumen.value = data.resumen;
        } catch (err) {
            errorDeRespuesta(err);
            abierto.value = false;
        } finally {
            cargando.value = false;
        }
    },
);

const textoPersona = (persona) => `${persona.nombres} ${persona.apellidos ?? ''} - CI: ${persona.ci ?? ''}`;

const confirmarEliminacion = async () => {
    const definitiva = eliminarCompleto.value;
    const ok = await confirmar({
        titulo: `¿Eliminar a ${props.persona.nombres} ${props.persona.apellidos ?? ''}?`,
        texto: definitiva
            ? 'Se eliminará de forma permanente junto con sus datos no migrados. No se puede deshacer.'
            : 'Quedará marcada como eliminada y se podrá recuperar.',
        confirmar: '¡Sí, eliminar!',
        peligro: true,
    });

    if (!ok) {
        return;
    }

    router.delete(route('personas.destroy', props.persona.id), {
        data: { persona_migrar_id: personaMigrarId.value, eliminar_completo: definitiva ? 1 : 0 },
        preserveScroll: true,
        preserveState: true,
        onStart: () => (enviando.value = true),
        onFinish: () => (enviando.value = false),
        onError: (err) => (errores.value = err),
        onSuccess: (pagina) => {
            if (!pagina.props.flash?.error) {
                abierto.value = false;
            }
        },
    });
};
</script>

<template>
    <BaseModal v-model="abierto" estatico>
        <template #titulo>
            <span class="text-danger"><i class="ri-alert-line align-middle me-2" />Eliminar Persona</span>
        </template>

        <div class="alert alert-warning mb-3">
            <i class="ri-information-line align-middle me-2" />
            <strong>Atención:</strong> Está a punto de eliminar a <strong>{{ persona?.nombres }} {{ persona?.apellidos }}</strong> del sistema.
        </div>

        <div class="mb-3">
            <label class="form-label"><i class="ri-shuffle-line align-middle me-2" />Migrar Datos Relacionados (Opcional)</label>
            <small class="text-muted d-block mb-2">
                Si esta persona tiene datos relacionados (mandamientos, registros criminales, etc.), puede migrarlos a otro
                registro existente antes de eliminar.
            </small>
            <BuscadorRemoto
                v-model="personaMigrarId"
                :url="route('personas.search')"
                :parametros="{ id: persona?.id }"
                placeholder="Selecciona una persona para migrar datos relacionados (opcional)"
                :mapear="(p) => ({ id: p.id, texto: textoPersona(p) })"
                :invalido="Boolean(errores.persona_migrar_id)"
            />
            <CampoError :mensaje="errores.persona_migrar_id" />
        </div>

        <div class="card bg-light mb-3">
            <div class="card-body">
                <h6 class="card-title mb-3"><i class="ri-file-list-line align-middle me-2" />Datos Relacionados</h6>
                <div v-if="cargando" class="text-muted small"><span class="spinner-border spinner-border-sm me-2" />Contando datos…</div>
                <dl v-else-if="resumen" class="row mb-0 small">
                    <template v-for="(cantidad, clave) in resumen" :key="clave">
                        <dt class="col-7 fw-medium">{{ ETIQUETAS[clave] ?? clave }}</dt>
                        <dd class="col-5 mb-1 text-end cifra">{{ cantidad }}</dd>
                    </template>
                </dl>
            </div>
        </div>

        <div class="form-check form-switch form-switch-danger form-switch-lg mb-1">
            <input id="chkEliminarCompleto" v-model="eliminarCompleto" type="checkbox" class="form-check-input">
            <label class="form-check-label" for="chkEliminarCompleto">
                <i class="ri-delete-bin-line align-middle me-2" /><strong>Eliminar completamente</strong> (no recuperable)
            </label>
        </div>
        <small class="text-danger d-block mb-3">
            Si marca esta opción, la persona se eliminará permanentemente de la base de datos.<br>
            Si deja sin marcar, se marcará como eliminada pero será recuperable.
        </small>

        <div class="alert alert-info mb-0">
            <strong>Nota:</strong> Si no migra los datos relacionados y elimina completamente, estos se eliminarán junto con la persona.
        </div>

        <template #pie="{ cerrar }">
            <button type="button" class="btn btn-secondary" @click="cerrar"><i class="ri-close-line align-middle me-1" /> Cancelar</button>
            <button type="button" class="btn btn-danger" :disabled="enviando || cargando" @click="confirmarEliminacion">
                <i class="align-middle me-1" :class="enviando ? 'ri-loader-4-line' : 'ri-delete-bin-fill'" />
                {{ enviando ? 'Procesando…' : 'Confirmar Eliminación' }}
            </button>
        </template>
    </BaseModal>
</template>
