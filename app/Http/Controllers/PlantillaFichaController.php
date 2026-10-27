<?php

namespace App\Http\Controllers;

use App\Models\FichaFaltaContravencionPlantilla;
use App\Models\FichaRegistroPlantilla;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * «Plantilla de la Ficha»: edita el texto base de la ficha de registro de Registro Criminal y de Faltas y
 * Contravenciones. Sólo escribe en la fila ACTIVA de la tabla de plantillas del módulo (o la crea si no hay ninguna);
 * los cambios quedan en la Auditoría de cambios.
 */
class PlantillaFichaController extends Controller
{
    /**
     * Módulos con plantilla: modelo, permiso y nombre del registro en el texto estándar.
     *
     * @var array<string, array{titulo: string, modelo: class-string<Model>, permiso: string, registro: string}>
     */
    private const MODULOS = [
        'registro_criminal' => [
            'titulo' => 'Registro Criminal',
            'modelo' => FichaRegistroPlantilla::class,
            'permiso' => 'registro_criminal.plantilla',
            'registro' => 'REGISTRO CRIMINAL',
        ],
        'faltas_contravenciones' => [
            'titulo' => 'Faltas y Contravenciones',
            'modelo' => FichaFaltaContravencionPlantilla::class,
            'permiso' => 'faltas_contravenciones.plantilla',
            'registro' => 'REGISTRO DE FALTAS Y CONTRAVENCIONES',
        ],
    ];

    /**
     * Bloques de la plantilla: etiqueta, ayuda, largo máximo (el de su columna) y variables que la ficha reemplaza.
     *
     * @var array<string, array{etiqueta: string, ayuda: string, maximo: int, variables: array<string, string>}>
     */
    public const CAMPOS = [
        'introduccion' => [
            'etiqueta' => 'Introducción',
            'ayuda' => 'Primer párrafo: fecha y requerimiento fiscal.',
            'maximo' => 10000,
            'variables' => ['fecha_hora_actual' => 'Fecha y hora en que se genera la ficha'],
        ],
        'requerimiento' => [
            'etiqueta' => 'Requiere',
            'ayuda' => 'Texto debajo de «REQUIERE:».',
            'maximo' => 10000,
            'variables' => [],
        ],
        'persona' => [
            'etiqueta' => 'Persona',
            'ayuda' => 'Línea con viñeta que identifica a la persona.',
            'maximo' => 250,
            'variables' => ['nombre_persona' => 'Nombres y apellidos', 'ci_persona' => 'Número de C.I.'],
        ],
        'resultado_busqueda' => [
            'etiqueta' => 'Resultado de la búsqueda',
            'ayuda' => 'Párrafo subrayado con el resultado.',
            'maximo' => 10000,
            'variables' => [
                'division' => 'División del registro',
                'fecha_aprehension' => 'Fecha del registro (dd/mm/aaaa)',
                'delito' => 'Especialidad del registro',
            ],
        ],
        'nota_certificacion' => [
            'etiqueta' => 'Nota de certificación',
            'ayuda' => 'Nota en cursiva sobre la certificación de antecedentes.',
            'maximo' => 10000,
            'variables' => [],
        ],
        'nota_general' => [
            'etiqueta' => 'Nota general',
            'ayuda' => 'Nota final, antes de la fecha y las firmas.',
            'maximo' => 10000,
            'variables' => [],
        ],
    ];

    public function edit(Request $request): Response
    {
        $modulos = collect(self::MODULOS)
            ->filter(fn (array $modulo) => $request->user()->can($modulo['permiso']))
            ->map(function (array $modulo, string $clave) {
                $activa = $this->activa($modulo['modelo']);

                return [
                    'clave' => $clave,
                    'titulo' => $modulo['titulo'],
                    'campos' => $activa ? $activa->only(array_keys(self::CAMPOS)) : $this->estandar($modulo),
                    'estandar' => $this->estandar($modulo),
                    'existe' => $activa !== null,
                    'actualizado' => $activa?->updated_at?->toIso8601String(),
                ];
            })
            ->values();

        return Inertia::render('PlantillaFicha/Editar', [
            'modulos' => $modulos,
            'definicion' => self::CAMPOS,
        ]);
    }

    public function update(Request $request, string $modulo): RedirectResponse
    {
        $definicion = self::MODULOS[$modulo] ?? abort(404);
        abort_unless($request->user()->can($definicion['permiso']), 403);

        $datos = $request->validate($this->reglas(), $this->mensajes());

        DB::transaction(function () use ($definicion, $datos) {
            $activa = $this->activa($definicion['modelo']);

            if ($activa) {
                $activa->update($datos);
            } else {
                $definicion['modelo']::create([...$datos, 'estado' => 'ACTIVO']);
            }
        });

        return back()->with('success', "Plantilla de la ficha de {$definicion['titulo']} guardada.");
    }

    /**
     * @param  class-string<Model>  $modelo
     */
    private function activa(string $modelo): ?Model
    {
        return $modelo::query()->where('estado', 'ACTIVO')->orderBy('id')->first();
    }

    /**
     * @param  array{registro: string}  $modulo
     * @return array<string, string>
     */
    private function estandar(array $modulo): array
    {
        $texto = FichaRegistroPlantilla::TEXTO_ESTANDAR;
        $texto['requerimiento'] = str_replace('REGISTRO CRIMINAL', $modulo['registro'], $texto['requerimiento']);

        return $texto;
    }

    /**
     * Cada bloque admite sólo sus variables: una que no existe saldría impresa tal cual en la ficha.
     *
     * @return array<string, array<int, mixed>>
     */
    private function reglas(): array
    {
        $reglas = [];

        foreach (self::CAMPOS as $campo => $info) {
            $permitidas = array_keys($info['variables']);
            $reglas[$campo] = ['nullable', 'string', "max:{$info['maximo']}", function (string $atributo, mixed $valor, \Closure $falla) use ($permitidas, $info) {
                preg_match_all('/\{([^{}]*)\}/u', (string) $valor, $encontradas);
                $desconocidas = array_diff(array_unique($encontradas[1]), $permitidas);

                if ($desconocidas !== []) {
                    $disponibles = $permitidas ? 'Puede usar: {'.implode('}, {', $permitidas).'}.' : 'Este bloque no admite variables.';
                    $falla("«{$info['etiqueta']}» tiene variables que no existen: {".implode('}, {', $desconocidas)."}. {$disponibles}");
                }
            }];
        }

        return $reglas;
    }

    /**
     * @return array<string, string>
     */
    private function mensajes(): array
    {
        $mensajes = [];

        foreach (self::CAMPOS as $campo => $info) {
            $mensajes["{$campo}.max"] = "«{$info['etiqueta']}» no puede pasar de :max caracteres.";
        }

        return $mensajes;
    }
}
