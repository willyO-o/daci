<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import PageHeader from '@/Components/Layout/PageHeader.vue';
import BotonAyuda from '@/Components/Ui/BotonAyuda.vue';
import CampoError from '@/Components/Ui/CampoError.vue';
import HojaFicha from '@/Components/RegistroCriminal/HojaFicha.vue';
import { useNotificacion } from '@/Composables/useNotificacion';
import { useTour } from '@/Composables/useTour';
import { fechaHora } from '@/Utils/formato';

/**
 * «Plantilla de la Ficha»: edita el texto base con el que empieza cada ficha de registro (Registro Criminal y Faltas
 * y Contravenciones, cada una con su plantilla). A la derecha, la hoja con datos de ejemplo se actualiza mientras se
 * escribe. Las variables {…} se insertan con un clic donde está el cursor; una que no existe se avisa aquí y el
 * servidor la rechaza.
 */
const props = defineProps({
    /** Módulos que el usuario puede editar: { clave, titulo, campos, estandar, existe, actualizado }. */
    modulos: { type: Array, required: true },
    /** Bloques: { etiqueta, ayuda, maximo, variables: { nombre: descripción } }. */
    definicion: { type: Object, required: true },
});

const { confirmar } = useNotificacion();

const activo = ref(props.modulos[0]?.clave ?? null);
const modulo = computed(() => props.modulos.find((m) => m.clave === activo.value));

// Un formulario por módulo: al cambiar de pestaña no se pierde lo escrito.
const formularios = reactive(Object.fromEntries(props.modulos.map((m) => [m.clave, useForm({ ...m.campos })])));
const form = computed(() => formularios[activo.value]);
const hayCambios = computed(() => props.modulos.some((m) => formularios[m.clave].isDirty));

const CAMPOS = Object.keys(props.definicion);
const enFoco = ref(null);
const areas = {};

// ---------- Variables ----------
// Texto «{nombre}» (en la plantilla de Vue no se puede escribir `}}` dentro de una expresión).
const marca = (nombre) => `{${nombre}}`;

const desconocidas = (campo) => {
    const permitidas = Object.keys(props.definicion[campo].variables);
    const encontradas = [...(form.value[campo] ?? '').matchAll(/\{([^{}]*)\}/g)].map((m) => m[1]);

    return [...new Set(encontradas.filter((nombre) => !permitidas.includes(nombre)))];
};

const insertar = async (campo, variable) => {
    const area = areas[campo];
    const texto = form.value[campo] ?? '';
    const inicio = area?.selectionStart ?? texto.length;
    const fin = area?.selectionEnd ?? texto.length;
    const insertada = marca(variable);
    form.value[campo] = texto.slice(0, inicio) + insertada + texto.slice(fin);
    await nextTick();
    area?.focus();
    area?.setSelectionRange(inicio + insertada.length, inicio + insertada.length);
};

// ---------- Vista previa con datos de ejemplo ----------
const ahora = new Date();
const dos = (n) => String(n).padStart(2, '0');
const fechaLarga = new Intl.DateTimeFormat('es-BO', { weekday: 'long', day: '2-digit', month: 'long', year: 'numeric' }).format(ahora).replace(',', '');
const hora12 = `${dos(((ahora.getHours() + 11) % 12) + 1)}:${dos(ahora.getMinutes())} ${ahora.getHours() < 12 ? 'am' : 'pm'}`;
const EJEMPLO = {
    fecha_hora_actual: `${fechaLarga}, a horas ${hora12}`,
    nombre_persona: 'JUAN PÉREZ MAMANI',
    ci_persona: '1234567',
    division: 'DIVISIÓN HOMICIDIOS',
    fecha_aprehension: `${dos(ahora.getDate())}/${dos(ahora.getMonth() + 1)}/${ahora.getFullYear()}`,
    delito: 'ROBO AGRAVADO',
};
const reemplazar = (texto) => (texto ?? '').replace(/\{(\w+)\}/g, (completo, nombre) => EJEMPLO[nombre] ?? completo);

const vistaPrevia = computed(() => ({
    caso_cud: 'CASO CUD - ...............',
    introduccion: reemplazar(form.value.introduccion),
    requerimiento: reemplazar(form.value.requerimiento),
    persona: `•  ${reemplazar(form.value.persona)}`,
    resultado_busqueda: reemplazar(form.value.resultado_busqueda),
    nota_certificacion: reemplazar(form.value.nota_certificacion),
    nota_general: reemplazar(form.value.nota_general),
    fecha_literal: `La Paz, ${new Intl.DateTimeFormat('es-BO', { day: '2-digit', month: 'long', year: 'numeric' }).format(ahora)}`,
}));
const numeroEjemplo = `001/${ahora.getFullYear()}`;

// ---------- Acciones ----------
const usarEstandar = async () => {
    const ok = await confirmar({
        titulo: '¿Usar el texto estándar?',
        texto: 'Los bloques vuelven al texto original del sistema. No se guarda hasta que pulse «Guardar».',
        confirmar: 'Sí, usar el estándar',
    });
    if (ok) {
        Object.assign(form.value, { ...modulo.value.estandar });
    }
};

const descartar = () => {
    form.value.reset();
    form.value.clearErrors();
};

const guardar = () => {
    const actual = form.value;
    actual.put(route('plantilla-ficha.update', activo.value), {
        preserveScroll: true,
        // Lo guardado pasa a ser el punto de partida: el formulario queda «sin cambios».
        onSuccess: () => actual.defaults(),
    });
};

// ---------- Cambios sin guardar ----------
const alSalirDelNavegador = (evento) => {
    if (hayCambios.value) {
        evento.preventDefault();
        evento.returnValue = '';
    }
};
let quitarAviso = null;
onMounted(() => {
    window.addEventListener('beforeunload', alSalirDelNavegador);
    quitarAviso = router.on('before', (evento) => {
        // El propio «Guardar» (PUT) no pregunta; sí salir a otra pantalla con cambios pendientes.
        if (evento.detail.visit.method === 'get' && hayCambios.value && !window.confirm('Hay cambios sin guardar en la plantilla. ¿Salir sin guardar?')) {
            evento.preventDefault();
        }
    });
});
onBeforeUnmount(() => {
    window.removeEventListener('beforeunload', alSalirDelNavegador);
    quitarAviso?.();
});

// ---------- Guía ----------
const { iniciar: iniciarGuia } = useTour();
const abrirGuia = () =>
    iniciarGuia([
        {
            titulo: 'Plantilla de la Ficha',
            texto: 'Aquí se cambia el texto con el que empieza cada ficha de registro. Cada ficha se puede corregir igual antes de imprimirla.',
        },
        { elemento: '[data-tour="p-pestanas"]', titulo: 'Módulo', texto: 'Registro Criminal y Faltas y Contravenciones tienen cada uno su plantilla.' },
        {
            elemento: '[data-tour="p-bloque"]',
            titulo: 'Bloques de la ficha',
            texto: 'Cada cuadro es una parte de la hoja. Al escribir en uno, su parte se resalta en la vista previa.',
        },
        {
            elemento: '[data-tour="p-variables"]',
            titulo: 'Variables',
            texto: 'Se reemplazan por los datos de cada registro al generar la ficha. Haga clic en una para insertarla donde está el cursor.',
        },
        { elemento: '[data-tour="p-vista"]', titulo: 'Vista previa', texto: 'Así quedará la hoja, con datos de ejemplo. Se actualiza mientras escribe.', lado: 'left' },
        { elemento: '[data-tour="p-acciones"]', titulo: 'Guardar', texto: '«Usar texto estándar» vuelve al texto original y «Descartar cambios» deshace lo no guardado. Nada cambia hasta pulsar «Guardar».', lado: 'top' },
    ]);
</script>

<template>
    <div>
        <PageHeader titulo="Plantilla de la Ficha" :migas="[{ etiqueta: 'Plantilla de la Ficha' }]">
            <template #acciones>
                <BotonAyuda @click="abrirGuia" />
            </template>
        </PageHeader>

        <div v-if="!modulos.length" class="alert alert-warning">No tiene permiso para editar ninguna plantilla.</div>

        <template v-else>
            <ul v-if="modulos.length > 1" class="nav nav-tabs nav-tabs-custom nav-success mb-3" role="tablist" data-tour="p-pestanas">
                <li v-for="m in modulos" :key="m.clave" class="nav-item" role="presentation">
                    <button type="button" class="nav-link" :class="{ active: activo === m.clave }" role="tab" :aria-selected="activo === m.clave" @click="activo = m.clave">
                        {{ m.titulo }}
                        <span v-if="formularios[m.clave].isDirty" class="badge bg-soft-warning text-warning ms-1" title="Cambios sin guardar">sin guardar</span>
                    </button>
                </li>
            </ul>

            <div class="row g-3">
                <div class="col-xl-6">
                    <form class="card mb-0" novalidate @submit.prevent="guardar">
                        <div class="card-header d-flex flex-wrap align-items-center gap-2">
                            <h5 class="card-title mb-0 flex-grow-1">Ficha de {{ modulo.titulo }}</h5>
                            <span v-if="!modulo.existe" class="badge bg-soft-info text-info" title="La base todavía no tiene plantilla: se usa el texto estándar">Texto estándar</span>
                            <small v-else-if="modulo.actualizado" class="text-muted">Último cambio: <span class="cifra">{{ fechaHora(modulo.actualizado) }}</span></small>
                        </div>

                        <div class="card-body">
                            <div v-for="(campo, indice) in CAMPOS" :key="campo" class="mb-4" :data-tour="indice === 0 ? 'p-bloque' : undefined">
                                <div class="d-flex flex-wrap align-items-baseline justify-content-between gap-1 mb-1">
                                    <label :for="`plantilla-${campo}`" class="form-label mb-0 fw-semibold">{{ definicion[campo].etiqueta }}</label>
                                    <small class="text-muted cifra" :class="{ 'text-danger': (form[campo]?.length ?? 0) > definicion[campo].maximo }">
                                        {{ form[campo]?.length ?? 0 }} / {{ definicion[campo].maximo }}
                                    </small>
                                </div>
                                <small class="text-muted d-block mb-2">{{ definicion[campo].ayuda }}</small>

                                <div v-if="Object.keys(definicion[campo].variables).length" class="d-flex flex-wrap gap-1 mb-2" :data-tour="campo === 'persona' ? 'p-variables' : undefined">
                                    <button
                                        v-for="(descripcion, variable) in definicion[campo].variables"
                                        :key="variable"
                                        type="button"
                                        class="btn btn-sm btn-soft-secondary py-0 font-monospace"
                                        :title="`Insertar: ${descripcion}`"
                                        @click="insertar(campo, variable)"
                                    >
                                        <i class="ri-add-line align-middle" />{{ marca(variable) }}
                                    </button>
                                </div>

                                <textarea
                                    :id="`plantilla-${campo}`"
                                    :ref="(el) => (areas[campo] = el)"
                                    v-model="form[campo]"
                                    class="form-control"
                                    :class="{ 'is-invalid': form.errors[campo] }"
                                    :rows="campo === 'persona' ? 2 : 4"
                                    @focus="enFoco = campo"
                                    @blur="enFoco = null"
                                />
                                <CampoError :mensaje="form.errors[campo]" />
                                <p v-if="!form.errors[campo] && desconocidas(campo).length" class="text-warning small mb-0 mt-1">
                                    <i class="ri-error-warning-line align-bottom" />
                                    Variable que no existe en este bloque: <span class="font-monospace">{{ desconocidas(campo).map(marca).join(', ') }}</span>.
                                    Saldría impresa tal cual; el sistema no la guardará.
                                </p>
                            </div>
                        </div>

                        <div class="card-footer d-flex flex-wrap justify-content-between gap-2" data-tour="p-acciones">
                            <button type="button" class="btn btn-soft-secondary" :disabled="form.processing" @click="usarEstandar">
                                <i class="ri-restart-line align-middle me-1" /> Usar texto estándar
                            </button>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-light" :disabled="!form.isDirty || form.processing" @click="descartar">Descartar cambios</button>
                                <button type="submit" class="btn btn-primary" :disabled="form.processing || (modulo.existe && !form.isDirty)">
                                    <i class="align-middle me-1" :class="form.processing ? 'ri-loader-4-line' : 'ri-save-3-line'" />
                                    {{ form.processing ? 'Guardando…' : 'Guardar' }}
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="col-xl-6">
                    <div class="card mb-0 vista-previa" data-tour="p-vista">
                        <div class="card-header d-flex align-items-center gap-2">
                            <h5 class="card-title mb-0 flex-grow-1">Vista previa</h5>
                            <small class="text-muted">Con datos de ejemplo</small>
                        </div>
                        <div class="card-body bg-light overflow-auto vista-previa__hoja">
                            <HojaFicha :campos="vistaPrevia" :numero="numeroEjemplo" :resaltado="enFoco" compacta />
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>

<style scoped>
/* La vista previa acompaña al editor al bajar (en pantallas anchas). */
@media (min-width: 1200px) {
    .vista-previa {
        position: sticky;
        top: calc(70px + 1rem);
    }

    .vista-previa__hoja {
        max-height: calc(100vh - 70px - 8rem);
    }
}
</style>
