/**
 * Formatos de presentación compartidos por todas las pantallas (fechas en es-BO, nombres, CI, rutas de archivos).
 * Lo que antes repetía cada `index.js` del sistema anterior.
 */

const fechaCorta = new Intl.DateTimeFormat('es-BO', { year: 'numeric', month: 'short', day: 'numeric' });
const fechaNumerica = new Intl.DateTimeFormat('es-BO', { year: 'numeric', month: '2-digit', day: '2-digit' });
const fechaHoraNumerica = new Intl.DateTimeFormat('es-BO', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
});

/** Interpreta `YYYY-MM-DD` como fecha local (sin correr un día por la zona horaria). */
const aFecha = (valor) => {
    if (!valor) {
        return null;
    }
    if (typeof valor === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(valor)) {
        const [anio, mes, dia] = valor.split('-').map(Number);

        return new Date(anio, mes - 1, dia);
    }
    const fecha = new Date(valor);

    return Number.isNaN(fecha.getTime()) ? null : fecha;
};

/** «12 mar. 2026» */
export const fecha = (valor) => {
    const f = aFecha(valor);

    return f ? fechaCorta.format(f) : '—';
};

/** «12/03/2026» */
export const fechaNum = (valor) => {
    const f = aFecha(valor);

    return f ? fechaNumerica.format(f) : '—';
};

/** «12/03/2026 14:05» */
export const fechaHora = (valor) => {
    const f = aFecha(valor);

    return f ? fechaHoraNumerica.format(f) : '—';
};

/** Fecha para un <input type="date"> (`YYYY-MM-DD`). */
export const fechaInput = (valor) => (valor ? String(valor).substring(0, 10) : '');

/** Hoy en la hora local del navegador (`YYYY-MM-DD`), no en UTC. */
export const hoyIso = () => {
    const ahora = new Date();

    return `${ahora.getFullYear()}-${String(ahora.getMonth() + 1).padStart(2, '0')}-${String(ahora.getDate()).padStart(2, '0')}`;
};

export const nombreCompleto = (persona) => `${persona?.nombres ?? ''} ${persona?.apellidos ?? ''}`.trim();

export const ciCompleto = (persona) =>
    persona?.ci ? `${persona.ci}${persona.complemento ? `-${persona.complemento}` : ''}` : '—';

export const iniciales = (texto) =>
    (texto ?? '')
        .split(' ')
        .filter(Boolean)
        .map((palabra) => palabra.charAt(0).toUpperCase())
        .join('')
        .substring(0, 2);

/** Ruta de un archivo del disco `public` (`storage/app/public`). */
export const urlArchivo = (ruta) => (ruta ? `/storage/${ruta}` : null);

export const guion = (valor) => (valor === null || valor === undefined || valor === '' ? '—' : valor);

export const GENEROS = { MASCULINO: 'Masculino', FEMENINO: 'Femenino' };

export const ESTADOS_CIVILES = {
    SOLTERO: 'Soltero/a',
    CASADO: 'Casado/a',
    DIVORCIADO: 'Divorciado/a',
    VIUDO: 'Viudo/a',
    CONYUGUE: 'Cónyuge',
};

const numero = new Intl.NumberFormat('es-BO');

export const cifra = (valor) => numero.format(valor ?? 0);

const dinero = new Intl.NumberFormat('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

export const monto = (valor) => (valor === null || valor === undefined || valor === '' ? '—' : dinero.format(Number(valor)));
