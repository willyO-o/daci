/**
 * Espera desde la última tecla antes de que un buscador SIN botón consulte al servidor (listados, buscadores de los
 * formularios, verificación de C.I.). Evita una petición por cada letra; cada búsqueda nueva cancela la anterior.
 */
export const ESPERA_BUSQUEDA = 900;
