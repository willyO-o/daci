<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Modal } from 'bootstrap';
import BotonAyuda from '@/Components/Ui/BotonAyuda.vue';
import { useTour } from '@/Composables/useTour';

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
    /** Pasos de la guía del formulario (`useTour`): muestra el ícono de ayuda junto al título. */
    guia: { type: [Array, Function], default: null },
});

const emit = defineEmits(['update:modelValue', 'abierto', 'cerrado']);

const elemento = ref(null);
// El contenido se monta al abrir y se desmonta cuando termina la animación de cierre (no antes: evita el parpadeo).
const montado = ref(props.modelValue);
let instancia = null;

const alAbrir = () => emit('abierto');
// Guía del formulario. Bootstrap atrapa el foco dentro del modal y los botones de la guía están fuera: mientras la
// guía está abierta se suspende esa trampa (si no, cada clic devolvería el foco al primer campo y el modal saltaría).
const { iniciar: iniciarGuia, cerrar: cerrarGuia } = useTour();
const abrirGuia = () => {
    instancia?._focustrap?.deactivate();
    iniciarGuia(typeof props.guia === 'function' ? props.guia() : props.guia, {
        alCerrar: () => {
            if (elemento.value?.classList.contains('show')) {
                instancia?._focustrap?.activate();
            }
        },
    });
};

const alCerrar = () => {
    cerrarGuia();
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
                        <BotonAyuda v-if="guia" compacto class="ms-auto me-3" @click="abrirGuia" />
                        <button type="button" class="btn-close" :class="{ 'ms-0': guia }" aria-label="Cerrar" @click="cerrar" />
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
