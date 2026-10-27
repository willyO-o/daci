<?php

namespace App\Http\Controllers;

use App\Http\Requests\GuardarAntecedenteRequest;
use App\Lib\ReportesPdf;
use App\Models\AuditarConsultas;
use App\Models\Division;
use App\Models\FichaAntecedente;
use App\Models\Pais;
use App\Models\Persona;
use App\Models\RegistroAntecedente;
use App\Models\Telefono;
use App\Support\Antecedentes\ModuloAntecedente;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as RespuestaHttp;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\ImageManager;

/**
 * Módulo de antecedentes (Faltas y Contravenciones): el mismo funcionamiento que Registro Criminal — listado y
 * consulta auditada con scroll infinito, formulario como página propia, detalle, ficha imprimible con PDF — sobre las
 * tablas propias del módulo. La subclase sólo dice qué módulo atiende ({@see ModuloAntecedente}).
 *
 * Aislamiento: todo registro se busca con el modelo del módulo (sus tablas), así un id de Registro Criminal da 404; la
 * ficha que se corrige se busca además por su registro.
 */
abstract class AntecedenteController extends Controller
{
    /**
     * Campos del registro que se escriben desde el formulario (los de Registro Criminal).
     *
     * @var list<string>
     */
    private const CAMPOS = [
        'fecha_registro', 'nombre_supuesto', 'alias', 'especialidad', 'edad_aproximada', 'nombre_conyuge', 'domicilio',
        'rasgos', 'modus_operandi', 'zonas_opera', 'observaciones', 'id_division', 'telefono', 'estatura', 'peso', 'cud',
        'caracteristicas_particulares', 'hijos',
    ];

    /**
     * Campos editables de la ficha.
     *
     * @var list<string>
     */
    private const CAMPOS_FICHA = [
        'caso_cud', 'introduccion', 'requerimiento', 'persona', 'resultado_busqueda', 'nota_certificacion',
        'nota_general', 'fecha_literal',
    ];

    abstract protected function modulo(): ModuloAntecedente;

    public function index(Request $request): JsonResponse|Response
    {
        if (! $request->wantsJson()) {
            return Inertia::render('Antecedentes/Index', ['modulo' => $this->modulo()->paraVista()]);
        }

        $consulta = $this->consulta()
            ->when($request->filled('search'), fn (Builder $q) => $this->buscar($q, $request->input('filtro'), (string) $request->input('search')));

        return $this->pagina($consulta, $request);
    }

    public function create(): Response
    {
        return Inertia::render('Antecedentes/Formulario', [
            'modulo' => $this->modulo()->paraVista(),
            'registro' => null,
            ...$this->catalogos(),
        ]);
    }

    public function store(GuardarAntecedenteRequest $request): RedirectResponse
    {
        $archivos = [];

        try {
            DB::transaction(function () use ($request, &$archivos) {
                $persona = $request->filled('id_persona')
                    ? Persona::find($request->integer('id_persona'))
                    : ($request->filled('ci') ? Persona::conCi($request->input('ci'), $request->input('complemento'))->orderBy('id')->first() : null);

                $persona ??= Persona::create($request->only([
                    'nombres', 'apellidos', 'ci', 'domicilio', 'telefono', 'fecha_nacimiento', 'lugar_nacimiento',
                    'complemento', 'genero', 'estado_civil', 'nombre_conyuge', 'ocupacion', 'id_pais', 'alias',
                ]));

                if (filled($request->input('telefono'))) {
                    $persona->update(['telefono' => trim($request->input('telefono'))]);
                    $this->vincularTelefono($persona, trim($request->input('telefono')));
                }

                $modelo = $this->modulo()->modelo();
                $registro = $modelo::create([...$request->only(self::CAMPOS), 'id_persona' => $persona->id]);

                $archivos[] = $this->guardarFoto($registro, $request->file('foto_frente'), 'FRONTAL');
                $archivos[] = $this->guardarFoto($registro, $request->file('foto_perfil'), 'LATERAL');
            });
        } catch (\Throwable $error) {
            // La base volvió atrás: las fotos ya escritas en el disco quedarían huérfanas.
            Storage::disk('public')->delete(array_filter($archivos));

            throw $error;
        }

        $mensaje = 'Registro creado exitosamente.';

        // «Guardar y registrar nuevo» vuelve al formulario vacío; «Guardar», al listado (si se puede ver).
        if ($request->input('accion') === 'guardar_nuevo' || ! $request->user()->can($this->modulo()->permiso('listar'))) {
            return back()->with('success', $mensaje);
        }

        return redirect()->route($this->modulo()->ruta('index'))->with('success', $mensaje);
    }

    /**
     * Detalle en JSON para el modal (suma el acceso a la auditoría de la consulta en curso).
     */
    public function show(Request $request, int $registro): JsonResponse
    {
        $registro = $this->registro($registro);

        AuditarConsultas::agregarIdsAccedidos($request->input('identificador'), $registro->id, $registro::class);

        return response()->json($this->detalle($registro));
    }

    public function showByCodigo(Request $request, string $codigo): Response
    {
        $modelo = $this->modulo()->modelo();
        $registro = $modelo::whereRaw('MD5(id) = ?', [$codigo])->firstOrFail();

        AuditarConsultas::agregarIdsAccedidos($request->input('identificador'), $registro->id, $registro::class);

        return Inertia::render('Antecedentes/Ver', [
            'modulo' => $this->modulo()->paraVista(),
            'registro' => $this->detalle($registro),
            'identificador' => $request->input('identificador'),
        ]);
    }

    public function edit(int $registro): Response
    {
        $registro = $this->registro($registro);
        $registro->load(['persona', 'fotos']);

        return Inertia::render('Antecedentes/Formulario', [
            'modulo' => $this->modulo()->paraVista(),
            'registro' => [
                ...$registro->only([...self::CAMPOS, 'id', 'nro_registro', 'id_persona']),
                'fecha_registro' => $registro->fecha_registro?->toDateString(),
                'foto_frente' => $registro->fotos->firstWhere('tipo', 'FRONTAL')?->ruta_archivo,
                'foto_perfil' => $registro->fotos->firstWhere('tipo', 'LATERAL')?->ruta_archivo,
                'persona' => $registro->persona ? [
                    ...$registro->persona->toArray(),
                    'fecha_nacimiento' => $registro->persona->fecha_nacimiento?->toDateString(),
                ] : null,
            ],
            ...$this->catalogos(),
        ]);
    }

    /**
     * Actualiza el registro. De los datos personales sólo el estado civil y la ocupación se corrigen aquí (cambian con
     * el tiempo); el resto se edita en Personas, igual que en Registro Criminal.
     */
    public function update(Request $request, int $registro): RedirectResponse
    {
        $registro = $this->registro($registro);

        $request->validate([
            'fecha_registro' => ['required', 'date'],
            'id_division' => ['required', 'exists:division,id'],
            'alias' => ['nullable', 'string', 'max:20'],
            'edad_aproximada' => ['nullable', 'string', 'max:20'],
            'nombre_supuesto' => ['nullable', 'string', 'max:250'],
            'especialidad' => ['nullable', 'string', 'max:250'],
            'nombre_conyuge' => ['nullable', 'string', 'max:250'],
            'domicilio' => ['nullable', 'string', 'max:250'],
            'telefono' => ['nullable', 'string', 'max:255'],
            'estatura' => ['nullable', 'string', 'max:255'],
            'peso' => ['nullable', 'string', 'max:255'],
            'cud' => ['nullable', 'string', 'max:255'],
            // Datos de la persona que cambian con el tiempo: se actualizan en su ficha de Personas.
            'estado_civil' => ['nullable', 'in:SOLTERO,CASADO,DIVORCIADO,VIUDO,CONYUGUE'],
            'ocupacion' => ['nullable', 'string', 'max:150'],
            'foto_frente' => ['nullable', 'image', 'max:10240'],
            'foto_perfil' => ['nullable', 'image', 'max:10240'],
        ]);

        $nuevas = [];

        try {
            $reemplazadas = DB::transaction(function () use ($request, $registro, &$nuevas) {
                $registro->update($request->only(self::CAMPOS));

                // Estado civil y ocupación son de la persona: se corrigen aquí porque cambian con el tiempo.
                $registro->persona?->update($request->only(['estado_civil', 'ocupacion']));

                if (filled($request->input('telefono')) && $registro->persona) {
                    $this->vincularTelefono($registro->persona, trim($request->input('telefono')));
                }

                return array_filter([
                    $this->reemplazarFoto($registro, $request->file('foto_frente'), 'FRONTAL', $nuevas),
                    $this->reemplazarFoto($registro, $request->file('foto_perfil'), 'LATERAL', $nuevas),
                ]);
            });
        } catch (\Throwable $error) {
            Storage::disk('public')->delete($nuevas);

            throw $error;
        }

        // Las fotos anteriores se borran del disco sólo cuando la base ya confirmó el cambio.
        Storage::disk('public')->delete($reemplazadas);

        $mensaje = 'Registro actualizado exitosamente.';

        return $request->user()->can($this->modulo()->permiso('listar'))
            ? redirect()->route($this->modulo()->ruta('index'))->with('success', $mensaje)
            : back()->with('success', $mensaje);
    }

    /**
     * Elimina el registro de forma definitiva con sus fotos y fichas, como Registro Criminal. Todo en una
     * transacción: si algo falla no se borra nada; los archivos se quitan del disco sólo después de confirmar.
     */
    public function destroy(int $registro): JsonResponse
    {
        $registro = $this->registro($registro);
        $fotos = $registro->fotos()->pluck('ruta_archivo')->all();

        DB::transaction(function () use ($registro) {
            $registro->fichas()->withTrashed()->forceDelete();
            $registro->fotos()->delete();
            $registro->forceDelete();
        });

        Storage::disk('public')->delete($fotos);

        return response()->json(['success' => 'Registro eliminado exitosamente.']);
    }

    /**
     * Consulta: filtro obligatorio y 4 caracteres; cada búsqueda nueva queda en la Auditoría de consultas.
     */
    public function consultar(Request $request): JsonResponse|Response
    {
        if (! $request->wantsJson()) {
            return Inertia::render('Consultas/Antecedentes', ['modulo' => $this->modulo()->paraVista()]);
        }

        if (blank($request->input('filtro')) || mb_strlen((string) $request->input('search')) < 4) {
            return response()->json(['datos' => [], 'total' => 0, 'page' => 1, 'last_page' => 1]);
        }

        $consulta = $this->consulta();
        $this->buscar($consulta, $request->input('filtro'), (string) $request->input('search'));
        $respuesta = $this->pagina($consulta, $request);

        if ($request->boolean('nuevo_filtro')) {
            $request->merge(['cantidad_resultados' => $respuesta->getData(true)['total']]);
            AuditarConsultas::registrar($request->user(), $this->modulo()->moduloAuditoria(), $request);
        }

        return $respuesta;
    }

    /**
     * Datos de la ficha editable: la plantilla activa del módulo con los datos del registro ya reemplazados.
     */
    public function vistaPrevia(int $registro): JsonResponse
    {
        $registro = $this->registro($registro);
        $registro->load(['persona', 'division']);
        $plantillaModelo = $this->modulo()->modeloPlantilla();
        $plantilla = $plantillaModelo::where('estado', 'ACTIVO')->orderBy('id')->firstOrFail();
        $fichaModelo = $this->modulo()->modeloFicha();
        $ahora = Carbon::now()->locale('es');

        $ultima = $fichaModelo::withTrashed()->where('anio_ficha', $ahora->year)->max('numero_ficha');
        $persona = $registro->persona;

        return response()->json([
            'registro_id' => $registro->id,
            'nro_ficha' => str_pad((string) ($ultima + 1), 3, '0', STR_PAD_LEFT).'/'.$ahora->year,
            'campos' => [
                'caso_cud' => 'CASO CUD - ...............',
                'introduccion' => Str::replace('{fecha_hora_actual}', $ahora->isoFormat('dddd DD [de] MMMM [de] YYYY, [a horas] hh:mm a'), (string) $plantilla->introduccion),
                'requerimiento' => $plantilla->requerimiento,
                'persona' => '•  '.Str::replace(['{nombre_persona}', '{ci_persona}'], [trim(($persona?->nombres ?? '').' '.($persona?->apellidos ?? '')), $persona?->ci ?? ''], (string) $plantilla->persona),
                'resultado_busqueda' => Str::replace(
                    ['{division}', '{fecha_aprehension}', '{delito}'],
                    [$registro->division?->division ?? '', $registro->fecha_registro?->isoFormat('DD/MM/YYYY') ?? '', $registro->especialidad ?? ''],
                    (string) $plantilla->resultado_busqueda,
                ),
                'nota_certificacion' => $plantilla->nota_certificacion,
                'nota_general' => $plantilla->nota_general,
                'fecha_literal' => 'La Paz, '.$ahora->isoFormat('DD [de] MMMM [de] YYYY'),
            ],
        ]);
    }

    /**
     * Guarda la ficha (nueva o la misma que se está corrigiendo) y devuelve el enlace al PDF.
     */
    public function guardarFicha(Request $request): JsonResponse
    {
        $tabla = $this->modulo()->tabla();

        $request->validate([
            'registro_id' => ['required', 'integer', Rule::exists($tabla, 'id')->whereNull('deleted_at')],
            'codigo' => ['nullable', 'string', 'size:32'],
        ]);

        $clave = $this->modulo()->claveFicha();
        $fichaModelo = $this->modulo()->modeloFicha();

        // Sólo letras, números, acentos y la puntuación de un documento (sin emojis ni marcas), igual que la ficha de
        // Registro Criminal.
        $datos = collect($request->only(self::CAMPOS_FICHA))
            ->map(fn ($valor) => is_string($valor)
                ? trim(preg_replace('/[^a-zA-Z0-9áéíóúÁÉÍÓÚñÑüÜ\s,;:\/#.\'"\-\_\(\)•\n\r\t]/u', '', $valor))
                : $valor)
            ->put($clave, $request->integer('registro_id'))
            ->all();

        // La ficha que se corrige tiene que ser del mismo registro.
        $ficha = $request->filled('codigo')
            ? $fichaModelo::whereRaw('MD5(id) = ?', [$request->input('codigo')])->where($clave, $request->integer('registro_id'))->first()
            : null;

        if ($ficha) {
            $ficha->update($datos);
        } else {
            $ficha = $fichaModelo::create($datos);
        }

        $codigo = md5((string) $ficha->id);

        return response()->json([
            'data' => ['url' => route($this->modulo()->ruta('ficha.pdf'), ['codigo' => $codigo]), 'codigo' => $codigo],
            'message' => 'Ficha de registro creada exitosamente',
        ]);
    }

    public function pdf(string $codigo): RespuestaHttp
    {
        $fichaModelo = $this->modulo()->modeloFicha();
        /** @var FichaAntecedente $ficha */
        $ficha = $fichaModelo::whereRaw('MD5(id) = ?', [$codigo])->firstOrFail();

        $contenido = (new ReportesPdf)->fichaRegistro($ficha, 'S');
        $nombre = Str::slug($this->modulo()->titulo()).'-ficha-'.str_pad((string) $ficha->numero_ficha, 3, '0', STR_PAD_LEFT).'-'.$ficha->anio_ficha.'.pdf';

        return response($contenido, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$nombre.'"',
        ]);
    }

    /**
     * Registro del módulo (se busca sólo en sus tablas).
     */
    private function registro(int $id): RegistroAntecedente
    {
        $modelo = $this->modulo()->modelo();

        return $modelo::findOrFail($id);
    }

    private function consulta(): Builder
    {
        $modelo = $this->modulo()->modelo();
        $tabla = $this->modulo()->tabla();

        return $modelo::query()
            ->with(['persona.pais:id,gentilicio', 'division:id,division', 'fotos'])
            ->orderByDesc("{$tabla}.created_at");
    }

    private function pagina(Builder $consulta, Request $request): JsonResponse
    {
        $pagina = $consulta->paginate($this->tamanoPagina($request, 6));

        return response()->json([
            'datos' => collect($pagina->items())->filter(fn (RegistroAntecedente $r) => $r->persona)->map(fn (RegistroAntecedente $r) => $this->tarjeta($r))->values(),
            'total' => $pagina->total(),
            'page' => $pagina->currentPage(),
            'last_page' => $pagina->lastPage(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function tarjeta(RegistroAntecedente $registro): array
    {
        $persona = $registro->persona;

        return [
            'id' => $registro->id,
            'nro_registro' => $registro->nro_registro,
            'fecha_registro' => $registro->fecha_registro?->toDateString(),
            'alias' => $registro->alias,
            'nombre_supuesto' => $registro->nombre_supuesto,
            'especialidad' => $registro->especialidad,
            'foto_frente' => $registro->fotos->firstWhere('tipo', 'FRONTAL')?->ruta_archivo,
            'fotos' => $registro->fotos->pluck('ruta_archivo')->values(),
            'persona' => [
                'id' => $persona->id,
                'nombres' => $persona->nombres,
                'apellidos' => $persona->apellidos,
                'ci' => $persona->ci,
                'complemento' => $persona->complemento,
                'genero' => $persona->genero,
                'edad' => $persona->fecha_nacimiento?->age,
                'nacionalidad' => $persona->pais?->gentilicio,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detalle(RegistroAntecedente $registro): array
    {
        $registro->load([
            'persona.pais:id,gentilicio',
            'persona.multimedia:id,id_persona,ruta',
            'persona.mandamientos' => fn ($q) => $q->select('id', 'id_persona', 'hoja_ruta', 'estado', 'id_delito', 'id_tipo_mandamiento')
                ->with(['delito:id,nombre_delito', 'tipoMandamiento:id,tipo_mandamiento']),
            'persona.vehiculos:vehiculo.id,placa,responsable,caso_relacionado,descripcion',
            'persona.telefonos:id,persona_id,numero_celular,persona_caso,caso,empresa',
            'division:id,division',
            'usuario:id,name',
            'fotos',
        ]);
        $persona = $registro->persona;
        $modelo = $this->modulo()->modelo();

        return [
            ...$registro->only([...self::CAMPOS, 'id', 'nro_registro']),
            'codigo' => md5((string) $registro->id),
            'fecha_registro' => $registro->fecha_registro?->toDateString(),
            'division' => $registro->division?->division,
            'registrado_por' => $registro->usuario?->name,
            'foto_frente' => $registro->fotos->firstWhere('tipo', 'FRONTAL')?->ruta_archivo,
            'foto_perfil' => $registro->fotos->firstWhere('tipo', 'LATERAL')?->ruta_archivo,
            'persona' => $persona ? [
                ...$persona->only(['id', 'nombres', 'apellidos', 'ci', 'complemento', 'alias', 'telefono', 'ocupacion', 'genero',
                    'lugar_nacimiento', 'grupo_sanguineo', 'estado_civil', 'nombre_conyuge', 'padre', 'madre', 'domicilio']),
                'fecha_nacimiento' => $persona->fecha_nacimiento?->toDateString(),
                'edad' => $persona->fecha_nacimiento?->age,
                'nacionalidad' => $persona->pais?->gentilicio,
            ] : null,
            // Otros registros de la persona en ESTE módulo.
            'otros_registros' => $persona
                ? $modelo::where('id_persona', $persona->id)->whereKeyNot($registro->id)
                    ->get(['id', 'nro_registro', 'fecha_registro', 'edad_aproximada', 'especialidad'])
                    ->map(fn (RegistroAntecedente $otro) => [
                        'id' => $otro->id,
                        'codigo' => md5((string) $otro->id),
                        'nro_registro' => $otro->nro_registro,
                        'fecha_registro' => $otro->fecha_registro?->toDateString(),
                        'edad_aproximada' => $otro->edad_aproximada,
                        'especialidad' => $otro->especialidad,
                    ])
                : [],
            'mandamientos' => $persona?->mandamientos->map(fn ($m) => [
                'id' => $m->id,
                'codigo' => md5((string) $m->id),
                'hoja_ruta' => $m->hoja_ruta,
                'delito' => $m->delito?->nombre_delito,
                'tipo' => $m->tipoMandamiento?->tipo_mandamiento,
                'estado' => $m->estado,
            ]) ?? [],
            'vehiculos' => $persona?->vehiculos ?? [],
            'telefonos' => $persona?->telefonos ?? [],
        ];
    }

    /**
     * Mismos filtros que Registro Criminal (lista blanca).
     */
    private function buscar(Builder $consulta, ?string $filtro, string $texto): void
    {
        $como = '%'.str_replace(' ', '%', $texto).'%';

        $consulta->where(function (Builder $q) use ($filtro, $texto, $como) {
            match ($filtro) {
                'nombres' => $q->whereHas('persona', fn (Builder $p) => $p->whereRaw("CONCAT(COALESCE(nombres, ''), ' ', COALESCE(apellidos, '')) LIKE ?", [$como])),
                'apellidos' => $q->whereHas('persona', fn (Builder $p) => $p->where('apellidos', 'like', $como)),
                'alias' => $q->where('alias', 'like', $como)->orWhereHas('persona', fn (Builder $p) => $p->where('alias', 'like', $como)),
                'ci' => $q->whereHas('persona', fn (Builder $p) => $p->where('ci', 'like', $como)),
                'celular' => $q->where('telefono', 'like', $como)->orWhereHas('persona.telefonos', fn (Builder $t) => $t->where('numero_celular', 'like', $como)),
                'cud' => $q->where('cud', 'like', $como),
                'conyuge' => $q->where('nombre_conyuge', 'like', $como),
                'padre' => $q->whereHas('persona', fn (Builder $p) => $p->where('padre', 'like', $como)),
                'madre' => $q->whereHas('persona', fn (Builder $p) => $p->where('madre', 'like', $como)),
                'nombre_supuesto' => $q->where('nombre_supuesto', 'like', $como),
                'hijos' => $q->where('hijos', 'like', $como),
                'nacimiento' => preg_match('/^\d{2}-\d{2}-\d{4}$/', $texto)
                    ? $q->whereHas('persona', fn (Builder $p) => $p->whereDate('fecha_nacimiento', Carbon::createFromFormat('d-m-Y', $texto)->toDateString()))
                    : $q->whereRaw('1 = 0'),
                default => $q->whereHas('persona', fn (Builder $p) => $p->whereRaw("CONCAT(COALESCE(nombres, ''), ' ', COALESCE(apellidos, '')) LIKE ?", [$como])
                    ->orWhere('ci', 'like', $como)
                    ->orWhere('alias', 'like', $como)
                    ->orWhere('telefono', 'like', $como))
                    ->orWhere('alias', 'like', $como)
                    ->orWhere('nombre_supuesto', 'like', $como),
            };
        });
    }

    private function vincularTelefono(Persona $persona, string $numero): void
    {
        $telefono = Telefono::firstOrCreate(['numero_celular' => $numero], ['persona_id' => $persona->id]);

        if ($telefono->persona_id !== $persona->id) {
            $telefono->update(['persona_id' => $persona->id]);
        }
    }

    /**
     * Foto convertida a WebP (calidad 80) en la carpeta del módulo; devuelve la ruta guardada.
     */
    private function guardarFoto(RegistroAntecedente $registro, ?UploadedFile $foto, string $tipo): ?string
    {
        if (! $foto) {
            return null;
        }

        $ruta = $this->modulo()->carpetaFotos().'/img_'.Str::uuid()->toString().'.webp';
        $imagen = (new ImageManager(new ImagickDriver))->read($foto->getPathname());
        Storage::disk('public')->put($ruta, (string) $imagen->toWebp(quality: 80));

        $registro->fotos()->create([
            'tipo' => $tipo,
            'ruta_archivo' => $ruta,
            'id_usuario' => auth()->id(),
        ]);

        return $ruta;
    }

    /**
     * Cambia la foto de un tipo y devuelve la ruta de la anterior, que se borra del disco tras confirmar.
     *
     * @param  list<string>  $nuevas
     */
    private function reemplazarFoto(RegistroAntecedente $registro, ?UploadedFile $foto, string $tipo, array &$nuevas): ?string
    {
        if (! $foto) {
            return null;
        }

        $anterior = $registro->fotos()->where('tipo', $tipo)->first();
        $nuevas[] = $this->guardarFoto($registro, $foto, $tipo);
        $anterior?->delete();

        return $anterior?->ruta_archivo;
    }

    /**
     * @return array<string, mixed>
     */
    private function catalogos(): array
    {
        return [
            'paises' => Pais::query()->orderBy('gentilicio')->get(['id', 'gentilicio']),
            'divisiones' => Division::query()->orderBy('division')->get(['id', 'division']),
        ];
    }
}
