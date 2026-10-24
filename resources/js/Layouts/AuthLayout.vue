<script setup>
import '@fontsource/barlow/latin-500.css';
import '@fontsource/barlow/latin-500-italic.css';
import '@fontsource/barlow/latin-600.css';
import '@fontsource/barlow/latin-700.css';
import '../../css/acceso.css';

/**
 * Layout de las pantallas de acceso («propuesta 5»): foto del Palacio con la bandera, encabezado institucional con
 * la franja tricolor y el lema al pie. Cada página pone su tarjeta (`TarjetaAcceso`) en el slot.
 */
const anio = new Date().getFullYear();
</script>

<template>
    <div class="acceso">
        <img class="acceso-fondo" src="/felcc/img/login-fondo.webp" alt="" fetchpriority="high" decoding="async">

        <header class="acceso-cabecera">
            <div class="acceso-institucion">
                <span class="acceso-tricolor" aria-hidden="true" />
                <div>
                    <p class="acceso-institucion-nombre">POLICÍA BOLIVIANA</p>
                    <p class="acceso-institucion-detalle">Fuerza Especial de Lucha Contra el Crimen</p>
                    <p class="acceso-institucion-detalle">Dirección de Análisis de Información Criminal</p>
                </div>
            </div>

            <div class="acceso-marca">
                <i class="ri-shield-check-line" aria-hidden="true" />
                <span>
                    <span class="acceso-marca-nombre">FELCC - DACI</span>
                    <span class="acceso-marca-lema">Análisis · Investigación · Resultados</span>
                </span>
            </div>
        </header>

        <main class="acceso-contenido">
            <slot />
            <p class="acceso-lema">Por una Bolivia más segura</p>
        </main>

        <footer class="acceso-pie">&copy; {{ anio }} FELCC<span class="acceso-pie-detalle"> · Dirección de Análisis de Información Criminal</span></footer>
    </div>
</template>
