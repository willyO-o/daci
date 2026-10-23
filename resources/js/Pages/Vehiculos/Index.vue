<script setup>
import { ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import PageHeader from '@/Components/Layout/PageHeader.vue';
import BaseModal from '@/Components/Ui/BaseModal.vue';
import BuscadorRemoto from '@/Components/Ui/BuscadorRemoto.vue';
import CampoError from '@/Components/Ui/CampoError.vue';
import EstadoVacio from '@/Components/Ui/EstadoVacio.vue';
import FilasCargando from '@/Components/Ui/FilasCargando.vue';
import Paginacion from '@/Components/Ui/Paginacion.vue';
import SubirArchivos from '@/Components/Ui/SubirArchivos.vue';
import DetalleVehiculo from '@/Components/Vehiculos/DetalleVehiculo.vue';
import { useListado } from '@/Composables/useListado';
import { useNotificacion } from '@/Composables/useNotificacion';
import { usePermisos } from '@/Composables/usePermisos';
import { fecha, guion, nombreCompleto, urlArchivo } from '@/Utils/formato';
import { http } from '@/Utils/http';

const props = defineProps({
    vehiculos: { type: Object, required: true },
    filtros: { type: Object, default: () => ({}) },
});

const TIPOS = ['BSISA', 'RUAT', 'SOAT', 'ANH', 'ITB'];

const { puede } = usePermisos();
const { confirmar, exito, errorDeRespuesta, aviso } = useNotificacion();
const { filtros, cargando, irAPagina, recargar } = useListado('vehiculos.index', { search: props.filtros.search ?? '', filtro: props.filtros.filtro ?? '' }, ['vehiculos']);

const numero = (indice) => (props.vehiculos.from ?? 1) + indice;
const textoPersona = (p) => `${p.nombres} ${p.apellidos ?? ''} - CI: ${p.ci ?? ''}`;

// Alta / edición con personas asociadas y fotos
const modalFormulario = ref(false);
const editando = ref(null);
const fotosActuales = ref([]);
const personaElegida = ref(null);
const personaElegidaDatos = ref(null);
const tipoPersona = ref('');
const casoPersona = ref('');
const form = useForm({ placa: '', responsable: '', descripcion: '', caso_relacionado: '', personas_asociadas: [], fotosVehiculos: [] });

const abrirNuevo = () => {
    editando.value = null;
    fotosActuales.value = [];
    form.defaults({ placa: '', responsable: '', descripcion: '', caso_relacionado: '', personas_asociadas: [], fotosVehiculos: [] });
    form.reset();
    form.clearErrors();
    modalFormulario.value = true;
};
const abrirEditar = (vehiculo) => {
    editando.value = vehiculo;
    fotosActuales.value = [...(vehiculo.multimedia ?? [])];
    form.defaults({
        placa: vehiculo.placa,
        responsable: vehiculo.responsable ?? '',
        descripcion: vehiculo.descripcion ?? '',
        caso_relacionado: vehiculo.caso_relacionado ?? '',
        personas_asociadas: (vehiculo.personas ?? []).map((p) => ({
            persona_id: p.id,
            nombre: nombreCompleto(p),
            tipo: p.pivot?.tipo ?? '',
            caso: p.pivot?.numero_informacion ?? '',
        })),
        fotosVehiculos: [],
    });
    form.reset();
    form.clearErrors();
    modalFormulario.value = true;
};

const agregarPersona = () => {
    if (!personaElegida.value || !tipoPersona.value.trim()) {
        aviso('Seleccione una persona y el tipo de información.');

        return;
    }
    const tipo = tipoPersona.value.trim().toUpperCase();
    if (form.personas_asociadas.some((p) => p.persona_id === personaElegida.value && p.tipo === tipo)) {
        aviso('Esta persona ya está agregada con ese tipo.');

        return;
    }
    form.personas_asociadas.push({
        persona_id: personaElegida.value,
        nombre: personaElegidaDatos.value ? nombreCompleto(personaElegidaDatos.value) : '',
        tipo,
        caso: casoPersona.value.trim().toUpperCase(),
    });
    personaElegida.value = null;
    personaElegidaDatos.value = null;
    tipoPersona.value = '';
    casoPersona.value = '';
};
const quitarPersona = (indice) => form.personas_asociadas.splice(indice, 1);

const guardar = () => {
    const opciones = { forceFormData: true, preserveScroll: true, preserveState: true, onSuccess: () => (modalFormulario.value = false) };
    const limpiar = (datos) => ({ ...datos, personas_asociadas: datos.personas_asociadas.map(({ persona_id, tipo, caso }) => ({ persona_id, tipo, caso })) });
    if (editando.value) {
        form.transform((datos) => ({ ...limpiar(datos), _method: 'put' })).post(route('vehiculos.update', editando.value.id), opciones);
    } else {
        form.transform(limpiar).post(route('vehiculos.store'), opciones);
    }
};

const eliminarFoto = async (foto) => {
    if (!(await confirmar({ titulo: '¿Eliminar foto?', texto: 'Esta acción no se puede deshacer', confirmar: 'Sí, eliminar', peligro: true }))) {
        return;
    }
    try {
        await http.delete(route('multimedia.destroy', foto.id));
        fotosActuales.value = fotosActuales.value.filter((f) => f.id !== foto.id);
        exito('Foto eliminada correctamente', 'Eliminada');
        recargar();
    } catch (err) {
        errorDeRespuesta(err);
    }
};

// Detalle
const modalDetalle = ref(false);
const detalle = ref(null);
const verDetalle = async (vehiculo) => {
    detalle.value = null;
    modalDetalle.value = true;
    try {
        detalle.value = (await http.get(route('vehiculos.show', vehiculo.id))).data;
    } catch (err) {
        modalDetalle.value = false;
        errorDeRespuesta(err);
    }
};

// Vincular persona
const modalVincular = ref(false);
const vehiculoVincular = ref(null);
const formVincular = useForm({ persona_id: null, tipo: '', caso: '' });
const abrirVincular = (vehiculo) => {
    vehiculoVincular.value = vehiculo;
    formVincular.reset();
    formVincular.clearErrors();
    modalVincular.value = true;
};
const vincular = () => {
    formVincular.post(route('vehiculos.vincular-persona', vehiculoVincular.value.id), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => (modalVincular.value = false),
    });
};

const eliminar = async (vehiculo) => {
    if (await confirmar({ titulo: `¿Eliminar el vehículo ${vehiculo.placa}?`, texto: 'Se borrarán también sus personas vinculadas, carguíos, ITV y fotos.', confirmar: 'Sí, eliminar', peligro: true })) {
        router.delete(route('vehiculos.destroy', vehiculo.id), { preserveScroll: true, preserveState: true });
    }
};
</script>

<template>
    <div>
        <PageHeader titulo="Gestión de Vehículos" :migas="[{ etiqueta: 'Vehículos' }]">
            <template v-if="puede('vehiculos.crear')" #acciones>
                <button type="button" class="btn btn-primary" @click="abrirNuevo"><i class="ri-add-line align-middle me-1" /> Nuevo Vehículo</button>
            </template>
        </PageHeader>

        <div class="card">
            <div class="card-header">
                <div class="row g-2">
                    <div class="col-md-4">
                        <div class="input-group">
                            <span class="input-group-text bg-primary text-white border-primary"><i class="ri-search-line" /></span>
                            <input v-model="filtros.search" type="search" class="form-control" placeholder="Buscar (Placa, descripción, responsable)..." aria-label="Buscar vehículos">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select v-model="filtros.filtro" class="form-select" aria-label="Filtrar por">
                            <option value="">Filtrar Por</option>
                            <option value="placa">Placa</option>
                            <option value="descripcion">Descripción</option>
                            <option value="responsable">Responsable</option>
                            <option value="caso_relacionado">Caso Relacionado</option>
                            <option value="ci_persona">CI de Persona</option>
                            <option value="nombres">Nombre de Persona</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr><th>#</th><th>Placa</th><th>Descripción</th><th>Personas Vinculadas</th><th>Responsable</th><th>Caso Relacionado</th><th>Fecha Registro</th><th class="text-center">Acciones</th></tr>
                        </thead>
                        <tbody>
                            <FilasCargando v-if="cargando" :columnas="8" />
                            <template v-else>
                                <tr v-for="(vehiculo, indice) in vehiculos.data" :key="vehiculo.id">
                                    <td class="text-muted">{{ numero(indice) }}</td>
                                    <td><span class="fw-bold">{{ vehiculo.placa }}</span></td>
                                    <td>{{ guion(vehiculo.descripcion) }}</td>
                                    <td>
                                        <div v-if="vehiculo.personas?.length" style="max-height: 150px; overflow-y: auto">
                                            <div v-for="persona in vehiculo.personas" :key="`${persona.id}-${persona.pivot?.tipo}`" class="mb-2">
                                                <div class="fw-bold cifra">{{ guion(persona.ci) }}</div>
                                                <small>{{ nombreCompleto(persona) }}</small><br>
                                                <span class="badge badge-outline-secondary">{{ persona.pivot?.tipo }}</span>
                                                <span v-if="persona.pivot?.numero_informacion" class="badge badge-outline-info ms-1">{{ persona.pivot.numero_informacion }}</span>
                                            </div>
                                        </div>
                                        <small v-else class="text-muted">Sin personas vinculadas</small>
                                    </td>
                                    <td>{{ guion(vehiculo.responsable) }}</td>
                                    <td>{{ guion(vehiculo.caso_relacionado) }}</td>
                                    <td class="cifra text-nowrap">{{ fecha(vehiculo.created_at) }}</td>
                                    <td class="text-center">
                                        <div class="d-flex gap-2 justify-content-center">
                                            <button type="button" class="btn btn-sm btn-soft-secondary" title="Ver detalles" @click="verDetalle(vehiculo)"><i class="ri-eye-fill align-bottom" /></button>
                                            <button v-if="puede('vehiculos.vincular')" type="button" class="btn btn-sm btn-soft-success" title="Vincular persona" @click="abrirVincular(vehiculo)"><i class="ri-links-line align-bottom" /></button>
                                            <button v-if="puede('vehiculos.editar')" type="button" class="btn btn-sm btn-soft-secondary" title="Editar" @click="abrirEditar(vehiculo)"><i class="ri-pencil-fill align-bottom" /></button>
                                            <button v-if="puede('vehiculos.eliminar')" type="button" class="btn btn-sm btn-soft-secondary" title="Eliminar" @click="eliminar(vehiculo)"><i class="ri-delete-bin-fill align-bottom" /></button>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
                <EstadoVacio v-if="!cargando && !vehiculos.data.length" titulo="No se encontraron vehículos" />
                <Paginacion :paginado="vehiculos" entidad="vehículos" :deshabilitado="cargando" @ir="irAPagina" />
            </div>
        </div>

        <BaseModal v-model="modalFormulario" :titulo="editando ? 'Editar Vehículo' : 'Nuevo Vehículo'" tamano="lg" estatico>
            <form id="formVehiculo" novalidate @submit.prevent="guardar">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="placa" class="form-label">Placa *</label>
                        <input id="placa" v-model="form.placa" v-mayuscula v-sin-espacios type="text" class="form-control" :class="{ 'is-invalid': form.errors.placa }" placeholder="Ej: LAB-123">
                        <CampoError :mensaje="form.errors.placa" />
                    </div>
                    <div class="col-md-6">
                        <label for="responsable" class="form-label">Responsable</label>
                        <input id="responsable" v-model="form.responsable" v-mayuscula type="text" class="form-control" :class="{ 'is-invalid': form.errors.responsable }" placeholder="Nombre del responsable">
                        <CampoError :mensaje="form.errors.responsable" />
                    </div>
                    <div class="col-12">
                        <label for="descripcion" class="form-label">Descripción</label>
                        <textarea id="descripcion" v-model="form.descripcion" class="form-control" :class="{ 'is-invalid': form.errors.descripcion }" rows="2" maxlength="255" placeholder="Descripción del vehículo" />
                        <CampoError :mensaje="form.errors.descripcion" />
                    </div>
                    <div class="col-12">
                        <label for="caso_relacionado" class="form-label">Caso Relacionado</label>
                        <input id="caso_relacionado" v-model="form.caso_relacionado" v-mayuscula type="text" class="form-control" :class="{ 'is-invalid': form.errors.caso_relacionado }" placeholder="Caso relacionado">
                        <CampoError :mensaje="form.errors.caso_relacionado" />
                    </div>
                </div>

                <hr>
                <h6 class="mb-3">Personas Asociadas</h6>
                <div class="mb-3">
                    <label class="form-label">Buscar Persona</label>
                    <BuscadorRemoto v-model="personaElegida" :url="route('personas.search')" placeholder="Nombre, apellido o CI" :mapear="(p) => ({ id: p.id, texto: textoPersona(p) })" @seleccionado="(p) => (personaElegidaDatos = p)" />
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="tipoPersona" class="form-label">Tipo de Información *</label>
                        <input id="tipoPersona" v-model="tipoPersona" v-mayuscula list="tiposPersona" class="form-control" placeholder="Seleccionar tipo">
                        <datalist id="tiposPersona"><option v-for="tipo in TIPOS" :key="tipo" :value="tipo" /></datalist>
                    </div>
                    <div class="col-md-6">
                        <label for="casoPersona" class="form-label">Caso (Opcional)</label>
                        <input id="casoPersona" v-model="casoPersona" v-mayuscula type="text" class="form-control" placeholder="Ingresa el caso si aplica">
                    </div>
                </div>
                <button type="button" class="btn btn-sm btn-secondary my-3" @click="agregarPersona"><i class="ri-add-line align-middle me-1" /> Agregar Persona</button>
                <div v-for="(persona, indice) in form.personas_asociadas" :key="`${persona.persona_id}-${persona.tipo}`" class="card mb-2 border">
                    <div class="card-body p-2 d-flex justify-content-between align-items-center">
                        <div>
                            <small class="fw-bold d-block">{{ persona.nombre || `Persona #${persona.persona_id}` }}</small>
                            <span class="badge badge-outline-secondary">{{ persona.tipo }}</span>
                            <template v-if="persona.caso">Caso: <span class="badge badge-outline-info">{{ persona.caso }}</span></template>
                        </div>
                        <button type="button" class="btn btn-sm btn-soft-danger" title="Quitar" @click="quitarPersona(indice)"><i class="ri-close-line align-bottom" /></button>
                    </div>
                </div>
                <CampoError :mensaje="form.errors['personas_asociadas.0.persona_id'] || form.errors['personas_asociadas.0.tipo']" />

                <hr>
                <h6 class="mb-3">Fotos del Vehículo <small class="text-muted fw-normal">(JPEG, PNG, WebP, JPG - máx 2MB por archivo)</small></h6>
                <SubirArchivos v-model="form.fotosVehiculos" :maximo="10" :tamano-maximo-mb="2" acepta="image/jpeg,image/png,image/webp,image/jpg" texto="Arrastra o sube las fotos del vehículo" :error="form.errors['fotosVehiculos.0']" />
                <div v-if="fotosActuales.length" class="mt-3">
                    <label class="form-label">Fotos Actuales:</label>
                    <div class="d-flex flex-wrap gap-2">
                        <div v-for="foto in fotosActuales" :key="foto.id" class="position-relative">
                            <img :src="urlArchivo(foto.ruta)" alt="Foto del vehículo" class="rounded border miniatura-archivo">
                            <button v-if="puede('vehiculos.editar')" type="button" class="btn btn-sm btn-danger position-absolute top-0 end-0 m-1 py-0 px-1" aria-label="Eliminar foto" @click="eliminarFoto(foto)"><i class="ri-delete-bin-fill" /></button>
                        </div>
                    </div>
                </div>
            </form>
            <template #pie="{ cerrar }">
                <button type="button" class="btn btn-secondary" @click="cerrar"><i class="ri-close-line align-middle me-1" /> Cancelar</button>
                <button type="submit" form="formVehiculo" class="btn btn-primary" :disabled="form.processing">
                    <i class="align-middle me-1" :class="form.processing ? 'ri-loader-4-line' : 'ri-save-3-line'" /> {{ form.processing ? 'Guardando…' : 'Guardar' }}
                </button>
            </template>
        </BaseModal>

        <BaseModal v-model="modalDetalle" titulo="Detalles del Vehículo" tamano="xl" centrado>
            <DetalleVehiculo v-if="detalle" :vehiculo="detalle" />
            <div v-else class="text-center py-5"><div class="spinner-border text-primary" role="status" /></div>
            <template #pie="{ cerrar }">
                <button type="button" class="btn btn-secondary" @click="cerrar"><i class="ri-close-line align-middle me-1" /> Cerrar</button>
            </template>
        </BaseModal>

        <BaseModal v-model="modalVincular" titulo="Vincular Persona al Vehículo" centrado>
            <form id="formVincularVehiculo" novalidate @submit.prevent="vincular">
                <div class="mb-3">
                    <label class="form-label">Buscar Persona *</label>
                    <BuscadorRemoto v-model="formVincular.persona_id" :url="route('personas.search')" placeholder="Seleccionar persona" :mapear="(p) => ({ id: p.id, texto: textoPersona(p) })" :invalido="Boolean(formVincular.errors.persona_id)" />
                    <CampoError :mensaje="formVincular.errors.persona_id" />
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="vincularTipo" class="form-label">Tipo de Información *</label>
                        <input id="vincularTipo" v-model="formVincular.tipo" v-mayuscula list="datalistTipo" class="form-control" :class="{ 'is-invalid': formVincular.errors.tipo }" placeholder="Buscar tipo...">
                        <datalist id="datalistTipo"><option v-for="tipo in TIPOS" :key="tipo" :value="tipo" /></datalist>
                        <CampoError :mensaje="formVincular.errors.tipo" />
                    </div>
                    <div class="col-md-6">
                        <label for="vincularCaso" class="form-label">Caso (Opcional)</label>
                        <input id="vincularCaso" v-model="formVincular.caso" v-mayuscula type="text" class="form-control" placeholder="Ingresa el caso si aplica">
                    </div>
                </div>
            </form>
            <template #pie="{ cerrar }">
                <button type="button" class="btn btn-secondary" @click="cerrar"><i class="ri-close-line align-middle me-1" /> Cancelar</button>
                <button type="submit" form="formVincularVehiculo" class="btn btn-primary" :disabled="formVincular.processing"><i class="ri-links-line align-middle me-1" /> Vincular</button>
            </template>
        </BaseModal>
    </div>
</template>
