<?php

namespace App\Http\Controllers;

use App\Models\Mandamiento;
use App\Models\Persona;
use App\Models\RegistroCriminal;
use App\Models\Telefono;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\CSV\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reportes: exportación a CSV de mandamientos, registro criminal, personas y celulares (mismas columnas que el
 * sistema anterior). Se escriben en streaming con `lazy()` para no cargar la tabla entera en memoria.
 */
class ReporteController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Reportes/Index', [
            'reportes' => [
                ['tipo' => 'mandamientos', 'titulo' => 'Mandamientos', 'descripcion' => 'Exporta todos los mandamientos registrados', 'icono' => 'mdi-briefcase-check', 'color' => 'primary', 'total' => Mandamiento::count()],
                ['tipo' => 'registro-criminal', 'titulo' => 'Registro Criminal', 'descripcion' => 'Exporta el registro criminal de personas', 'icono' => 'mdi-alert-box', 'color' => 'danger', 'total' => RegistroCriminal::count()],
                ['tipo' => 'personas', 'titulo' => 'Personas', 'descripcion' => 'Exporta lista de personas registradas', 'icono' => 'mdi-account-multiple', 'color' => 'info', 'total' => Persona::count()],
                ['tipo' => 'celulares', 'titulo' => 'Celulares', 'descripcion' => 'Exporta teléfonos y números celulares', 'icono' => 'mdi-phone', 'color' => 'success', 'total' => Telefono::count()],
            ],
        ]);
    }

    public function exportar(Request $request): StreamedResponse
    {
        return match ($request->input('tipo')) {
            'mandamientos' => $this->mandamientos(),
            'registro-criminal' => $this->registroCriminal(),
            'personas' => $this->personas(),
            'celulares' => $this->celulares(),
            default => abort(400, 'Tipo de reporte no válido'),
        };
    }

    private function mandamientos(): StreamedResponse
    {
        return $this->csv('mandamientos', ['Nro.', 'HOJA DE RUTA O MEMORANDUM', 'NOMBRES', 'APELLIDOS', 'TIPO DE MANDAMIENTO', 'ORIGINAL O FOTOCOPIA', 'DELITO', 'JUZGADO', 'ESTADO', 'DOMICILIO', 'C.I.', 'TELEFONO', 'ASIGNADO', 'ACTIVIDADES REALIZADAS', 'FECHA DE EJECUCIÓN', 'DETALLE EJECUCIÓN'], function (Writer $escritor) {
            $contador = 1;

            Mandamiento::with(['persona:id,nombres,apellidos,ci', 'delito:id,nombre_delito', 'juzgado:id,nombre_juzgado', 'tipoMandamiento:id,tipo_mandamiento'])
                ->lazyById(500)
                ->each(function (Mandamiento $m) use ($escritor, &$contador) {
                    $escritor->addRow(Row::fromValues([
                        $contador++,
                        $m->hoja_ruta,
                        $m->persona->nombres ?? '',
                        $m->persona->apellidos ?? '',
                        $m->tipoMandamiento->tipo_mandamiento ?? '',
                        $m->tipo_documento,
                        $m->delito->nombre_delito ?? '',
                        $m->juzgado->nombre_juzgado ?? '',
                        $m->estado,
                        $m->domicilio,
                        $m->persona->ci ?? '',
                        $m->telefono,
                        $m->asignado,
                        $m->actividades_realizadas,
                        $m->fecha_ejecucion?->format('d/m/Y'),
                        $m->detalle_ejecucion,
                    ]));
                });
        });
    }

    /**
     * El sistema anterior cargaba una relación `pais` inexistente en el registro y el reporte fallaba; la
     * nacionalidad es la de la persona.
     */
    private function registroCriminal(): StreamedResponse
    {
        return $this->csv('registro_criminal', ['Nro. REGISTRO', 'FECHA REGISTRO', 'NOMBRES', 'APELLIDOS', 'C.I.', 'CUD', 'ALIAS', 'NOMBRE SUPUESTO', 'DIVISION', 'ESPECIALIDAD', 'NACIONALIDAD', 'EDAD APROXIMADA', 'ZONAS OPERA', 'MODUS OPERANDI', 'RASGOS', 'ESTATURA', 'PESO', 'CARACTERISTICAS PARTICULARES', 'HIJOS', 'OBSERVACIONES'], function (Writer $escritor) {
            RegistroCriminal::with(['persona:id,nombres,apellidos,ci,id_pais', 'persona.pais:id,gentilicio', 'division:id,division'])
                ->lazyById(500)
                ->each(fn (RegistroCriminal $r) => $escritor->addRow(Row::fromValues([
                    $r->nro_registro,
                    $r->fecha_registro?->format('d/m/Y'),
                    $r->persona->nombres ?? '',
                    $r->persona->apellidos ?? '',
                    $r->persona->ci ?? '',
                    $r->cud,
                    $r->alias,
                    $r->nombre_supuesto,
                    $r->division->division ?? '',
                    $r->especialidad,
                    $r->persona?->pais?->gentilicio ?? '',
                    $r->edad_aproximada,
                    $r->zonas_opera,
                    $r->modus_operandi,
                    $r->rasgos,
                    $r->estatura,
                    $r->peso,
                    $r->caracteristicas_particulares,
                    $r->hijos,
                    $r->observaciones,
                ])));
        });
    }

    /**
     * El sistema anterior pedía una relación `persona` inexistente en Persona y el reporte fallaba.
     */
    private function personas(): StreamedResponse
    {
        return $this->csv('personas', ['Nro. REGISTRO', 'NOMBRES', 'APELLIDOS', 'C.I.', 'DATOS_SEGIP', 'ESTADO', 'RESPONSABLE', 'GRUPO SANGUINEO', 'ALIAS', 'PADRE', 'MADRE', 'OCUPACION', 'ESTADO CIVIL', 'NOMBRE CONYUGE', 'FECHA NACIMIENTO', 'LUGAR NACIMIENTO', 'DOMICILIO'], function (Writer $escritor) {
            $contador = 1;

            Persona::query()->lazyById(500)->each(function (Persona $p) use ($escritor, &$contador) {
                $escritor->addRow(Row::fromValues([
                    $contador++,
                    $p->nombres,
                    $p->apellidos,
                    $p->ci,
                    $p->datos_segip,
                    $p->estado_investigacion,
                    $p->responsable,
                    $p->grupo_sanguineo,
                    $p->alias,
                    $p->padre,
                    $p->madre,
                    $p->ocupacion,
                    $p->estado_civil,
                    $p->nombre_conyuge,
                    $p->fecha_nacimiento?->format('d/m/Y'),
                    $p->lugar_nacimiento,
                    $p->domicilio,
                ]));
            });
        });
    }

    private function celulares(): StreamedResponse
    {
        return $this->csv('telefonos', ['Nro.', 'NUMERO DE CELULAR', 'PERSONA DEL CASO', 'CASO', 'EMPRESA', 'RESP A REQ', 'C.I.', 'INFO', 'CALLAPP', 'TRUECALL', 'UNINET'], function (Writer $escritor) {
            $contador = 1;

            Telefono::with('persona:id,nombres,apellidos,ci')->lazyById(500)->each(function (Telefono $t) use ($escritor, &$contador) {
                $escritor->addRow(Row::fromValues([
                    $contador++,
                    $t->numero_celular,
                    $t->persona_caso,
                    $t->caso,
                    $t->empresa,
                    trim(($t->persona?->nombres ?? '').' '.($t->persona?->apellidos ?? '')),
                    $t->persona?->ci,
                    $t->informacion ?? '',
                    $t->callapp,
                    $t->truecall,
                    $t->uninet,
                ]));
            });
        });
    }

    /**
     * @param  list<string>  $cabeceras
     */
    private function csv(string $nombre, array $cabeceras, callable $escribirFilas): StreamedResponse
    {
        return response()->streamDownload(function () use ($cabeceras, $escribirFilas) {
            $escritor = new Writer;
            $escritor->openToFile('php://output');
            $escritor->addRow(Row::fromValues($cabeceras));
            $escribirFilas($escritor);
            $escritor->close();
        }, $nombre.'_'.date('Y-m-d_His').'.csv', ['Content-Type' => 'text/csv; charset=utf-8']);
    }
}
