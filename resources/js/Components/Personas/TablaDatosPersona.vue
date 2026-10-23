<script setup>
import { computed } from 'vue';
import { ciCompleto, fechaNum, guion } from '@/Utils/formato';

/** «Datos personales» (el parcial `personas/partials/_datos.blade.php`), usado en todas las fichas. */
const props = defineProps({
    persona: { type: Object, required: true },
});

const filas = computed(() => [
    ['Nro C.I.', ciCompleto(props.persona)],
    ['Nombre', props.persona.nombres],
    ['Apellidos', props.persona.apellidos],
    ['Alias', props.persona.alias],
    ['Nro Celular', props.persona.telefono],
    ['Ocupación', props.persona.ocupacion],
    [
        'Nacimiento / edad',
        props.persona.fecha_nacimiento ? `${fechaNum(props.persona.fecha_nacimiento)} / ${props.persona.edad ?? '—'} años` : null,
    ],
    ['Género', props.persona.genero],
    ['Lugar de nacimiento', props.persona.lugar_nacimiento],
    ['Grupo sanguíneo', props.persona.grupo_sanguineo],
    ['Nacionalidad', props.persona.nacionalidad],
    ['Estado civil', props.persona.estado_civil],
    ['Nombre del cónyuge', props.persona.nombre_conyuge],
    ['Nombre del padre', props.persona.padre],
    ['Nombre de la madre', props.persona.madre],
    ['Domicilio', props.persona.domicilio],
]);
</script>

<template>
    <div class="mt-3">
        <h5 class="fs-14"><i class="ri-user-line me-1 align-middle" /> Datos personales:</h5>
        <div class="table-responsive">
            <table class="table mb-0 small table-sm">
                <tbody>
                    <tr v-for="[etiqueta, valor] in filas" :key="etiqueta">
                        <th scope="row" style="width: 200px">{{ etiqueta }}</th>
                        <td>{{ guion(valor) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
