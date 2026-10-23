<script setup>
import { computed } from 'vue';

/**
 * Rango de fechas con los atajos del daterangepicker del sistema anterior (Hoy, Últimos 7 días, Este mes…).
 * v-model:inicio y v-model:fin en formato `YYYY-MM-DD`.
 */
const props = defineProps({
    inicio: { type: String, default: '' },
    fin: { type: String, default: '' },
    etiqueta: { type: String, default: 'Fecha de ejecución' },
});

const emit = defineEmits(['update:inicio', 'update:fin', 'cambio']);

const iso = (fecha) => {
    const mes = String(fecha.getMonth() + 1).padStart(2, '0');
    const dia = String(fecha.getDate()).padStart(2, '0');

    return `${fecha.getFullYear()}-${mes}-${dia}`;
};

const hoy = () => new Date();
const haceDias = (dias) => {
    const fecha = new Date();
    fecha.setDate(fecha.getDate() - dias);

    return fecha;
};

const atajos = [
    { etiqueta: 'Hoy', rango: () => [hoy(), hoy()] },
    { etiqueta: 'Últimos 7 días', rango: () => [haceDias(6), hoy()] },
    { etiqueta: 'Últimos 30 días', rango: () => [haceDias(29), hoy()] },
    { etiqueta: 'Este mes', rango: () => [new Date(hoy().getFullYear(), hoy().getMonth(), 1), new Date(hoy().getFullYear(), hoy().getMonth() + 1, 0)] },
    { etiqueta: 'Mes anterior', rango: () => [new Date(hoy().getFullYear(), hoy().getMonth() - 1, 1), new Date(hoy().getFullYear(), hoy().getMonth(), 0)] },
    { etiqueta: 'Últimos 90 días', rango: () => [haceDias(89), hoy()] },
];

const aplicar = (inicio, fin) => {
    emit('update:inicio', inicio);
    emit('update:fin', fin);
    emit('cambio', { inicio, fin });
};

const usarAtajo = (atajo) => {
    const [desde, hasta] = atajo.rango();
    aplicar(iso(desde), iso(hasta));
};

const tieneValor = computed(() => Boolean(props.inicio || props.fin));
</script>

<template>
    <div class="input-group">
        <span class="input-group-text bg-primary text-white border-primary" :title="etiqueta"><i class="ri-calendar-line" /></span>
        <input
            type="date"
            class="form-control"
            :value="inicio"
            :max="fin || undefined"
            :aria-label="`${etiqueta}: desde`"
            @change="aplicar($event.target.value, fin)"
        >
        <input
            type="date"
            class="form-control"
            :value="fin"
            :min="inicio || undefined"
            :aria-label="`${etiqueta}: hasta`"
            @change="aplicar(inicio, $event.target.value)"
        >
        <button type="button" class="btn btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" title="Atajos de fecha">
            <i class="ri-flashlight-line" />
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
            <li v-for="atajo in atajos" :key="atajo.etiqueta">
                <button type="button" class="dropdown-item" @click="usarAtajo(atajo)">{{ atajo.etiqueta }}</button>
            </li>
        </ul>
        <button type="button" class="btn btn-outline-danger" title="Limpiar filtro de fechas" :disabled="!tieneValor" @click="aplicar('', '')">
            <i class="ri-close-line" />
        </button>
    </div>
</template>
