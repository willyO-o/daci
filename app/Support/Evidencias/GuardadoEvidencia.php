<?php

namespace App\Support\Evidencias;

use App\Models\Evidencia;
use App\Models\EvidenciaArchivo;
use App\Models\EvidenciaArticulo;
use App\Models\TipoArticulo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\ImageManager;
use Throwable;

/**
 * Guarda una evidencia con sus artículos y archivos en UNA transacción (D-038):
 *
 *  - Artículos: los que vienen con `id` se actualizan (sólo si son de esta evidencia), los nuevos se crean y los que ya
 *    no vienen se eliminan (eliminación lógica, con sus archivos). Los campos que no son del grupo del tipo se vacían.
 *  - Publicación: «Solicitar publicación» pasa el artículo a PENDIENTE_REVISION (nunca a PUBLICADO). Si cambian los
 *    datos que vería el ciudadano de uno PUBLICADO (o la fecha de la evidencia), vuelve a revisión. Una evidencia ANULADA
 *    retira sus artículos publicados y saca de revisión los pendientes.
 *  - Archivos: en el disco privado, `evidencias/{id}/`. Las imágenes se guardan como WebP reducido (lo que además quita
 *    los metadatos, como la ubicación GPS de una foto de celular). Si algo falla, se borran los archivos recién escritos
 *    y la base queda como estaba.
 */
final class GuardadoEvidencia
{
    /** Archivos escritos en esta operación (se borran si falla). */
    private array $escritos = [];

    /**
     * @param  array<string, mixed>  $datos  validados por `GuardarEvidenciaRequest`
     * @param  array<string, mixed>  $archivos  `$request->allFiles()`
     */
    public function guardar(array $datos, array $archivos, ?Evidencia $evidencia = null): Evidencia
    {
        $this->escritos = [];

        try {
            return DB::transaction(function () use ($datos, $archivos, $evidencia) {
                $evidencia ??= new Evidencia;
                $fechaAnterior = $evidencia->exists ? $evidencia->fecha_hora?->format('Y-m-d H:i') : null;

                $evidencia->fill([
                    'codigo' => $this->texto($datos['codigo'] ?? null),
                    'id_caso' => $datos['id_caso'] ?? null,
                    'id_division' => $datos['id_division'] ?? null,
                    'fecha_hora' => $datos['fecha_hora'],
                    'lugar' => $this->texto($datos['lugar']),
                    'detalle_lugar' => $this->libre($datos['detalle_lugar'] ?? null),
                    'funcionario_responsable' => $this->texto($datos['funcionario_responsable']),
                    'numero_acta' => $this->texto($datos['numero_acta'] ?? null),
                    'observaciones' => $this->libre($datos['observaciones'] ?? null),
                    'estado' => $datos['estado'],
                ])->save();

                $cambioFecha = $fechaAnterior !== null && $fechaAnterior !== $evidencia->fecha_hora?->format('Y-m-d H:i');

                $this->guardarArticulos($evidencia, $datos['articulos'], $archivos['articulos'] ?? [], $cambioFecha);

                foreach (['actas' => 'ACTA', 'documentos' => 'DOCUMENTO'] as $campo => $tipo) {
                    foreach ($archivos[$campo] ?? [] as $archivo) {
                        $this->guardarArchivo($evidencia, null, $archivo, $tipo);
                    }
                }

                if ($evidencia->estado === 'ANULADA') {
                    self::retirarPublicaciones($evidencia, 'Evidencia anulada.');
                }

                return $evidencia;
            });
        } catch (Throwable $error) {
            Storage::disk(EvidenciaArchivo::DISCO)->delete($this->escritos);

            throw $error;
        }
    }

    /**
     * Saca del portal los artículos de una evidencia anulada o eliminada: PUBLICADO → RETIRADO y PENDIENTE → NO_PUBLICAR.
     */
    public static function retirarPublicaciones(Evidencia $evidencia, string $motivo): void
    {
        $evidencia->articulos()->where('estado_publicacion', ReglasEvidencia::PUBLICADO)->get()
            ->each(fn (EvidenciaArticulo $a) => $a->update(['estado_publicacion' => ReglasEvidencia::RETIRADO, 'motivo_publicacion' => $motivo]));

        $evidencia->articulos()->where('estado_publicacion', ReglasEvidencia::PENDIENTE)->get()
            ->each(fn (EvidenciaArticulo $a) => $a->update(['estado_publicacion' => ReglasEvidencia::NO_PUBLICAR, 'motivo_publicacion' => $motivo]));
    }

    /**
     * @param  list<array<string, mixed>>  $articulos
     * @param  array<int, array<string, mixed>>  $archivos
     */
    private function guardarArticulos(Evidencia $evidencia, array $articulos, array $archivos, bool $cambioFecha): void
    {
        $existentes = $evidencia->articulos()->get()->keyBy('id');
        $grupos = TipoArticulo::query()->whereIn('id', collect($articulos)->pluck('tipo_articulo_id'))->pluck('grupo', 'id');
        $conservados = [];

        foreach ($articulos as $indice => $datos) {
            $articulo = null;

            if (filled($datos['id'] ?? null)) {
                $articulo = $existentes->get((int) $datos['id']);

                if (! $articulo) {
                    throw ValidationException::withMessages(["articulos.{$indice}.tipo_articulo_id" => 'Este artículo ya no pertenece a la evidencia. Vuelva a abrir el formulario.']);
                }
            }

            $articulo ??= new EvidenciaArticulo(['evidencia_id' => $evidencia->id]);
            $articulo->fill($this->camposArticulo($datos, $grupos[$datos['tipo_articulo_id']] ?? 'OTRO'));
            $this->decidirPublicacion($articulo, (bool) ($datos['solicitar_publicacion'] ?? false), $cambioFecha);
            $articulo->save();
            $conservados[] = $articulo->id;

            foreach (['fotos' => 'FOTO_ARTICULO', 'fotos_imei' => 'FOTO_IMEI', 'comprobantes' => 'COMPROBANTE'] as $campo => $tipo) {
                foreach ($archivos[$indice][$campo] ?? [] as $archivo) {
                    $this->guardarArchivo($evidencia, $articulo, $archivo, $tipo);
                }
            }
        }

        // Los que ya no están en el formulario: eliminación lógica, con sus archivos (los archivos no se borran del disco).
        $existentes->except($conservados)->each(function (EvidenciaArticulo $articulo) {
            $articulo->archivos()->get()->each->delete();
            $articulo->delete();
        });
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private function camposArticulo(array $datos, string $grupo): array
    {
        $propios = ReglasEvidencia::GRUPOS[$grupo]['campos'] ?? [];

        $campos = [
            'tipo_articulo_id' => (int) $datos['tipo_articulo_id'],
            'descripcion' => $this->texto($datos['descripcion'] ?? null),
            'marca' => $this->texto($datos['marca'] ?? null),
            'modelo' => $this->texto($datos['modelo'] ?? null),
            'color' => $this->texto($datos['color'] ?? null),
            'imei_1' => $datos['imei_1'] ?? null,
            'imei_2' => $datos['imei_2'] ?? null,
            'numero_serie' => $this->texto($datos['numero_serie'] ?? null),
            'cantidad' => $datos['cantidad'] ?? null,
            'unidad_medida' => $this->texto($datos['unidad_medida'] ?? null),
            'estado_fisico' => $datos['estado_fisico'] ?? null,
            'funcionario_recoge' => $this->texto($datos['funcionario_recoge'] ?? null),
            'fecha_recepcion' => $datos['fecha_recepcion'] ?? null,
            'observaciones' => $this->libre($datos['observaciones'] ?? null),
        ];

        // Lo que no es del grupo, vacío (y la cantidad, 1).
        foreach (ReglasEvidencia::CAMPOS_DE_GRUPO as $campo) {
            if (! in_array($campo, $propios, true)) {
                $campos[$campo] = $campo === 'cantidad' ? 1 : null;
            }
        }

        $campos['cantidad'] = filled($campos['cantidad']) ? $campos['cantidad'] : 1;

        return $campos;
    }

    private function decidirPublicacion(EvidenciaArticulo $articulo, bool $solicitar, bool $cambioFecha): void
    {
        $estado = $articulo->estado_publicacion ?? ReglasEvidencia::NO_PUBLICAR;

        if ($estado === ReglasEvidencia::PUBLICADO) {
            if ($cambioFecha || $articulo->isDirty(ReglasEvidencia::CAMPOS_PUBLICOS)) {
                $articulo->forceFill([
                    'estado_publicacion' => ReglasEvidencia::PENDIENTE,
                    'publicado_at' => null,
                    'publicado_por' => null,
                    'motivo_publicacion' => 'Se modificaron datos publicados: debe revisarse de nuevo.',
                ]);
            }

            return;
        }

        if ($solicitar && in_array($estado, [ReglasEvidencia::NO_PUBLICAR, ReglasEvidencia::RETIRADO], true)) {
            $articulo->forceFill(['estado_publicacion' => ReglasEvidencia::PENDIENTE, 'motivo_publicacion' => null]);
        } elseif (! $solicitar && $estado === ReglasEvidencia::PENDIENTE) {
            $articulo->forceFill(['estado_publicacion' => ReglasEvidencia::NO_PUBLICAR, 'motivo_publicacion' => null]);
        } elseif (! $articulo->exists) {
            $articulo->forceFill(['estado_publicacion' => ReglasEvidencia::NO_PUBLICAR]);
        }
    }

    private function guardarArchivo(Evidencia $evidencia, ?EvidenciaArticulo $articulo, UploadedFile $archivo, string $tipo): void
    {
        $esImagen = str_starts_with((string) $archivo->getMimeType(), 'image/');
        $nombre = (string) Str::ulid();
        $disco = Storage::disk(EvidenciaArchivo::DISCO);

        if ($esImagen) {
            $imagen = (new ImageManager(new ImagickDriver))->read($archivo->getPathname())
                ->scaleDown(ReglasEvidencia::LADO_MAXIMO_FOTO, ReglasEvidencia::LADO_MAXIMO_FOTO);
            $ruta = "evidencias/{$evidencia->id}/{$nombre}.webp";
            $disco->put($ruta, (string) $imagen->toWebp(quality: 80));
            $mime = 'image/webp';
        } else {
            $ruta = "evidencias/{$evidencia->id}/{$nombre}.pdf";
            $disco->putFileAs("evidencias/{$evidencia->id}", $archivo, "{$nombre}.pdf");
            $mime = 'application/pdf';
        }

        $this->escritos[] = $ruta;

        EvidenciaArchivo::create([
            'evidencia_id' => $evidencia->id,
            'evidencia_articulo_id' => $articulo?->id,
            'tipo' => $tipo,
            'nombre_archivo' => Str::limit(basename($archivo->getClientOriginalName()), 250, ''),
            'ruta' => $ruta,
            'mime' => $mime,
            'tamano' => $disco->size($ruta),
            'es_publico' => false,
            'user_id' => auth()->id(),
        ]);
    }

    /** Texto corto como lo guarda el sistema: espacios unificados y MAYÚSCULA. */
    private function texto(?string $valor): ?string
    {
        $limpio = campoDB($valor);

        return filled($limpio) ? $limpio : null;
    }

    /** Texto largo: sólo sin espacios a los costados. */
    private function libre(?string $valor): ?string
    {
        $limpio = trim((string) $valor);

        return $limpio === '' ? null : $limpio;
    }
}
