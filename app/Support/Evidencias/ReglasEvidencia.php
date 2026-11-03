<?php

namespace App\Support\Evidencias;

/**
 * Valores y reglas del módulo Evidencias (D-038), en un solo lugar: los usan las validaciones, los controladores y
 * (por `paraVista()`) las pantallas. Los valores se guardan en MAYÚSCULA como texto validado, no como `enum` de MySQL.
 */
final class ReglasEvidencia
{
    public const ESTADOS = ['REGISTRADA', 'CERRADA', 'ANULADA'];

    public const ESTADOS_FISICOS = ['BUENO', 'REGULAR', 'MALO', 'INSERVIBLE'];

    /** El ciclo de publicación de cada artículo. Ninguno se publica solo. */
    public const NO_PUBLICAR = 'NO_PUBLICAR';

    public const PENDIENTE = 'PENDIENTE_REVISION';

    public const PUBLICADO = 'PUBLICADO';

    public const RETIRADO = 'RETIRADO';

    public const ESTADOS_PUBLICACION = [self::NO_PUBLICAR, self::PENDIENTE, self::PUBLICADO, self::RETIRADO];

    /**
     * Grupo del tipo de artículo => campos propios que pide el formulario. Los comunes (tipo, descripción, estado físico,
     * funcionario que recoge, fecha de recepción, observaciones) van siempre. Al guardar, los campos que no son del
     * grupo se dejan vacíos: un MATERIAL nunca queda con un IMEI de un tipo elegido antes.
     *
     * @var array<string, array{etiqueta: string, campos: list<string>}>
     */
    public const GRUPOS = [
        'CELULAR' => ['etiqueta' => 'Celular o tablet (con IMEI)', 'campos' => ['marca', 'modelo', 'color', 'imei_1', 'imei_2', 'numero_serie']],
        'EQUIPO' => ['etiqueta' => 'Equipo con número de serie', 'campos' => ['marca', 'modelo', 'color', 'numero_serie']],
        'MATERIAL' => ['etiqueta' => 'Material (cantidad y unidad)', 'campos' => ['cantidad', 'unidad_medida']],
        'OTRO' => ['etiqueta' => 'Otro (descripción)', 'campos' => ['color', 'cantidad']],
    ];

    /** Campos que dependen del grupo. */
    public const CAMPOS_DE_GRUPO = ['marca', 'modelo', 'color', 'imei_1', 'imei_2', 'numero_serie', 'cantidad', 'unidad_medida'];

    /** Grupos en los que la descripción es obligatoria (no hay marca ni modelo que identifiquen el artículo). */
    public const DESCRIPCION_OBLIGATORIA = ['MATERIAL', 'OTRO'];

    /**
     * Datos de un artículo que verían los ciudadanos: si cambian en uno PUBLICADO, vuelve a revisión.
     */
    public const CAMPOS_PUBLICOS = ['tipo_articulo_id', 'descripcion', 'marca', 'modelo', 'color', 'imei_1', 'imei_2', 'numero_serie'];

    public const UNIDADES = ['UNIDAD', 'PAR', 'JUEGO', 'CAJA', 'PAQUETE', 'BOLSA', 'KG', 'G', 'LITRO', 'METRO'];

    /**
     * Tipos de archivo => etiqueta y si es privado (sólo con `evidencias.documentos`).
     *
     * @var array<string, array{etiqueta: string, privado: bool}>
     */
    public const TIPOS_ARCHIVO = [
        'FOTO_ARTICULO' => ['etiqueta' => 'Fotografía del artículo', 'privado' => false],
        'FOTO_IMEI' => ['etiqueta' => 'Fotografía del IMEI', 'privado' => false],
        'ACTA' => ['etiqueta' => 'Acta de secuestro', 'privado' => true],
        'COMPROBANTE' => ['etiqueta' => 'Comprobante de compra', 'privado' => true],
        'DOCUMENTO' => ['etiqueta' => 'Documento de respaldo', 'privado' => true],
    ];

    /** Sólo estas fotografías pueden marcarse como públicas (la del IMEI lo mostraría completo). */
    public const TIPOS_PUBLICABLES = ['FOTO_ARTICULO'];

    /** Tipos de archivo de imagen (se guardan como WebP reducido, sin metadatos como el GPS). */
    public const TIPOS_FOTO = ['FOTO_ARTICULO', 'FOTO_IMEI'];

    /**
     * Archivos por envío. PHP descarta en silencio los que pasan de `max_file_uploads` (20 por defecto): el
     * formulario y la validación no dejan pasar más.
     */
    public const MAX_ARCHIVOS_POR_ENVIO = 20;

    public const MAX_MB_FOTO = 5;

    public const MAX_MB_DOCUMENTO = 10;

    /** Lado mayor de una foto guardada, en píxeles. */
    public const LADO_MAXIMO_FOTO = 2000;

    public static function esPrivado(string $tipo): bool
    {
        return self::TIPOS_ARCHIVO[$tipo]['privado'] ?? true;
    }

    /**
     * Lo que las pantallas necesitan (etiquetas, campos por grupo, límites).
     *
     * @return array<string, mixed>
     */
    public static function paraVista(): array
    {
        return [
            'estados' => self::ESTADOS,
            'estadosFisicos' => self::ESTADOS_FISICOS,
            'estadosPublicacion' => self::ESTADOS_PUBLICACION,
            'grupos' => self::GRUPOS,
            'descripcionObligatoria' => self::DESCRIPCION_OBLIGATORIA,
            'unidades' => self::UNIDADES,
            'tiposArchivo' => self::TIPOS_ARCHIVO,
            'maxArchivos' => self::MAX_ARCHIVOS_POR_ENVIO,
            'maxMbFoto' => self::MAX_MB_FOTO,
            'maxMbDocumento' => self::MAX_MB_DOCUMENTO,
        ];
    }
}
