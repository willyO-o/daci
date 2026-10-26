/** Color de la plantilla para cada estado de mandamiento (los demás, gris). */
const COLORES = {
    PENDIENTE: 'info',
    EJECUTADO: 'success',
    CANCELADO: 'danger',
};

export const colorEstado = (estado) => COLORES[estado] ?? 'secondary';

/** El estado pide datos de ejecución si contiene «EJECUTADO» (mismo criterio que el servidor). */
export const esEjecutado = (estado) => (estado ?? '').toUpperCase().includes('EJECUTADO');

/** Filtros de búsqueda del listado y la consulta de mandamientos (mismos valores que el servidor). */
export const FILTROS_MANDAMIENTO = [
    { valor: 'hoja_ruta', etiqueta: 'Hoja de Ruta' },
    { valor: 'nombre_persona', etiqueta: 'Nombre' },
    { valor: 'apellidos', etiqueta: 'Apellidos' },
    { valor: 'ci', etiqueta: 'C.I.' },
    { valor: 'estado', etiqueta: 'Estado' },
    { valor: 'nombre_delito', etiqueta: 'Delito' },
    { valor: 'tipo_mandamiento', etiqueta: 'Tipo de Mandamiento' },
    { valor: 'nombre_juzgado', etiqueta: 'Juzgado' },
    { valor: 'encargado', etiqueta: 'Encargado' },
];
