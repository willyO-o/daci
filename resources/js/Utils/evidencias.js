/**
 * Etiquetas y colores del módulo Evidencias. Los valores y reglas vienen del servidor (`ReglasEvidencia::paraVista()`,
 * prop `reglas`); aquí sólo cómo se muestran.
 */

export const ETIQUETA_ESTADO = { REGISTRADA: 'Registrada', CERRADA: 'Cerrada', ANULADA: 'Anulada' };

export const CLASE_ESTADO = {
    REGISTRADA: 'bg-soft-primary text-primary',
    CERRADA: 'bg-soft-success text-success',
    ANULADA: 'bg-soft-danger text-danger',
};

export const ETIQUETA_PUBLICACION = {
    NO_PUBLICAR: 'No publicar',
    PENDIENTE_REVISION: 'Pendiente de revisión',
    PUBLICADO: 'Publicado',
    RETIRADO: 'Retirado',
};

export const CLASE_PUBLICACION = {
    NO_PUBLICAR: 'bg-soft-secondary text-secondary',
    PENDIENTE_REVISION: 'bg-soft-warning text-warning',
    PUBLICADO: 'bg-soft-success text-success',
    RETIRADO: 'bg-soft-danger text-danger',
};

export const ICONO_GRUPO = {
    CELULAR: 'mdi mdi-cellphone',
    EQUIPO: 'mdi mdi-laptop',
    MATERIAL: 'mdi mdi-package-variant-closed',
    OTRO: 'mdi mdi-shape-outline',
};

/** Campos propios del grupo de un tipo (`reglas.grupos[grupo].campos`). */
export const camposDelGrupo = (reglas, grupo) => reglas?.grupos?.[grupo]?.campos ?? [];

/** Título corto de un artículo: «TELÉFONO CELULAR · SAMSUNG A12» o su descripción. */
export const tituloArticulo = (articulo, tipo = articulo?.tipo) => {
    const marcaModelo = [articulo?.marca, articulo?.modelo].filter(Boolean).join(' ');

    return [tipo?.nombre, marcaModelo || articulo?.descripcion].filter(Boolean).join(' · ') || 'Artículo';
};

/** Tamaño legible de un archivo. */
export const tamano = (bytes) => {
    if (!bytes) {
        return '';
    }
    if (bytes < 1024 * 1024) {
        return `${Math.max(1, Math.round(bytes / 1024))} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
};
