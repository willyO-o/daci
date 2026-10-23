<script setup>
import { onMounted, ref } from 'vue';
import { useNotificacion } from '@/Composables/useNotificacion';
import { http } from '@/Utils/http';

/**
 * Ficha de registro editable (`_previewPrint.blade.php`): la plantilla activa con los datos del registro, cada
 * bloque marcado con ✏️ se puede corregir antes de «Generar PDF». El PDF oficial lo arma el servidor.
 */
const props = defineProps({
    registroId: { type: Number, required: true },
});

const { errorDeRespuesta } = useNotificacion();

const cargando = ref(true);
const datos = ref(null);
const codigo = ref(null);
const urlPdf = ref(null);
const generando = ref(false);
const bloques = ref({});

const CAMPOS = ['caso_cud', 'introduccion', 'requerimiento', 'persona', 'resultado_busqueda', 'nota_certificacion', 'nota_general', 'fecha_literal'];

onMounted(async () => {
    try {
        datos.value = (await http.get(route('registro.vista-previa', props.registroId))).data;
    } catch (err) {
        errorDeRespuesta(err);
    } finally {
        cargando.value = false;
    }
});

const registrarBloque = (campo) => (elemento) => {
    if (elemento) {
        bloques.value[campo] = elemento;
    }
};

const generar = async () => {
    generando.value = true;
    try {
        // Texto plano (con saltos de línea): el sistema anterior enviaba HTML y quedaban restos como «br».
        const campos = Object.fromEntries(CAMPOS.map((campo) => [campo, bloques.value[campo]?.innerText ?? '']));
        const { data } = await http.post(route('ficha-registro.store'), {
            ...campos,
            registro_criminal_id: props.registroId,
            codigo: codigo.value,
        });
        codigo.value = data.data.codigo;
        urlPdf.value = `${data.data.url}?v=${Date.now()}`;
    } catch (err) {
        errorDeRespuesta(err);
    } finally {
        generando.value = false;
    }
};

const editar = () => {
    urlPdf.value = null;
};
</script>

<template>
    <div>
        <div v-if="cargando" class="text-center py-5"><div class="spinner-border text-primary" role="status" /></div>

        <template v-else-if="datos">
            <div class="text-center mb-3">
                <button v-if="!urlPdf" type="button" class="btn btn-primary" :disabled="generando" @click="generar">
                    <i class="mdi align-middle me-1" :class="generando ? 'mdi-loading mdi-spin' : 'mdi-file-pdf-box'" />
                    {{ generando ? 'Generando PDF...' : 'Generar PDF' }}
                </button>
                <template v-else>
                    <button type="button" class="btn btn-soft-primary me-2" @click="editar"><i class="ri-edit-2-line align-middle me-1" /> Editar Ficha</button>
                    <a :href="urlPdf" target="_blank" rel="noopener" class="btn btn-primary"><i class="ri-external-link-line align-middle me-1" /> Abrir en otra pestaña</a>
                </template>
                <p v-if="!urlPdf" class="text-muted small mt-2 mb-0">Haga clic en los bloques marcados con ✏️ para corregirlos antes de generar el PDF.</p>
            </div>

            <div v-show="!urlPdf" class="documento-ficha">
                <div class="encabezado">
                    <div class="encabezado-izquierda">
                        <img src="/felcc/img/felcc.png" alt="Escudo FELCC">
                        <div class="logo-texto">POLICIA BOLIVIANA <br> DIRECCION NACIONAL <br> FUERZA ESPECIAL DE LUCHA CONTRA EL CRIMEN <br> La Paz - Bolivia</div>
                    </div>
                    <div class="encabezado-derecha">
                        <div class="logo-texto">"DEPARTAMENTO DE ANÁLISIS <br> CRIMINAL E INTELIGENCIA" "D.A.C.I."</div>
                    </div>
                </div>

                <div class="numero-caso">
                    <div>
                        <div>No: {{ datos.nro_ficha }}</div>
                        <div :ref="registrarBloque('caso_cud')" class="editable" contenteditable="true">{{ datos.campos.caso_cud }}</div>
                    </div>
                </div>

                <div class="titulo">FICHA DE REGISTRO</div>

                <div :ref="registrarBloque('introduccion')" class="parrafo editable" contenteditable="true">{{ datos.campos.introduccion }}</div>
                <div class="etiqueta">REQUIERE:</div>
                <div :ref="registrarBloque('requerimiento')" class="seccion sangria editable" contenteditable="true">{{ datos.campos.requerimiento }}</div>
                <div :ref="registrarBloque('persona')" class="vineta negrita editable" contenteditable="true">{{ datos.campos.persona }}</div>
                <div :ref="registrarBloque('resultado_busqueda')" class="seccion subrayado negrita justificado editable" contenteditable="true">{{ datos.campos.resultado_busqueda }}</div>
                <div :ref="registrarBloque('nota_certificacion')" class="nota justificado negrita editable" contenteditable="true">{{ datos.campos.nota_certificacion }}</div>
                <div :ref="registrarBloque('nota_general')" class="seccion justificado editable" contenteditable="true">{{ datos.campos.nota_general }}</div>
                <div :ref="registrarBloque('fecha_literal')" class="fecha editable" contenteditable="true">{{ datos.campos.fecha_literal }}</div>

                <div class="firmas">
                    <div class="firma">
                        <div class="linea" />
                        <div class="nombre">Sgto. 2do. María Magdalena Huanca Churqui</div>
                        <div class="cargo">PERSONAL DE SERVICIO DEL DEPARTAMENTO</div>
                        <div class="cargo">ANÁLISIS CRIMINAL E INTELIGENCIA</div>
                        <div class="cargo">REGISTRO FOTOSTATICO SOMÁTICO "GRIA"</div>
                    </div>
                    <div class="firma separada">
                        <div class="linea" />
                        <div class="nombre">Cap. Alberto Marcio Poma García</div>
                        <div class="cargo">JEFE DEL DEPARTAMENTO DE</div>
                        <div class="cargo">ANÁLISIS CRIMINAL E INTELIGENCIA</div>
                        <div class="cargo">"D.A.C.I."</div>
                    </div>
                </div>
            </div>

            <iframe v-if="urlPdf" :src="urlPdf" title="Ficha de registro en PDF" class="w-100 border rounded" style="height: 800px" />
        </template>
    </div>
</template>

<style scoped>
/* Hoja tamaño oficio con la tipografía del documento impreso (copiada del sistema anterior). */
.documento-ficha {
    max-width: 8.5in;
    min-height: 13in;
    margin: 0 auto;
    padding: 0.5in 1in;
    background: #fff;
    box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
    font-family: 'Times New Roman', Times, serif;
    font-size: 12pt;
    line-height: 1.4;
    color: #333;
    white-space: pre-line;
}
.encabezado { display: flex; justify-content: space-between; gap: 40px; }
.encabezado-izquierda, .encabezado-derecha { flex: 1; display: flex; flex-direction: column; justify-content: center; min-height: 100px; text-align: center; }
.encabezado-izquierda { align-items: flex-start; }
.encabezado-derecha { align-items: flex-end; }
.encabezado img { width: 50px; display: block; margin: 0 auto 5px; }
.logo-texto { font-weight: 900; font-size: 6pt; white-space: normal; }
.numero-caso { display: flex; justify-content: flex-end; font-weight: bold; font-size: 10pt; margin-bottom: 15px; }
.titulo { text-align: center; font-weight: bold; font-size: 13pt; padding: 8px; margin-bottom: 15px; color: #000; text-decoration: underline; }
.parrafo { text-align: justify; margin-bottom: 12px; }
.etiqueta { font-weight: bold; margin: 10px 0 5px; }
.seccion { margin: 10px 0; }
.sangria { text-indent: 40px; }
.vineta { text-indent: 60px; margin-bottom: 8px; text-align: justify; }
.nota { padding: 8px; margin: 10px 0; font-style: italic; }
.justificado { text-align: justify; }
.negrita { font-weight: bold; }
.subrayado { text-decoration: underline; }
.fecha { text-align: right; margin-top: 20px; }
.firmas { display: flex; justify-content: space-between; font-size: 7pt; white-space: normal; }
.firma { margin-top: 30px; text-align: center; }
.firma.separada { margin-top: 75px; }
.linea { margin-top: 40px; border-top: 1px solid #000; display: inline-block; width: 200px; }
.nombre { margin-top: 5px; }
.cargo { font-size: 6pt; margin-top: 2px; font-weight: bold; }
.editable { border-radius: 2px; outline: 1px dashed transparent; transition: outline-color 150ms ease-out, background-color 150ms ease-out; }
.editable:hover { outline-color: rgba(64, 81, 137, 0.35); }
.editable:focus { outline: 1px dashed rgba(64, 81, 137, 0.7); background: rgba(64, 81, 137, 0.04); }
.editable::after { content: ' ✏️'; font-size: 9pt; }
.editable:focus::after { content: ''; }
:global([data-bs-theme='dark']) .documento-ficha { box-shadow: none; }
</style>
