<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Modal } from 'bootstrap';

/**
 * Modal de la plantilla (Bootstrap 5) controlado con v-model. Conserva las animaciones y estilos de Velzon.
 *
 *   <BaseModal v-model="abierto" titulo="Nueva persona" tamano="xl" estatico>
 *       …contenido…
 *       <template #pie>…botones…</template>
 *   </BaseModal>
 */
const props = defineProps({
    modelValue: { type: Boolean, default: false },
    titulo: { type: String, default: '' },
    /** sm | lg | xl | fullscreen */
    tamano: { type: String, default: '' },
    centrado: { type: Boolean, default: false },
    /** No se cierra al hacer clic fuera ni con Escape (formularios con datos sin guardar). */
    estatico: { type: Boolean, default: false },
    desplazable: { type: Boolean, default: false },
    sinCabecera: { type: Boolean, default: false },
    cuerpoClase: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue', 'abierto', 'cerrado']);

const elemento = ref(null);
// El contenido se monta al abrir y se desmonta cuando termina la animación de cierre (no antes: evita el parpadeo).
const montado = ref(props.modelValue);
let instancia = null;

const alAbrir = () => emit('abierto');
const alCerrar = () => {
    montado.value = false;
    emit('update:modelValue', false);
    emit('cerrado');
};

onMounted(() => {
    instancia = new Modal(elemento.value, {
        backdrop: props.estatico ? 'static' : true,
        keyboard: !props.estatico,
        focus: true,
    });
    elemento.value.addEventListener('shown.bs.modal', alAbrir);
    elemento.value.addEventListener('hidden.bs.modal', alCerrar);

    if (props.modelValue) {
        instancia.show();
    }
});

watch(
    () => props.modelValue,
    (abierto) => {
        if (abierto) {
            montado.value = true;
            instancia?.show();
        } else {
            instancia?.hide();
        }
    },
);

onBeforeUnmount(() => {
    const estabaAbierto = elemento.value?.classList.contains('show');
    elemento.value?.removeEventListener('shown.bs.modal', alAbrir);
    elemento.value?.removeEventListener('hidden.bs.modal', alCerrar);
    instancia?.dispose();

    // Si se navega con el modal abierto, Bootstrap no llega a quitar el fondo ni a liberar el scroll del body.
    if (estabaAbierto && !document.querySelector('.modal.show')) {
        document.querySelectorAll('.modal-backdrop').forEach((fondo) => fondo.remove());
        document.body.classList.remove('modal-open');
        document.body.style.removeProperty('overflow');
        document.body.style.removeProperty('padding-right');
    }
});

const cerrar = () => instancia?.hide();

defineExpose({ cerrar });
</script>

<template>
    <Teleport to="body">
        <div ref="elemento" class="modal fade" tabindex="-1" aria-hidden="true" :aria-label="titulo">
            <div
                class="modal-dialog"
                :class="{
                    [`modal-${tamano}`]: tamano,
                    'modal-dialog-centered': centrado,
                    'modal-dialog-scrollable': desplazable,
                }"
            >
                <div class="modal-content">
                    <div v-if="!sinCabecera" class="modal-header">
                        <h5 class="modal-title">
                            <slot name="titulo">{{ titulo }}</slot>
                        </h5>
                        <button type="button" class="btn-close" aria-label="Cerrar" @click="cerrar" />
                    </div>
                    <div class="modal-body" :class="cuerpoClase">
                        <slot v-if="montado" />
                    </div>
                    <div v-if="$slots.pie" class="modal-footer">
                        <slot v-if="montado" name="pie" :cerrar="cerrar" />
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>
