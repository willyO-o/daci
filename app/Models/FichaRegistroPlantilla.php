<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Texto base de la ficha de registro de Registro Criminal. Se edita en «Plantilla de la Ficha»; los cambios quedan en la
 * Auditoría de cambios.
 */
class FichaRegistroPlantilla extends Model implements AuditableContract
{
    use Auditable;

    protected $table = 'ficha_registro_plantilla';

    protected $fillable = [

        'introduccion',
        'requerimiento',
        'persona',
        'resultado_busqueda',
        'nota_certificacion',
        'nota_general',
        'estado',
    ];

    /**
     * Texto estándar de la ficha de registro (el de `PlantillaFichaSeeder` del sistema anterior). Lo usa el seeder y,
     * si la base no tiene ninguna plantilla ACTIVA, la vista previa de la ficha en lugar de fallar.
     *
     * @var array<string, string>
     */
    public const TEXTO_ESTANDAR = [
        'introduccion' => 'En la ciudad de La Paz, del día {fecha_hora_actual}, dando cumplimiento a requerimiento fiscal emitido por .......................✏️, Director funcional de las Investigaciones en función a las investigaciones conferidas.',
        'requerimiento' => "\t\t\t QUE, POR LA UNIDAD Y/O SECCIÓN QUE CORRESPONDA, REMITA INFORME Y/O CERTIFICACIÓN EN RELACIÓN AL REGISTRO CRIMINAL Y/O FICHA DE REGISTRO DE:",
        'persona' => '{nombre_persona} con C.I. {ci_persona}',
        'resultado_busqueda' => 'REVISADA LA BASE DE DATOS DEL REGISTRO FOTOSTÁTICO SOMÁTICO COMPUTARIZADO DEL DEPARTAMENTO DE ANÁLISIS CRIMINAL E INTELIGENCIA "D.A.C.I." DE LA F.E.L.C.C. DE LA PAZ SE INFORMA QUE SE ENCUENTRAN REGISTRADO EN CALIDAD DE APREHENDIDO POR LA DIVISIÓN {division} DE LA FELCC - LA PAZ, EN FECHA {fecha_aprehension}, POR EL PRESUNTO DELITO DE {delito}.',
        'nota_certificacion' => 'Sin enbargo el peticionante debe recurrir a la Dirección Nacional de  Servicios Técnicos auxiliares de la Policía Boliviana para su verificación y Certificación de Antecedentes Policiales. Se expide la presente ficha de Información, para seguir con las investigaciones.',
        'nota_general' => ' NOTA. - Se hace conocer que únicamente se tiene el Registro Forostatico Somático de la Base de Datos desde el año 2005, bajo en la jurisdicción del Municipio de La Paz y no así a nivel nacional.',
    ];

    /**
     * La plantilla activa o, si la base no tiene ninguna, una con el texto estándar (sin guardarla).
     */
    public static function activaOEstandar(): self
    {
        return self::query()->where('estado', 'ACTIVO')->orderBy('id')->first()
            ?? new self([...self::TEXTO_ESTANDAR, 'estado' => 'ACTIVO']);
    }
}
