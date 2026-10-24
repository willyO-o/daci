/**
 * Pantalla de carga de la plantilla (`#preloader` en `app.blade.php`). Sólo se ve con `data-preloader="enable"`
 * (ajuste del panel «Personalizar tema»); la plantilla la ocultaba al terminar de cargar la página.
 */
export const ocultarPreloader = ({ mostrarAntes = false, espera = 1000 } = {}) => {
    const preloader = document.getElementById('preloader');
    if (!preloader) {
        return;
    }

    if (mostrarAntes) {
        preloader.style.opacity = '1';
        preloader.style.visibility = 'visible';
    }

    setTimeout(() => {
        preloader.style.opacity = '0';
        preloader.style.visibility = 'hidden';
    }, espera);
};
