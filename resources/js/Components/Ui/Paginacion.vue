<script setup>
import { computed } from 'vue';

/**
 * Paginación de un listado paginado por Laravel (`LengthAwarePaginator`): «Mostrando X a Y de Z» y los números de
 * página con elipsis (mismo armado que `renderPaginacion` del sistema anterior: 2 páginas a cada lado).
 */
const props = defineProps({
    /** @type {{ current_page: number, last_page: number, from: ?number, to: ?number, total: number }} */
    paginado: { type: Object, required: true },
    /** Sustantivo plural para el resumen («personas», «mandamientos»). */
    entidad: { type: String, default: 'registros' },
    deshabilitado: { type: Boolean, default: false },
});

const emit = defineEmits(['ir']);

const actual = computed(() => props.paginado.current_page);
const ultima = computed(() => props.paginado.last_page);

const paginas = computed(() => {
    const inicio = Math.max(1, actual.value - 2);
    const fin = Math.min(ultima.value, actual.value + 2);
    const lista = [];

    if (inicio > 1) {
        lista.push(1);
        if (inicio > 2) {
            lista.push('…');
        }
    }
    for (let pagina = inicio; pagina <= fin; pagina++) {
        lista.push(pagina);
    }
    if (fin < ultima.value) {
        if (fin < ultima.value - 1) {
            lista.push('…');
        }
        lista.push(ultima.value);
    }

    return lista;
});

const formato = new Intl.NumberFormat('es-BO');

const ir = (pagina) => {
    if (!props.deshabilitado && pagina >= 1 && pagina <= ultima.value && pagina !== actual.value) {
        emit('ir', pagina);
    }
};
</script>

<template>
    <div v-if="paginado.total > 0" class="d-flex flex-column flex-sm-row justify-content-between align-items-center gap-2 mt-3">
        <span class="text-muted small cifra">
            Mostrando {{ formato.format(paginado.from ?? 0) }} a {{ formato.format(paginado.to ?? 0) }} de
            {{ formato.format(paginado.total) }} {{ entidad }}
        </span>
        <nav v-if="ultima > 1" aria-label="Paginación">
            <ul class="pagination pagination-sm mb-0">
                <li class="page-item" :class="{ disabled: actual <= 1 }">
                    <a class="page-link" href="#" aria-label="Anterior" @click.prevent="ir(actual - 1)">&laquo;</a>
                </li>
                <li
                    v-for="(pagina, indice) in paginas"
                    :key="`${pagina}-${indice}`"
                    class="page-item"
                    :class="{ active: pagina === actual, disabled: pagina === '…' }"
                >
                    <span v-if="pagina === '…'" class="page-link">…</span>
                    <a v-else class="page-link" href="#" :aria-current="pagina === actual ? 'page' : undefined" @click.prevent="ir(pagina)">
                        {{ pagina }}
                    </a>
                </li>
                <li class="page-item" :class="{ disabled: actual >= ultima }">
                    <a class="page-link" href="#" aria-label="Siguiente" @click.prevent="ir(actual + 1)">&raquo;</a>
                </li>
            </ul>
        </nav>
    </div>
</template>
