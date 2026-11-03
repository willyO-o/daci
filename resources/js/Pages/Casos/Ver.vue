<script setup>
import { computed, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import PageHeader from '@/Components/Layout/PageHeader.vue';
import BaseModal from '@/Components/Ui/BaseModal.vue';
import EstadoVacio from '@/Components/Ui/EstadoVacio.vue';
import FormularioCaso from '@/Components/Casos/FormularioCaso.vue';
import { useNotificacion } from '@/Composables/useNotificacion';
import { usePermisos } from '@/Composables/usePermisos';
import { cifra, fecha, fechaHora, guion, nombreCompleto } from '@/Utils/formato';

/**
 * Detalle de un caso: todo lo que tiene su `id_caso`. Sólo llegan las secciones que el usuario puede ver; cada una
 * muestra hasta `porSeccion` filas (las más recientes) con el total.
 */
const props = defineProps({
    caso: { type: Object, required: true },
    secciones: { type: Object, default: () => ({}) },
    porSeccion: { type: Number, default: 100 },
    estados: { type: Array, default: () => [] },
});

const { puede } = usePermisos();
const { confirmar } = useNotificacion();

const seccion = (nombre) => props.secciones[nombre] ?? null;
const totalVinculados = computed(() => Object.values(props.secciones).reduce((suma, s) => suma + (s.total ?? 0), 0));
const faltan = (s) => (s && s.total > s.filas.length ? s.total - s.filas.length : 0);
const persona = (p) => (p ? `${nombreCompleto(p)}${p.ci ? ` · C.I. ${p.ci}` : ''}` : null);
const urlPersona = (p) => (p && puede('personas.ver') ? route('personas.show', p.id) : null);

const modalEditar = ref(false);
const formulario = ref(null);

const eliminar = async () => {
    if (await confirmar({ titulo: `¿Eliminar el caso ${props.caso.codigo}?`, texto: 'Esta acción no se puede deshacer.', confirmar: 'Sí, eliminar', peligro: true })) {
        router.delete(route('casos.destroy', props.caso.id));
    }
};
</script>

<template>
    <div>
        <PageHeader :titulo="`Caso ${caso.codigo}`" :migas="[{ etiqueta: 'Casos', ruta: 'casos.index' }, { etiqueta: caso.codigo }]">
            <template #acciones>
                <div class="d-flex gap-2">
                    <Link :href="route('casos.index')" class="btn btn-soft-secondary"><i class="ri-arrow-left-line align-middle me-1" /> Volver</Link>
                    <button v-if="puede('casos.editar')" type="button" class="btn btn-soft-primary" @click="modalEditar = true"><i class="ri-pencil-line align-middle me-1" /> Editar</button>
                    <button v-if="puede('casos.eliminar') && totalVinculados === 0" type="button" class="btn btn-soft-danger" @click="eliminar"><i class="ri-delete-bin-line align-middle me-1" /> Eliminar</button>
                </div>
            </template>
        </PageHeader>

        <div class="card">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4"><h6 class="text-muted mb-1">Caso / CUD</h6><p class="fw-bold fs-5 mb-0">{{ caso.codigo }}</p></div>
                    <div class="col-md-2">
                        <h6 class="text-muted mb-1">Estado</h6>
                        <span class="badge" :class="caso.estado === 'ACTIVO' ? 'bg-soft-success text-success' : 'bg-soft-secondary text-secondary'">{{ caso.estado }}</span>
                    </div>
                    <div class="col-md-3"><h6 class="text-muted mb-1">Registrado</h6><p class="mb-0 cifra">{{ guion(fechaHora(caso.created_at)) }}</p><small class="text-muted">{{ caso.usuario ?? 'Trasladado del sistema' }}</small></div>
                    <div class="col-md-3"><h6 class="text-muted mb-1">Registros vinculados</h6><p class="fw-bold fs-5 mb-0 cifra">{{ cifra(totalVinculados) }}</p></div>
                    <div v-if="caso.descripcion" class="col-12"><h6 class="text-muted mb-1">Descripción</h6><p class="mb-0" style="white-space: pre-line">{{ caso.descripcion }}</p></div>
                </div>
            </div>
        </div>

        <!-- Teléfonos -->
        <div v-if="seccion('telefonos')" class="card">
            <div class="card-header d-flex align-items-center">
                <h5 class="card-title mb-0 flex-grow-1"><i class="mdi mdi-phone me-1" /> Teléfonos <span class="badge bg-soft-primary text-primary cifra ms-1">{{ cifra(seccion('telefonos').total) }}</span></h5>
            </div>
            <div class="card-body">
                <div v-if="seccion('telefonos').filas.length" class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light"><tr><th>Número Celular</th><th>Empresa</th><th>Persona del Caso</th><th>Persona Vinculada</th><th>Fecha Registro</th></tr></thead>
                        <tbody>
                            <tr v-for="t in seccion('telefonos').filas" :key="t.id">
                                <td class="fw-bold cifra">{{ t.numero_celular }}</td>
                                <td>{{ guion(t.empresa) }}</td>
                                <td>{{ guion(t.persona_caso) }}</td>
                                <td>
                                    <Link v-if="urlPersona(t.persona)" :href="urlPersona(t.persona)">{{ persona(t.persona) }}</Link>
                                    <template v-else>{{ guion(persona(t.persona)) }}</template>
                                </td>
                                <td class="cifra text-nowrap">{{ guion(fecha(t.created_at)) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <EstadoVacio v-else titulo="Sin teléfonos en este caso" />
                <p v-if="faltan(seccion('telefonos'))" class="text-muted small mt-2 mb-0">Se muestran los {{ porSeccion }} más recientes; hay {{ cifra(faltan(seccion('telefonos'))) }} más.</p>
            </div>
        </div>

        <!-- Registro criminal y Faltas y Contravenciones -->
        <template v-for="[clave, titulo, icono, rutaVer] in [['registrosCriminales', 'Registro Criminal', 'mdi-head', 'registro-criminal.showByCodigo'], ['faltasContravenciones', 'Faltas y Contravenciones', 'mdi-alert-circle-outline', 'faltas-contravenciones.showByCodigo']]" :key="clave">
            <div v-if="seccion(clave)" class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="mdi me-1" :class="icono" /> {{ titulo }} <span class="badge bg-soft-primary text-primary cifra ms-1">{{ cifra(seccion(clave).total) }}</span></h5>
                </div>
                <div class="card-body">
                    <div v-if="seccion(clave).filas.length" class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead class="table-light"><tr><th>Nro.</th><th>Persona</th><th>División</th><th>Fecha Registro</th><th class="text-center">Ver</th></tr></thead>
                            <tbody>
                                <tr v-for="r in seccion(clave).filas" :key="r.id">
                                    <td class="cifra">{{ guion(r.nro_registro) }}</td>
                                    <td>{{ guion(persona(r.persona)) }}</td>
                                    <td>{{ guion(r.division?.division) }}</td>
                                    <td class="cifra text-nowrap">{{ guion(fecha(r.fecha_registro)) }}</td>
                                    <td class="text-center"><Link :href="route(rutaVer, r.codigo)" class="btn btn-sm btn-soft-secondary" title="Ver registro"><i class="ri-eye-fill align-bottom" /></Link></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <EstadoVacio v-else :titulo="`Sin registros de ${titulo.toLowerCase()} en este caso`" />
                    <p v-if="faltan(seccion(clave))" class="text-muted small mt-2 mb-0">Se muestran los {{ porSeccion }} más recientes; hay {{ cifra(faltan(seccion(clave))) }} más.</p>
                </div>
            </div>
        </template>

        <!-- Vehículos -->
        <div v-if="seccion('vehiculos') || seccion('vinculosVehiculo')" class="card">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="mdi mdi-car me-1" /> Vehículos <span class="badge bg-soft-primary text-primary cifra ms-1">{{ cifra((seccion('vehiculos')?.total ?? 0) + (seccion('vinculosVehiculo')?.total ?? 0)) }}</span></h5>
            </div>
            <div class="card-body">
                <h6 class="text-muted">Caso relacionado del vehículo</h6>
                <div v-if="seccion('vehiculos')?.filas.length" class="table-responsive mb-3">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light"><tr><th>Placa</th><th>Responsable</th><th>Descripción</th></tr></thead>
                        <tbody>
                            <tr v-for="v in seccion('vehiculos').filas" :key="v.id">
                                <td class="fw-bold cifra">{{ v.placa }}</td>
                                <td>{{ guion(v.responsable) }}</td>
                                <td>{{ guion(v.descripcion) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-else class="text-muted small">Ningún vehículo tiene este caso como caso relacionado.</p>
                <p v-if="faltan(seccion('vehiculos'))" class="text-muted small">Se muestran los {{ porSeccion }} más recientes; hay {{ cifra(faltan(seccion('vehiculos'))) }} más.</p>

                <h6 class="text-muted">Vínculos vehículo – persona con este caso</h6>
                <div v-if="seccion('vinculosVehiculo')?.filas.length" class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light"><tr><th>Placa</th><th>Persona</th><th>Tipo</th></tr></thead>
                        <tbody>
                            <tr v-for="v in seccion('vinculosVehiculo').filas" :key="v.id">
                                <td class="fw-bold cifra">{{ guion(v.vehiculo?.placa) }}</td>
                                <td>
                                    <Link v-if="urlPersona(v.persona)" :href="urlPersona(v.persona)">{{ persona(v.persona) }}</Link>
                                    <template v-else>{{ guion(persona(v.persona)) }}</template>
                                </td>
                                <td>{{ guion(v.tipo) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-else class="text-muted small mb-0">Ningún vínculo vehículo – persona tiene este caso.</p>
                <p v-if="faltan(seccion('vinculosVehiculo'))" class="text-muted small mt-2 mb-0">Se muestran los {{ porSeccion }} más recientes; hay {{ cifra(faltan(seccion('vinculosVehiculo'))) }} más.</p>
            </div>
        </div>

        <BaseModal v-model="modalEditar" titulo="Editar Caso" estatico>
            <FormularioCaso ref="formulario" :caso="caso" :estados="estados" @guardado="modalEditar = false" />
            <template #pie="{ cerrar }">
                <button type="button" class="btn btn-secondary" @click="cerrar"><i class="ri-close-line align-middle me-1" /> Cancelar</button>
                <button type="submit" form="formCaso" class="btn btn-primary" :disabled="formulario?.procesando">
                    <i class="align-middle me-1" :class="formulario?.procesando ? 'ri-loader-4-line' : 'ri-save-3-line'" /> {{ formulario?.procesando ? 'Guardando…' : 'Guardar' }}
                </button>
            </template>
        </BaseModal>
    </div>
</template>
