<?php

namespace Tests\Feature\Evidencias;

use App\Models\Caso;
use App\Models\Division;
use App\Models\Evidencia;
use App\Models\EvidenciaArchivo;
use App\Models\EvidenciaArticulo;
use App\Models\Imei;
use App\Models\Rol;
use App\Models\TipoArticulo;
use App\Support\Acceso\CatalogoPermisos;
use Database\Seeders\PermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Módulo Evidencias (D-038): permisos sólo para el administrador, alta con artículos y archivos privados en una
 * transacción, campos según el grupo, publicación individual revisada, documentos internos protegidos.
 */
class EvidenciasTest extends TestCase
{
    use RefreshDatabase;

    private const TODOS = [
        'evidencias.listar', 'evidencias.ver', 'evidencias.crear', 'evidencias.editar', 'evidencias.eliminar',
        'evidencias.documentos', 'evidencias.publicar', 'evidencias.tipos',
    ];

    private const IMEI = '356938035643809'; // dígito verificador válido

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
    }

    private function tipo(string $grupo): TipoArticulo
    {
        return TipoArticulo::where('grupo', $grupo)->orderBy('id')->firstOrFail();
    }

    /**
     * @param  array<int, array<string, mixed>>  $articulos
     * @return array<string, mixed>
     */
    private function datos(array $articulos = [], array $cambios = []): array
    {
        return [
            'fecha_hora' => now()->subDay()->format('Y-m-d\TH:i'),
            'lugar' => 'zona sur, calle 21',
            'funcionario_responsable' => 'sgto. juan perez',
            'estado' => 'REGISTRADA',
            'articulos' => $articulos ?: [['tipo_articulo_id' => $this->tipo('CELULAR')->id, 'marca' => 'samsung', 'modelo' => 'a12', 'imei_1' => self::IMEI]],
            ...$cambios,
        ];
    }

    private function registrar(array $datos): Evidencia
    {
        $this->post(route('evidencias.store'), $datos)->assertSessionHasNoErrors();

        return Evidencia::query()->latest('id')->firstOrFail();
    }

    public function test_los_permisos_nuevos_son_solo_del_administrador_sin_quitar_los_anteriores(): void
    {
        $this->artisan('acceso:sincronizar');
        Permission::where('name', 'like', 'evidencias.%')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $antes = Rol::with('permissions')->get()->mapWithKeys(fn ($r) => [$r->name => $r->permissions->pluck('name')->sort()->values()->all()]);

        $this->seed(PermisosSeeder::class);

        foreach (Rol::with('permissions')->get() as $rol) {
            $evidencias = $rol->permissions->pluck('name')->filter(fn ($p) => str_starts_with($p, 'evidencias.'))->sort()->values()->all();
            $esperado = in_array($rol->name, [CatalogoPermisos::ROL_SUPERADMIN, CatalogoPermisos::ROL_ADMINISTRADOR], true) ? collect(self::TODOS)->sort()->values()->all() : [];
            $this->assertSame($esperado, $evidencias, "Rol {$rol->name}");
            $this->assertEmpty(array_diff($antes[$rol->name], $rol->permissions->pluck('name')->all()), "Rol {$rol->name} perdió permisos.");
        }
    }

    public function test_sin_permiso_no_entra(): void
    {
        $this->actingAs($this->usuarioConPermisos(['casos.listar']));

        $this->get(route('evidencias.index'))->assertForbidden();
        $this->get(route('evidencias.create'))->assertForbidden();
        $this->get(route('evidencias.publicacion.index'))->assertForbidden();
        $this->get(route('tipos-articulo.index'))->assertForbidden();
    }

    public function test_registra_con_articulos_y_archivos_privados_en_una_operacion(): void
    {
        $usuario = $this->usuarioConPermisos(['evidencias.crear', 'evidencias.ver']);
        $this->actingAs($usuario);
        $caso = Caso::create(['codigo' => '201102012600756']);
        $division = Division::create(['division' => 'PROPIEDADES']);

        $this->post(route('evidencias.store'), $this->datos([
            [
                'tipo_articulo_id' => $this->tipo('CELULAR')->id, 'marca' => 'samsung', 'modelo' => 'a12', 'imei_1' => '35-693803-564380-9',
                'fotos' => [UploadedFile::fake()->image('celular.jpg', 3000, 1500)],
                'comprobantes' => [UploadedFile::fake()->create('factura.pdf', 50, 'application/pdf')],
            ],
            [
                'tipo_articulo_id' => $this->tipo('MATERIAL')->id, 'descripcion' => 'cable de cobre', 'cantidad' => 12.5, 'unidad_medida' => 'kg',
                'imei_1' => self::IMEI, 'marca' => 'X',
            ],
        ], [
            'id_caso' => $caso->id,
            'id_division' => $division->id,
            'actas' => [UploadedFile::fake()->create('acta.pdf', 80, 'application/pdf')],
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $evidencia = Evidencia::sole();
        $this->assertNull($evidencia->codigo, 'El código interno lo escribe el usuario: queda vacío si no lo escribe.');
        $this->assertSame([$caso->id, $division->id, $usuario->id, 'ZONA SUR, CALLE 21'], [(int) $evidencia->id_caso, (int) $evidencia->id_division, (int) $evidencia->user_id, $evidencia->lugar]);

        [$celular, $material] = $evidencia->articulos()->orderBy('id')->get()->all();
        $this->assertSame([self::IMEI, 'SAMSUNG', 'NO_PUBLICAR'], [$celular->imei_1, $celular->marca, $celular->estado_publicacion]);
        $this->assertSame([null, null, '12.50', 'KG'], [$material->imei_1, $material->marca, $material->cantidad, $material->unidad_medida], 'Los campos que no son del grupo se vacían.');
        $this->assertNotEmpty($celular->codigo_publico);

        $foto = EvidenciaArchivo::where('tipo', 'FOTO_ARTICULO')->sole();
        $this->assertSame([$celular->id, 'image/webp', false], [(int) $foto->evidencia_articulo_id, $foto->mime, $foto->es_publico]);
        Storage::disk('local')->assertExists($foto->ruta);
        Storage::disk('public')->assertMissing($foto->ruta);
        [$ancho] = getimagesizefromstring(Storage::disk('local')->get($foto->ruta));
        $this->assertSame(2000, $ancho, 'La foto se reduce a 2000 px.');
        $this->assertSame(['ACTA', 'COMPROBANTE', 'FOTO_ARTICULO'], EvidenciaArchivo::orderBy('tipo')->pluck('tipo')->all());
        $this->assertNull(EvidenciaArchivo::where('tipo', 'ACTA')->sole()->evidencia_articulo_id);
    }

    public function test_validaciones_por_grupo_imei_y_cantidad_de_archivos(): void
    {
        $this->actingAs($this->usuarioConPermisos(['evidencias.crear']));

        $this->post(route('evidencias.store'), $this->datos([
            ['tipo_articulo_id' => $this->tipo('MATERIAL')->id],
            ['tipo_articulo_id' => $this->tipo('CELULAR')->id, 'imei_1' => '123', 'imei_2' => '123'],
        ]))->assertSessionHasErrors(['articulos.0.descripcion', 'articulos.1.imei_1']);

        $this->post(route('evidencias.store'), $this->datos([], ['articulos' => []]))->assertSessionHasErrors(['articulos' => 'Agregue al menos un artículo.']);

        $fotos = array_map(fn ($i) => UploadedFile::fake()->image("f{$i}.jpg", 10, 10), range(1, 21));
        $this->post(route('evidencias.store'), $this->datos([['tipo_articulo_id' => $this->tipo('OTRO')->id, 'descripcion' => 'X', 'fotos' => $fotos]]))
            ->assertSessionHasErrors('archivos');

        $this->assertSame(0, Evidencia::count());
    }

    public function test_editar_agrega_actualiza_y_quita_articulos(): void
    {
        $this->actingAs($this->usuarioConPermisos(['evidencias.crear', 'evidencias.editar', 'evidencias.ver']));
        $evidencia = $this->registrar($this->datos([
            ['tipo_articulo_id' => $this->tipo('CELULAR')->id, 'marca' => 'A', 'fotos' => [UploadedFile::fake()->image('a.jpg', 10, 10)]],
            ['tipo_articulo_id' => $this->tipo('OTRO')->id, 'descripcion' => 'MOCHILA'],
        ]));
        [$primero, $segundo] = $evidencia->articulos()->orderBy('id')->get()->all();
        $otraEvidencia = $this->registrar($this->datos());

        $this->put(route('evidencias.update', $evidencia), $this->datos([
            ['id' => $primero->id, 'tipo_articulo_id' => $this->tipo('CELULAR')->id, 'marca' => 'B'],
            ['tipo_articulo_id' => $this->tipo('EQUIPO')->id, 'numero_serie' => 'sn-1'],
        ]))->assertSessionHasNoErrors();

        $this->assertSame('B', $primero->fresh()->marca);
        $this->assertSoftDeleted($segundo);
        $this->assertSame(['B', 'SN-1'], $evidencia->articulos()->orderBy('id')->get()->map(fn ($a) => $a->marca ?? $a->numero_serie)->all());
        $this->assertSame(1, $primero->archivos()->count(), 'Las fotos del artículo que sigue se conservan.');

        // Un artículo de otra evidencia no se puede tomar.
        $ajeno = $otraEvidencia->articulos()->sole();
        $this->put(route('evidencias.update', $evidencia), $this->datos([['id' => $ajeno->id, 'tipo_articulo_id' => $ajeno->tipo_articulo_id]]))
            ->assertSessionHasErrors('articulos.0.tipo_articulo_id');
        $this->assertSame($otraEvidencia->id, (int) $ajeno->fresh()->evidencia_id);
    }

    public function test_ciclo_de_publicacion(): void
    {
        $this->actingAs($this->usuarioConPermisos(['evidencias.crear', 'evidencias.editar', 'evidencias.ver']));
        $celular = $this->tipo('CELULAR')->id;
        $evidencia = $this->registrar($this->datos([['tipo_articulo_id' => $celular, 'marca' => 'SAMSUNG', 'imei_1' => self::IMEI, 'solicitar_publicacion' => true]]));
        $articulo = $evidencia->articulos()->sole();
        $this->assertSame('PENDIENTE_REVISION', $articulo->estado_publicacion, 'Solicitar no publica: queda pendiente.');

        // Sin `evidencias.publicar` no se aprueba.
        $this->post(route('evidencias.publicacion.aprobar', $articulo))->assertForbidden();

        $revisor = $this->usuarioConPermisos(['evidencias.publicar', 'evidencias.editar', 'evidencias.ver']);
        $this->actingAs($revisor);
        $this->post(route('evidencias.publicacion.aprobar', $articulo))->assertSessionHas('success');
        $articulo->refresh();
        $this->assertSame(['PUBLICADO', $revisor->id], [$articulo->estado_publicacion, (int) $articulo->publicado_por]);
        $this->assertNotNull($articulo->publicado_at);

        // Cambiar un dato que ve el ciudadano lo devuelve a revisión.
        $this->put(route('evidencias.update', $evidencia), $this->datos([['id' => $articulo->id, 'tipo_articulo_id' => $celular, 'marca' => 'XIAOMI', 'imei_1' => self::IMEI]], ['fecha_hora' => $evidencia->fecha_hora->format('Y-m-d\TH:i')]))
            ->assertSessionHasNoErrors();
        $this->assertSame(['PENDIENTE_REVISION', null], [$articulo->fresh()->estado_publicacion, $articulo->fresh()->publicado_at]);

        // Rechazar pide motivo.
        $this->post(route('evidencias.publicacion.rechazar', $articulo), ['motivo' => ''])->assertSessionHasErrors('motivo');
        $this->post(route('evidencias.publicacion.aprobar', $articulo));
        $this->post(route('evidencias.publicacion.retirar', $articulo), ['motivo' => 'Entregado a su propietario'])->assertSessionHas('success');
        $this->assertSame(['RETIRADO', 'Entregado a su propietario'], [$articulo->fresh()->estado_publicacion, $articulo->fresh()->motivo_publicacion]);

        // Una acción desde un estado que no corresponde no pisa nada.
        $this->post(route('evidencias.publicacion.rechazar', $articulo), ['motivo' => 'Otro motivo'])->assertSessionHas('error');
        $this->assertSame('RETIRADO', $articulo->fresh()->estado_publicacion);
    }

    public function test_anular_o_eliminar_retira_del_portal(): void
    {
        $this->actingAs($this->usuarioConPermisos(['evidencias.crear', 'evidencias.editar', 'evidencias.eliminar', 'evidencias.publicar', 'evidencias.listar']));
        $celular = $this->tipo('CELULAR')->id;
        $evidencia = $this->registrar($this->datos([
            ['tipo_articulo_id' => $celular, 'marca' => 'A', 'solicitar_publicacion' => true],
            ['tipo_articulo_id' => $celular, 'marca' => 'B', 'solicitar_publicacion' => true],
        ]));
        [$publicado, $pendiente] = $evidencia->articulos()->orderBy('id')->get()->all();
        $this->post(route('evidencias.publicacion.aprobar', $publicado));

        $this->put(route('evidencias.update', $evidencia), $this->datos([
            ['id' => $publicado->id, 'tipo_articulo_id' => $celular, 'marca' => 'A'],
            ['id' => $pendiente->id, 'tipo_articulo_id' => $celular, 'marca' => 'B', 'solicitar_publicacion' => true],
        ], ['estado' => 'ANULADA', 'fecha_hora' => $evidencia->fecha_hora->format('Y-m-d\TH:i')]))->assertSessionHasNoErrors();

        $this->assertSame('RETIRADO', $publicado->fresh()->estado_publicacion);
        $this->assertSame('NO_PUBLICAR', $pendiente->fresh()->estado_publicacion);
        $this->post(route('evidencias.publicacion.aprobar', $publicado))->assertSessionHas('error');
        $this->assertSame('RETIRADO', $publicado->fresh()->estado_publicacion);

        $this->delete(route('evidencias.destroy', $evidencia))->assertRedirect(route('evidencias.index'));
        $this->assertSoftDeleted($evidencia);
        $this->assertSoftDeleted($publicado);
    }

    public function test_los_documentos_internos_piden_su_permiso_y_solo_una_foto_del_articulo_es_publicable(): void
    {
        $this->actingAs($this->usuarioConPermisos(['evidencias.crear', 'evidencias.ver']));
        $this->registrar($this->datos([[
            'tipo_articulo_id' => $this->tipo('CELULAR')->id, 'marca' => 'A',
            'fotos' => [UploadedFile::fake()->image('a.jpg', 10, 10)],
            'fotos_imei' => [UploadedFile::fake()->image('imei.jpg', 10, 10)],
        ]], ['actas' => [UploadedFile::fake()->create('acta.pdf', 10, 'application/pdf')]]));
        $foto = EvidenciaArchivo::where('tipo', 'FOTO_ARTICULO')->sole();
        $fotoImei = EvidenciaArchivo::where('tipo', 'FOTO_IMEI')->sole();
        $acta = EvidenciaArchivo::where('tipo', 'ACTA')->sole();

        $this->get(route('evidencias.archivos.show', $foto))->assertOk()->assertHeader('Content-Type', 'image/webp');
        $this->get(route('evidencias.archivos.show', $acta))->assertForbidden();

        $this->actingAs($this->usuarioConPermisos(['evidencias.ver', 'evidencias.documentos', 'evidencias.publicar']));
        $this->get(route('evidencias.archivos.show', $acta))->assertOk()->assertHeader('Content-Type', 'application/pdf');

        $this->patchJson(route('evidencias.archivos.publico', $foto), ['es_publico' => true])->assertOk();
        $this->assertTrue($foto->fresh()->es_publico);
        $this->patchJson(route('evidencias.archivos.publico', $fotoImei), ['es_publico' => true])->assertUnprocessable();
        $this->patchJson(route('evidencias.archivos.publico', $acta), ['es_publico' => true])->assertUnprocessable();
        $this->assertFalse($acta->fresh()->es_publico);
    }

    public function test_listado_con_filtros_y_detalle_con_coincidencias_de_imei(): void
    {
        $this->actingAs($this->usuarioConPermisos(['evidencias.crear', 'evidencias.listar', 'evidencias.ver']));
        $caso = Caso::create(['codigo' => 'CASO KEYTON']);
        $una = $this->registrar($this->datos([], ['id_caso' => $caso->id, 'codigo' => 'EVD-1']));
        $otra = $this->registrar($this->datos([], ['codigo' => 'EVD-2']));
        $this->registrar($this->datos([['tipo_articulo_id' => $this->tipo('OTRO')->id, 'descripcion' => 'MOCHILA']], ['codigo' => 'EVD-3']));
        Imei::create(['imei' => self::IMEI]);

        $this->get(route('evidencias.index', ['imei' => '5643809']))
            ->assertInertia(fn (Assert $p) => $p->component('Evidencias/Index')->has('evidencias.data', 2));
        $this->get(route('evidencias.index', ['cud' => 'keyton']))
            ->assertInertia(fn (Assert $p) => $p->has('evidencias.data', 1)->where('evidencias.data.0.codigo', 'EVD-1'));

        $articulo = $una->articulos()->sole();
        $this->get(route('evidencias.show', $una))
            ->assertInertia(fn (Assert $p) => $p->component('Evidencias/Ver')
                ->where("coincidencias.{$articulo->id}.imeis.0", self::IMEI)
                ->where("coincidencias.{$articulo->id}.otras.0.id", $otra->id));
    }

    public function test_el_codigo_interno_no_se_repite(): void
    {
        $this->actingAs($this->usuarioConPermisos(['evidencias.crear']));
        $this->registrar($this->datos([], ['codigo' => 'EVD-1']));

        $this->post(route('evidencias.store'), $this->datos([], ['codigo' => 'evd-1']))->assertSessionHasErrors(['codigo' => 'Ya existe una evidencia con ese código.']);
        $this->post(route('evidencias.store'), $this->datos())->assertSessionHasNoErrors();
        $this->post(route('evidencias.store'), $this->datos())->assertSessionHasNoErrors();
        $this->assertSame(2, Evidencia::whereNull('codigo')->count(), 'Sin código, no choca.');
    }

    public function test_tipos_de_articulo_un_tipo_en_uso_no_cambia_de_grupo(): void
    {
        $this->actingAs($this->usuarioConPermisos(['evidencias.tipos', 'evidencias.crear', 'evidencias.editar']));
        $this->post(route('tipos-articulo.store'), ['nombre' => 'cámara', 'grupo' => 'EQUIPO', 'estado' => 'ACTIVO'])->assertSessionHasNoErrors();
        $camara = TipoArticulo::where('nombre', 'CÁMARA')->sole();

        $evidencia = $this->registrar($this->datos([['tipo_articulo_id' => $camara->id, 'numero_serie' => 'X1']]));
        $this->put(route('tipos-articulo.update', $camara), ['nombre' => 'CÁMARA', 'grupo' => 'OTRO', 'estado' => 'ACTIVO'])->assertSessionHasErrors('grupo');
        $this->put(route('tipos-articulo.update', $camara), ['nombre' => 'CÁMARA', 'grupo' => 'EQUIPO', 'estado' => 'INACTIVO'])->assertSessionHasNoErrors();

        // Inactivo: no se ofrece al registrar, pero sigue disponible para editar el artículo que ya lo usa.
        $this->get(route('evidencias.create'))->assertInertia(fn (Assert $p) => $p->where('tipos', fn ($tipos) => ! collect($tipos)->pluck('id')->contains($camara->id)));
        $this->get(route('evidencias.edit', $evidencia))->assertInertia(fn (Assert $p) => $p->where('tipos', fn ($tipos) => collect($tipos)->pluck('id')->contains($camara->id)));
        $this->assertSame('X1', EvidenciaArticulo::sole()->numero_serie);
    }
}
