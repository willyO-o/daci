<script setup>
/**
 * Hoja de la ficha de registro de SÓLO LECTURA (el mismo diseño que `FichaRegistro.vue`, que es la editable): la usa
 * la vista previa de «Plantilla de la Ficha». `resaltado` marca el bloque que se está editando.
 */
defineProps({
    /** Textos ya listos: caso_cud, introduccion, requerimiento, persona, resultado_busqueda, nota_certificacion,
     * nota_general, fecha_literal. */
    campos: { type: Object, required: true },
    numero: { type: String, default: '001/2026' },
    /** Menos margen y sin alto mínimo de hoja, para mostrarla en media pantalla. */
    compacta: { type: Boolean, default: false },
    resaltado: { type: String, default: null },
});
</script>

<template>
    <div class="documento-ficha" :class="{ compacta }">
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
                <div>No: {{ numero }}</div>
                <div data-bloque="caso_cud" :class="{ 'bloque-activo': resaltado === 'caso_cud' }">{{ campos.caso_cud }}</div>
            </div>
        </div>

        <div class="titulo">FICHA DE REGISTRO</div>

        <div data-bloque="introduccion" :class="{ 'bloque-activo': resaltado === 'introduccion' }" class="parrafo">{{ campos.introduccion }}</div>
        <div class="etiqueta">REQUIERE:</div>
        <div data-bloque="requerimiento" :class="{ 'bloque-activo': resaltado === 'requerimiento' }" class="seccion sangria">{{ campos.requerimiento }}</div>
        <div data-bloque="persona" :class="{ 'bloque-activo': resaltado === 'persona' }" class="vineta negrita">{{ campos.persona }}</div>
        <div data-bloque="resultado_busqueda" :class="{ 'bloque-activo': resaltado === 'resultado_busqueda' }" class="seccion subrayado negrita justificado">{{ campos.resultado_busqueda }}</div>
        <div data-bloque="nota_certificacion" :class="{ 'bloque-activo': resaltado === 'nota_certificacion' }" class="nota justificado negrita">{{ campos.nota_certificacion }}</div>
        <div data-bloque="nota_general" :class="{ 'bloque-activo': resaltado === 'nota_general' }" class="seccion justificado">{{ campos.nota_general }}</div>
        <div data-bloque="fecha_literal" :class="{ 'bloque-activo': resaltado === 'fecha_literal' }" class="fecha">{{ campos.fecha_literal }}</div>

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
:global([data-bs-theme='dark']) .documento-ficha { box-shadow: none; }
.compacta {
    min-height: 0;
    padding: 0.35in 0.45in;
    font-size: 10.5pt;
}

[data-bloque] {
    border-radius: 2px;
    outline: 1px dashed transparent;
    outline-offset: 2px;
    transition: background-color 200ms ease-out, outline-color 200ms ease-out;
}

/* El bloque que se está editando (colores fijos: la hoja es papel blanco también en modo oscuro). */
.bloque-activo {
    background-color: rgba(64, 81, 137, 0.07);
    outline-color: rgba(64, 81, 137, 0.55);
}
</style>
