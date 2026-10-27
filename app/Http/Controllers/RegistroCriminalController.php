<?php

namespace App\Http\Controllers;

use App\Http\Requests\GuardarRegistroCriminalRequest;
use App\Models\AuditarConsultas;
use App\Models\Division;
use App\Models\FichaRegistro;
use App\Models\FichaRegistroPlantilla;
use App\Models\FotosRegistro;
use App\Models\Pais;
use App\Models\Persona;
use App\Models\RegistroCriminal;
use App\Models\Telefono;
use App\Models\VehiculoCaso;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\ImageManager;

/**
 * Registro criminal (EREBOR). Listado y consulta con scroll infinito en JSON; formulario como página propia.
 */
class RegistroCriminalController extends Controller
{
    /**
     * Campos del registro que se escriben desde el formulario.
     *
     * @var list<string>
     */
    private const CAMPOS = [
        'fecha_registro', 'nombre_supuesto', 'alias', 'especialidad', 'edad_aproximada', 'nombre_conyuge', 'domicilio',
        'rasgos', 'modus_operandi', 'zonas_opera', 'observaciones', 'id_division', 'telefono', 'estatura', 'peso', 'cud',
        'caracteristicas_particulares', 'hijos',
    ];

    public function index(Request $request): JsonResponse|Response
    {
        if (! $request->wantsJson()) {
            return Inertia::render('RegistroCriminal/Index');
        }

        $consulta = $this->consulta()
            ->when($request->filled('search'), fn (Builder $q) => $this->buscar($q, $request->input('filtro'), (string) $request->input('search')));

        return $this->pagina($consulta, $request);
    }

    public function create(): Response
    {
        return Inertia::render('RegistroCriminal/Formulario', [
            'registro' => null,
            ...$this->catalogos(),
        ]);
    }

    public function store(GuardarRegistroCriminalRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
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

            $registro = RegistroCriminal::create([...$request->only(self::CAMPOS), 'id_persona' => $persona->id]);

            $this->guardarFoto($registro, $request->file('foto_frente'), 'FRONTAL');
            $this->guardarFoto($registro, $request->file('foto_perfil'), 'LATERAL');
        });

        // «Guardar y registrar nuevo» vuelve al formulario vacío; «Guardar» vuelve al listado.
        if ($request->input('accion') === 'guardar_nuevo') {
            return back()->with('success', 'Registro creado exitosamente.');
        }

        return redirect()->route('registro-criminal.index')->with('success', 'Registro creado exitosamente.');
    }

    /**
     * Detalle en JSON para el modal (suma el acceso a la auditoría de la consulta en curso).
     */
    public function show(Request $request, RegistroCriminal $registroCriminal): JsonResponse
    {
        AuditarConsultas::agregarIdsAccedidos($request->input('identificador'), $registroCriminal->id, RegistroCriminal::class);

        return response()->json($this->detalle($registroCriminal));
    }

    public function showByCodigo(Request $request, string $codigo): Response
    {
        $registro = RegistroCriminal::whereRaw('MD5(id) = ?', [$codigo])->firstOrFail();

        AuditarConsultas::agregarIdsAccedidos($request->input('identificador'), $registro->id, RegistroCriminal::class);

        return Inertia::render('RegistroCriminal/Ver', [
            'registro' => $this->detalle($registro),
            'identificador' => $request->input('identificador'),
        ]);
    }

    public function edit(RegistroCriminal $registroCriminal): Response
    {
        $registroCriminal->load(['persona', 'fotos']);

        return Inertia::render('RegistroCriminal/Formulario', [
            'registro' => [
                ...$registroCriminal->only([...self::CAMPOS, 'id', 'nro_registro', 'id_persona', 'foto_frente', 'foto_perfil']),
                'fecha_registro' => $registroCriminal->fecha_registro?->toDateString(),
                'persona' => $registroCriminal->persona ? [
                    ...$registroCriminal->persona->toArray(),
                    'fecha_nacimiento' => $registroCriminal->persona->fecha_nacimiento?->toDateString(),
                ] : null,
            ],
            ...$this->catalogos(),
        ]);
    }

    /**
     * Actualiza el registro. De los datos personales sólo el estado civil y la ocupación se corrigen aquí (cambian con
     * el tiempo); el resto se edita en Personas, igual que antes.
     */
    public function update(Request $request, RegistroCriminal $registroCriminal): RedirectResponse
    {
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

        $reemplazadas = DB::transaction(function () use ($request, $registroCriminal) {
            $registroCriminal->update($request->only(self::CAMPOS));

            // Estado civil y ocupación son de la persona: se corrigen aquí porque cambian con el tiempo.
            $registroCriminal->persona?->update($request->only(['estado_civil', 'ocupacion']));

            if (filled($request->input('telefono')) && $registroCriminal->persona) {
                $this->vincularTelefono($registroCriminal->persona, trim($request->input('telefono')));
            }

            return array_filter([
                $this->reemplazarFoto($registroCriminal, $request->file('foto_frente'), 'FRONTAL'),
                $this->reemplazarFoto($registroCriminal, $request->file('foto_perfil'), 'LATERAL'),
            ]);
        });

        // Las fotos anteriores se borran del disco sólo cuando la base ya confirmó el cambio.
        Storage::disk('public')->delete($reemplazadas);

        return redirect()->route('registro-criminal.index')->with('success', 'Registro actualizado exitosamente.');
    }

    /**
     * Elimina el registro de forma definitiva, como el sistema anterior. Aquel fallaba (error 500) cuando el registro
     * tenía una ficha impresa o un vehículo vinculado, por las claves foráneas: ahora las fichas se borran con él, el
     * vínculo del vehículo con la persona se conserva sin el registro, y las fotos también se quitan del disco.
     */
    public function destroy(RegistroCriminal $registroCriminal): JsonResponse
    {
        $fotos = $registroCriminal->fotos()->pluck('ruta_archivo')->all();

        DB::transaction(function () use ($registroCriminal) {
            VehiculoCaso::where('registro_criminal_id', $registroCriminal->id)->update(['registro_criminal_id' => null]);
            FichaRegistro::withTrashed()->where('registro_criminal_id', $registroCriminal->id)->forceDelete();
            $registroCriminal->fotos()->delete();
            $registroCriminal->forceDelete();
        });

        Storage::disk('public')->delete($fotos);

        return response()->json(['success' => 'Registro eliminado exitosamente.']);
    }

    /**
     * Consulta de registro criminal: filtro obligatorio y 4 caracteres; cada búsqueda nueva queda auditada.
     */
    public function consultarRegistroCriminal(Request $request): JsonResponse|Response
    {
        if (! $request->wantsJson()) {
            return Inertia::render('Consultas/RegistroCriminal');
        }

        if (blank($request->input('filtro')) || mb_strlen((string) $request->input('search')) < 4) {
            return response()->json(['datos' => [], 'total' => 0, 'page' => 1, 'last_page' => 1]);
        }

        $consulta = $this->consulta();
        $this->buscar($consulta, $request->input('filtro'), (string) $request->input('search'));
        $respuesta = $this->pagina($consulta, $request);

        if ($request->boolean('nuevo_filtro')) {
            $request->merge(['cantidad_resultados' => $respuesta->getData(true)['total']]);
            AuditarConsultas::registrar($request->user(), 'consulta_registros', $request);
        }

        return $respuesta;
    }

    /**
     * Datos de la ficha de registro editable: la plantilla activa (o el texto estándar, si la base no tiene ninguna) con
     * los datos del registro ya reemplazados.
     */
    public function vistaPrevia(RegistroCriminal $registroCriminal): JsonResponse
    {
        $registroCriminal->load(['persona', 'division']);
        // Sin plantilla en la base se usa el texto estándar (antes: error «No query results»).
        $plantilla = FichaRegistroPlantilla::activaOEstandar();
        $ahora = Carbon::now()->locale('es');

        $ultima = FichaRegistro::whereYear('created_at', $ahora->year)->max('numero_ficha');
        $persona = $registroCriminal->persona;

        return response()->json([
            'registro_criminal_id' => $registroCriminal->id,
            'nro_ficha' => str_pad((string) ($ultima + 1), 3, '0', STR_PAD_LEFT).'/'.$ahora->year,
            'campos' => [
                'caso_cud' => 'CASO CUD - ...............',
                'introduccion' => Str::replace('{fecha_hora_actual}', $ahora->isoFormat('dddd DD [de] MMMM [de] YYYY, [a horas] hh:mm a'), $plantilla->introduccion),
                'requerimiento' => $plantilla->requerimiento,
                'persona' => '•  '.Str::replace(['{nombre_persona}', '{ci_persona}'], [trim(($persona?->nombres ?? '').' '.($persona?->apellidos ?? '')), $persona?->ci ?? ''], $plantilla->persona),
                // El sistema anterior leía `division->nombre`, que no existe: la división salía vacía.
                'resultado_busqueda' => Str::replace(
                    ['{division}', '{fecha_aprehension}', '{delito}'],
                    [$registroCriminal->division?->division ?? '', $registroCriminal->fecha_registro?->isoFormat('DD/MM/YYYY') ?? '', $registroCriminal->especialidad ?? ''],
                    $plantilla->resultado_busqueda,
                ),
                'nota_certificacion' => $plantilla->nota_certificacion,
                'nota_general' => $plantilla->nota_general,
                'fecha_literal' => 'La Paz, '.$ahora->isoFormat('DD [de] MMMM [de] YYYY'),
            ],
        ]);
    }

    private function consulta(): Builder
    {
        return RegistroCriminal::query()
            ->with(['persona.pais:id,gentilicio', 'division:id,division', 'fotos:id,id_registro_criminal,tipo,ruta_archivo'])
            ->orderByDesc('registro_criminal.created_at');
    }

    private function pagina(Builder $consulta, Request $request): JsonResponse
    {
        $pagina = $consulta->paginate($this->tamanoPagina($request, 6));

        return response()->json([
            'datos' => collect($pagina->items())->filter(fn (RegistroCriminal $r) => $r->persona)->map(fn (RegistroCriminal $r) => $this->tarjeta($r))->values(),
            'total' => $pagina->total(),
            'page' => $pagina->currentPage(),
            'last_page' => $pagina->lastPage(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function tarjeta(RegistroCriminal $registro): array
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
    private function detalle(RegistroCriminal $registro): array
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
            'fotos:id,id_registro_criminal,tipo,ruta_archivo',
        ]);
        $persona = $registro->persona;

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
            'otros_registros' => $persona
                ? $persona->registroCriminal()->whereKeyNot($registro->id)->get(['id', 'nro_registro', 'fecha_registro', 'edad_aproximada', 'especialidad'])
                    ->map(fn (RegistroCriminal $otro) => [
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

    private function buscar(Builder $consulta, ?string $filtro, string $texto): void
    {
        $como = '%'.str_replace(' ', '%', $texto).'%';

        $consulta->where(function (Builder $q) use ($filtro, $texto, $como) {
            match ($filtro) {
                'nombres' => $q->whereHas('persona', fn (Builder $p) => $p->whereRaw("CONCAT(COALESCE(nombres, ''), ' ', COALESCE(apellidos, '')) LIKE ?", [$como])),
                'apellidos' => $q->whereHas('persona', fn (Builder $p) => $p->where('apellidos', 'like', $como)),
                // El alias está en el registro y en la persona: se buscan ambos.
                'alias' => $q->where('alias', 'like', $como)->orWhereHas('persona', fn (Builder $p) => $p->where('alias', 'like', $como)),
                'ci' => $q->whereHas('persona', fn (Builder $p) => $p->where('ci', 'like', $como)),
                // El celular está en el registro y en los teléfonos de la persona.
                'celular' => $q->where('telefono', 'like', $como)->orWhereHas('persona.telefonos', fn (Builder $t) => $t->where('numero_celular', 'like', $como)),
                'cud' => $q->where('cud', 'like', $como),
                'conyuge' => $q->where('nombre_conyuge', 'like', $como),
                'padre' => $q->whereHas('persona', fn (Builder $p) => $p->where('padre', 'like', $como)),
                'madre' => $q->whereHas('persona', fn (Builder $p) => $p->where('madre', 'like', $como)),
                'nombre_supuesto' => $q->where('nombre_supuesto', 'like', $como),
                'hijos' => $q->where('hijos', 'like', $como),
                // dd-mm-aaaa; con otro formato no hay resultados (como antes).
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
     * Foto convertida a WebP (calidad 80) en `registro-criminal/`, como el sistema anterior.
     */
    private function guardarFoto(RegistroCriminal $registro, ?UploadedFile $foto, string $tipo): void
    {
        if (! $foto) {
            return;
        }

        $ruta = 'registro-criminal/img_'.Str::uuid()->toString().'.webp';
        $imagen = (new ImageManager(new ImagickDriver))->read($foto->getPathname());
        Storage::disk('public')->put($ruta, (string) $imagen->toWebp(quality: 80));

        FotosRegistro::create([
            'tipo' => $tipo,
            'ruta_archivo' => $ruta,
            'id_registro_criminal' => $registro->id,
            'id_usuario' => auth()->id(),
        ]);
    }

    /**
     * Cambia la foto de un tipo y devuelve la ruta de la anterior, que se borra del disco tras confirmar.
     */
    private function reemplazarFoto(RegistroCriminal $registro, ?UploadedFile $foto, string $tipo): ?string
    {
        if (! $foto) {
            return null;
        }

        $anterior = FotosRegistro::where('id_registro_criminal', $registro->id)->where('tipo', $tipo)->first();
        $this->guardarFoto($registro, $foto, $tipo);
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
