/**
 * Filtros de escritura de los formularios (las clases `txtMayuscula`, `txtNumero`, `sinEspacios`, `txtNormal`,
 * `txtDesc` y `txtDecimal` de `principal.js` del sistema anterior) como directivas de Vue, compatibles con v-model:
 *
 *   <input v-model="form.nombres" v-mayuscula>
 *   <input v-model="form.ci" v-solo-numeros>
 */

const crearFiltro = (transformar) => ({
    mounted(el) {
        el.addEventListener('input', (evento) => {
            // El evento que despachamos nosotros (no confiable) ya trae el valor limpio: v-model lo recoge.
            if (!evento.isTrusted) {
                return;
            }

            const limpio = transformar(el.value);
            if (limpio === el.value) {
                return;
            }

            const cursor = el.selectionStart;
            const diferencia = el.value.length - limpio.length;
            el.value = limpio;
            if (cursor !== null && typeof el.setSelectionRange === 'function' && el.type !== 'email' && el.type !== 'number') {
                const posicion = Math.max(0, cursor - diferencia);
                el.setSelectionRange(posicion, posicion);
            }
            el.dispatchEvent(new Event('input'));
        });
    },
});

const decimal = (valor) => {
    let texto = valor.replace(/[^0-9.,]/g, '').replace(/,/g, '.');
    const primerPunto = texto.indexOf('.');

    if (primerPunto !== -1) {
        const entero = texto.substring(0, primerPunto);
        const fraccion = texto.substring(primerPunto + 1).replace(/\./g, '').slice(0, 2);
        texto = `${entero.slice(0, 6)}.${fraccion}`;
    } else {
        texto = texto.slice(0, 6);
    }

    return texto;
};

export const directivas = {
    mayuscula: crearFiltro((valor) => valor.toUpperCase()),
    soloNumeros: crearFiltro((valor) => valor.replace(/[^0-9]/g, '')),
    sinEspacios: crearFiltro((valor) => valor.replace(/ /g, '')),
    textoNormal: crearFiltro((valor) => valor.replace(/[^a-zA-Z0-9áéíóúÁÉÍÓÚñÑ.*/\-_+#:;()\r\n ]/g, '')),
    textoDescripcion: crearFiltro((valor) => valor.replace(/[^a-zA-Z0-9áéíóúÁÉÍÓÚñÑ.,*/\-_+#:;$()%\r\n ]/g, '')),
    decimal: crearFiltro(decimal),
};

export default {
    install(app) {
        app.directive('mayuscula', directivas.mayuscula);
        app.directive('solo-numeros', directivas.soloNumeros);
        app.directive('sin-espacios', directivas.sinEspacios);
        app.directive('texto-normal', directivas.textoNormal);
        app.directive('texto-descripcion', directivas.textoDescripcion);
        app.directive('decimal', directivas.decimal);
    },
};
