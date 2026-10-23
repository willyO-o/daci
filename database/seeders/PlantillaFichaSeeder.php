<?php

namespace Database\Seeders;

use App\Models\FichaRegistroPlantilla;
use Illuminate\Database\Seeder;

class PlantillaFichaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        $plantillas = [
            [
                'introduccion' => 'En la ciudad de La Paz, del día {fecha_hora_actual}, dando cumplimiento a requerimiento fiscal emitido por .......................✏️, Director funcional de las Investigaciones en función a las investigaciones conferidas.',
                'requerimiento' => "\t\t\t QUE, POR LA UNIDAD Y/O SECCIÓN QUE CORRESPONDA, REMITA INFORME Y/O CERTIFICACIÓN EN RELACIÓN AL REGISTRO CRIMINAL Y/O FICHA DE REGISTRO DE:",
                'persona' => '{nombre_persona} con C.I. {ci_persona}',
                'resultado_busqueda' => 'REVISADA LA BASE DE DATOS DEL REGISTRO FOTOSTÁTICO SOMÁTICO COMPUTARIZADO DEL DEPARTAMENTO DE ANÁLISIS CRIMINAL E INTELIGENCIA "D.A.C.I." DE LA F.E.L.C.C. DE LA PAZ SE INFORMA QUE SE ENCUENTRAN REGISTRADO EN CALIDAD DE APREHENDIDO POR LA DIVISIÓN {division} DE LA FELCC - LA PAZ, EN FECHA {fecha_aprehension}, POR EL PRESUNTO DELITO DE {delito}.',
                'nota_certificacion' => 'Sin enbargo el peticionante debe recurrir a la Dirección Nacional de  Servicios Técnicos auxiliares de la Policía Boliviana para su verificación y Certificación de Antecedentes Policiales. Se expide la presente ficha de Información, para seguir con las investigaciones.',
                'nota_general' => ' NOTA. - Se hace conocer que únicamente se tiene el Registro Forostatico Somático de la Base de Datos desde el año 2005, bajo en la jurisdicción del Municipio de La Paz y no así a nivel nacional.',
                'estado' => 'ACTIVO',
            ],
        ];

        foreach ($plantillas as $plantilla) {
            FichaRegistroPlantilla::create($plantilla);
        }
    }
}
