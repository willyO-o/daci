<script setup>
import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import { cifra } from '@/Utils/formato';
import { http } from '@/Utils/http';

/**
 * Bloque de importación de un tipo de dato (las pantallas «Importar …» del sistema anterior): zona para soltar el
 * archivo, progreso de subida, resultado con la tabla de errores y, al lado, la guía de campos, las notas, la
 * plantilla y el total actual.
 */
const props = defineProps({
    titulo: { type: String, required: true },
    url: { type: String, required: true },
    /** `.csv` o `.csv,.xlsx`… */
    acepta: { type: String, default: '.csv' },
    formatos: { type: String, default: 'CSV' },
    /** Campos clave de la guía: `[{ nombre, detalle? }]`. */
    campos: { type: Array, default: () => [] },
    /** Lista completa de columnas reconocidas. */
    columnas: { type: Array, default: () => [] },
    notas: { type: Array, default: () => [] },
    plantilla: { type: String, default: null },
    etiquetaTotal: { type: String, default: 'Total registrados' },
    total: { type: Number, default: null },
    rutaVolver: { type: String, default: null },
    etiquetaVolver: { type: String, default: 'Ir al listado' },
});

const emit = defineEmits(['importado']);

const entrada = ref(null);
const archivo = ref(null);
const arrastrando = ref(false);
const progreso = ref(0);
const enviando = ref(false);
const resultado = ref(null);

const tamano = computed(() => (archivo.value ? `${(archivo.value.size / 1024 / 1024).toFixed(2)} MB` : '—'));
const extensiones = computed(() => props.acepta.split(',').map((ext) => ext.trim().toLowerCase()));

const elegir = (lista) => {
    const elegido = lista?.[0];
    resultado.value = null;
    if (!elegido) {
        return;
    }
    const extension = `.${elegido.name.split('.').pop().toLowerCase()}`;
    if (!extensiones.value.includes(extension)) {
        resultado.value = { tipo: 'danger', mensaje: `El archivo debe ser ${props.formatos}.` };

        return;
    }
    if (elegido.size > 10 * 1024 * 1024) {
        resultado.value = { tipo: 'danger', mensaje: 'El archivo no debe exceder 10MB.' };

        return;
    }
    archivo.value = elegido;
};

const soltar = (evento) => {
    arrastrando.value = false;
    elegir(evento.dataTransfer?.files);
};

const quitar = () => {
    archivo.value = null;
    progreso.value = 0;
    if (entrada.value) {
        entrada.value.value = '';
    }
};

const importar = async () => {
    const datos = new FormData();
    datos.append('archivo', archivo.value);
    enviando.value = true;
    progreso.value = 0;
    resultado.value = null;

    try {
        const { data } = await http.post(props.url, datos, {
            onUploadProgress: (evento) => {
                if (evento.total) {
                    progreso.value = Math.round((evento.loaded * 100) / evento.total);
                }
            },
        });
        resultado.value = {
            tipo: data.errores?.length ? 'warning' : 'success',
            mensaje: data.success,
            total: data.total,
            importadas: data.importadas,
            errores: data.errores ?? [],
        };
        emit('importado', data);
        quitar();
    } catch (err) {
        const datosError = err.response?.data ?? {};
        const mensaje = datosError.errors?.archivo?.[0] ?? (typeof datosError.errors === 'string' ? datosError.errors : null) ?? datosError.error ?? datosError.message ?? 'No se pudo procesar el archivo.';
        resultado.value = { tipo: 'danger', mensaje };
    } finally {
        enviando.value = false;
    }
};
</script>

<template>
    <div class="row">
        <div class="col-xl-8">
            <div class="card">
                <div class="card-header"><h5 class="card-title mb-0">{{ titulo }}</h5></div>
                <div class="card-body">
                    <div
                        class="zona-archivos text-center py-5 cursor-pointer"
                        :class="{ arrastrando }"
                        role="button"
                        tabindex="0"
                        @click="entrada.click()"
                        @keydown.enter.prevent="entrada.click()"
                        @dragover.prevent="arrastrando = true"
                        @dragleave.prevent="arrastrando = false"
                        @drop.prevent="soltar"
                    >
                        <i class="ri-upload-cloud-2-line display-5 text-muted" />
                        <p class="text-muted mt-3 mb-1">Arrastra tu archivo aquí o haz clic para seleccionar</p>
                        <small class="text-muted">Formatos: <span class="text-danger fw-medium">{{ formatos }}</span> (máx. 10MB)</small>
                        <input ref="entrada" type="file" class="d-none" :accept="acepta" @change="elegir($event.target.files)">
                    </div>

                    <div v-if="archivo" class="alert alert-info d-flex align-items-center justify-content-between mt-3 mb-0">
                        <div>
                            <i class="ri-file-excel-2-line align-middle me-2" /><strong>{{ archivo.name }}</strong>
                            <small class="d-block text-muted cifra">{{ tamano }}</small>
                        </div>
                        <button type="button" class="btn btn-sm btn-secondary" :disabled="enviando" @click="quitar">Quitar</button>
                    </div>

                    <button v-if="archivo" type="button" class="btn btn-primary w-100 mt-3" :disabled="enviando" @click="importar">
                        <i class="align-middle me-1" :class="enviando ? 'mdi mdi-loading mdi-spin' : 'ri-upload-2-line'" />
                        {{ enviando ? 'Importando…' : 'Importar' }}
                    </button>

                    <div v-if="enviando" class="mt-3">
                        <div class="progress" style="height: 6px">
                            <div class="progress-bar bg-success" role="progressbar" :style="{ width: `${progreso}%` }" :aria-valuenow="progreso" aria-valuemin="0" aria-valuemax="100" />
                        </div>
                        <p class="text-muted small mt-2 mb-0 cifra">{{ progreso < 100 ? `Subiendo ${progreso}%` : 'Procesando registros… no cierre esta pantalla.' }}</p>
                    </div>

                    <div v-if="resultado" class="mt-3">
                        <div class="alert mb-2" :class="`alert-${resultado.tipo}`" role="status">
                            {{ resultado.mensaje }}
                            <span v-if="resultado.total !== undefined" class="d-block small mt-1 cifra">
                                Filas leídas: {{ cifra(resultado.total) }} · Importadas: {{ cifra(resultado.importadas) }}
                            </span>
                        </div>
                        <template v-if="resultado.errores?.length">
                            <h6 class="mt-3 mb-2">Errores encontrados:</h6>
                            <div class="table-responsive" style="max-height: 260px">
                                <table class="table table-sm align-middle mb-0">
                                    <thead class="table-light"><tr><th>Fila</th><th>Nombre</th><th>Error</th></tr></thead>
                                    <tbody>
                                        <tr v-for="error in resultado.errores" :key="`${error.fila}-${error.nombre}`">
                                            <td class="cifra">{{ error.fila }}</td><td>{{ error.nombre }}</td><td class="text-danger small">{{ error.error }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </template>
                        <div v-if="resultado.tipo !== 'danger' && rutaVolver" class="d-flex gap-2 mt-3">
                            <Link :href="route(rutaVolver)" class="btn btn-primary"><i class="ri-list-check align-middle me-1" /> {{ etiquetaVolver }}</Link>
                            <button type="button" class="btn btn-secondary" @click="resultado = null">Importar otro archivo</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card">
                <div class="card-header"><h5 class="card-title mb-0">Guía de Campos</h5></div>
                <div class="card-body">
                    <ul v-if="campos.length" class="list-unstyled mb-3">
                        <li v-for="campo in campos" :key="campo.nombre" class="mb-2">
                            <strong>{{ campo.nombre }}</strong>
                            <small v-if="campo.detalle" class="d-block text-muted">{{ campo.detalle }}</small>
                        </li>
                    </ul>
                    <template v-if="columnas.length">
                        <p class="small text-muted mb-1">Columnas reconocidas:</p>
                        <div class="d-flex flex-wrap gap-1 mb-3">
                            <span v-for="columna in columnas" :key="columna" class="badge badge-outline-secondary">{{ columna }}</span>
                        </div>
                    </template>
                    <ul v-if="notas.length" class="small text-muted ps-3 mb-3">
                        <li v-for="nota in notas" :key="nota" class="mb-1">{{ nota }}</li>
                    </ul>
                    <a v-if="plantilla" :href="plantilla" download class="btn btn-sm btn-outline-primary w-100">
                        <i class="ri-download-2-line align-middle me-1" /> Descargar plantilla
                    </a>
                </div>
            </div>
            <div v-if="total !== null" class="card">
                <div class="card-header"><h5 class="card-title mb-0">Información</h5></div>
                <div class="card-body">
                    <small class="text-muted">{{ etiquetaTotal }}</small>
                    <h4 class="mb-0 cifra">{{ cifra(total) }}</h4>
                </div>
            </div>
        </div>
    </div>
</template>
