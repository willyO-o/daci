<?php

namespace App\Lib;

use Illuminate\Support\Carbon;

class ReportesPdf extends FpdfFelcc
{
    public function __construct()
    {
        parent::__construct();
        $this->SetMargins(24, 15, 24);
        $this->SetAutoPageBreak(true, 15);
    }

    public function imprimir($datos = [])
    {
        // $this->AddPage('P', 'Legal');
        $this->AddPage('P', [216, 330]);

        // ENCABEZADO
        if (file_exists(public_path('felcc/img/felcc.png'))) {
            $this->Image(public_path('felcc/img/felcc.png'), 42, 15, 15);
        }

        $this->SetFont('Times', 'B', 8);
        $this->SetXY(17, 31);
        $this->MultiCell(
            65,
            3,
            utf8Decode("POLICIA BOLIVIANA\n".
                "DIRECCION DEPARTAMENTAL\n".
                "Fuerza Especial de Lucha Contra El Crimen\n".
                'La Paz - Bolivia'),
            0,
            'C'
        );

        $this->SetFont('Times', 'B', 8);
        $this->SetXY(130, 24);
        $this->MultiCell(
            70,
            3,
            utf8Decode("\"DEPARTAMENTO DE ANALISIS\"\n".
                'CRIMINAL E INTELIGENCIA" "D.A.C.I."'),
            0,
            'C'
        );

        // Número de referencia
        $this->SetFont('Arial', 'B', 10);
        $this->SetTextColor(0, 0, 0);
        $this->SetXY(135, 35);
        $this->Cell(0, 4, 'No.: '.utf8Decode($datos['numero'] ?? '011/2026'), 0, 1, 'L');
        $this->SetXY(135, $this->GetY());
        $this->Cell(0, 4, utf8Decode($datos['caso'] ?? 'CASO CUD- 20112201260172B'), 0, 1, 'L');

        $this->Ln(5);
        // TÍTULO
        $this->SetFont('Times', 'BU', 14);
        // $this->SetFillColor(255, 192, 203);
        $this->SetXY(15, $this->GetY());
        $this->Cell(0, 7, utf8Decode('FICHA DE REGISTRO'), 0, 1, 'C');
        $this->Ln(2);

        // INFORMACIÓN DE FECHA Y LUGAR
        $this->SetFont('Times', '', 12);
        $interlineado = 12 * 0.5 * 1;
        $this->SetTextColor(0, 0, 0);
        $texto = 'En la Ciudad de '.($datos['ciudad'] ?? 'La Paz').', del día '.
            ($datos['fecha'] ?? 'Martes 07 de Abril de 2026').' a horas '.
            ($datos['hora'] ?? '11:00 a.m.').
            ', dando cumplimiento a requerimiento fiscal emitido por el Abg. Omar Alcides Mejillones Copana Director Funcional de las investigaciones en función a las investigaciones conferidas.';
        $this->MultiCell(0, $interlineado, utf8Decode($texto), 0, 'J');
        $this->Ln(3);

        // SECCIÓN REQUIERE
        $this->SetFont('Times', 'B', 12);
        $this->SetXY($this->GetX(), $this->GetY());
        $this->Cell(0, 6, 'REQUIERE:', 0, 1);

        $this->SetFont('Times', '', 12);
        $this->MultiCell(
            0,
            $interlineado,
            utf8Decode("\t\t\t\t QUE, POR LA UNIDAD Y/O SECCION QUE CORRESPONDA, REMITA INFORME Y/O CERTIFICACION EN RELACION AL REGISTRO CRIMINAL Y/O FICHA DE REGISTRO DE:"),
            0,
            'J'
        );
        $this->Ln(2);

        // INFORMACIÓN DE PERSONA
        $this->SetFont('Times', 'B', 12);
        $this->SetXY($this->GetX() + 15, $this->GetY());
        $this->Cell(5, 5, (chr(149)), 0, 0);
        $this->SetX($this->GetX());
        $this->Cell(0, 5, utf8Decode($datos['persona_nombre'] ?? 'JUAN ERNESTO LUNA ULLOA').' '.
            ($datos['persona_ci'] ?? 'con C.I. 6979550'), 0, 1);
        $this->Ln();

        // SECCIÓN DESTACADA
        $this->SetFillColor(255, 0, 255);
        $this->SetTextColor(0, 0, 0);
        $this->SetFont('Times', 'BU', 12);
        $this->SetXY($this->GetX(), $this->GetY());
        $this->MultiCell(
            0,
            $interlineado,
            utf8Decode($datos['seccion_destacada'] ?? 'REVISADA LA BASE DE DATOS DEL REGISTRO FOROESTATICO SOMÁTICO COMPUTARIZADO DEL DEPARTAMENTO DE ANALISIS CRIMINAL E INTELIGENCIA "D.A.C.I." DE LA F.E.L.C.C. DE LA PAZ SE INFORMA QUE SE ENCUENTRAN REGISTRADO EN CALIDAD DE APREHENDIDO POR LA DIVISION ECONOMICOS FINACIEROS DE LA FELCC - LA PAZ,EN FECHA 25/02/2016, POR EL PRESUNTO DELITO DE MANIPULACION INFORMATICA, ASOCIACION DELICTUOSA.'),
            0,
            'J'
        );
        $this->Ln();

        // NOTAS
        $interlineado = 10 * 0.5 * 1;
        $this->SetFont('Arial', 'BI', 10);
        $this->SetTextColor(0, 0, 0);
        $this->SetXY($this->GetX(), $this->GetY());
        $this->MultiCell(
            0,
            $interlineado,
            utf8Decode('Sin enbargo el peticionante debe recurrir a Servicios Técnicos auxiliares de la Policía Boliviana para su verificación y Certificación de Antecedentes Policiales. Se expido la presente ficha de Información, para seguir con las investigaciones.'),
            0,
            'J'
        );

        $this->Ln();

        $interlineado = 12 * 0.5 * 1;

        $this->SetFont('Times', '', 12);
        $this->SetXY($this->GetX(), $this->GetY());
        $this->MultiCell(
            0,
            $interlineado,
            utf8Decode('NOTA. - Se hace conocer que únicamente se tiene el Registro Foroestatico de la Base de Datos desde el año 2005, bajo en la jurisdicción del Municipio de La Paz y no así a nivel nacional.'),
            0,
            'J'
        );

        $fecha = Carbon::now()->locale('es')->isoFormat('D [de] MMMM [de] YYYY');

        $this->Ln();
        $this->Cell(0, 5, utf8Decode('La Paz, '.$fecha), 0, 1, 'R');

        // FIRMAS
        $this->Ln();
        $y_firma = $this->GetY();

        $this->SetFont('Times', '', 9);
        $this->SetXY($this->GetX(), $y_firma + 20);
        $this->Cell(70, 0, str_repeat('_', 35), 0, 1, 'C');

        $this->SetFont('Times', '', 8);
        $this->SetXY($this->GetX(), $y_firma + 22);
        $this->Cell(70, 4, utf8Decode('Sgto. 2do. María Magdalena Huanca Churqui'), 0, 1, 'C');

        $this->SetFont('Times', 'B', 8);
        $this->SetXY($this->GetX(), $y_firma + 26);
        $this->MultiCell(70, 3, utf8Decode(mb_strtoupper("Personal de Servicio del Departamento \nAnálisis Criminal e Inteligencia\nRegistro fotostatico Somático \"Gria\"")), 0, 'C');

        $this->Ln(5);
        $y_firma = $this->GetY();

        $this->SetXY(110, $y_firma + 20);
        $this->Cell(0, 0, str_repeat('_', 35), 0, 1, 'C');
        $this->SetFont('Times', '', 9);

        $this->SetXY(110, $y_firma + 22);
        $this->Cell(0, 4, utf8Decode('Cap. Alberto Marcio Poma García'), 0, 1, 'C');

        $this->SetFont('Times', 'B', 8);
        $this->SetXY(110, $y_firma + 26);
        $this->MultiCell(0, 3, utf8Decode(mb_strtoupper("Jefe del Departamento de\nAnálisis Criminal e Inteligencia\n\"D.A.C.I.\"")), 0, 'C');

        return $this->Output();
    }

    /**
     * @param  string  $destino  `I` (navegador, como antes) o `S` (devuelve el PDF como texto para una respuesta de Laravel).
     */
    public function fichaRegistro($datos = [], string $destino = 'I')
    {
        // $this->AddPage('P', 'Legal');
        $this->AddPage('P', [216, 330]);

        // ENCABEZADO
        if (file_exists(public_path('felcc/img/felcc.png'))) {
            $this->Image(public_path('felcc/img/felcc.png'), 50, 15, 15);
        }

        $this->SetFont('Times', 'B', 8);
        $this->SetXY($this->GetX(), 31);
        $this->MultiCell(
            65,
            3,
            utf8Decode("POLICIA BOLIVIANA\n".
                "DIRECCION DEPARTAMENTAL\n".
                "FUERZA ESPECIAL DE LUCHA CONTRA EL CRIMEN\n".
                'La Paz - Bolivia'),
            0,
            'C'
        );

        $this->SetFont('Times', 'B', 8);
        $this->SetXY(130, 30);
        $this->MultiCell(
            70,
            3,
            utf8Decode("\"DEPARTAMENTO DE ANALISIS\n".
                'CRIMINAL E INTELIGENCIA" "D.A.C.I."'),
            0,
            'C'
        );

        // Número de referencia
        $this->SetFont('Arial', 'B', 10);
        $this->SetTextColor(0, 0, 0);
        $this->SetXY(135, $this->GetY() + 7);
        $this->Cell(0, 4, 'No.: '.utf8Decode(str_pad($datos['numero_ficha'], 3, '0', STR_PAD_LEFT).'/'.($datos['anio_ficha'] ?? '')), 0, 1, 'L');
        $this->SetXY(135, $this->GetY());
        $this->Cell(0, 4, utf8Decode($datos['caso_cud'] ?? ''), 0, 1, 'L');

        $this->Ln(5);
        // TÍTULO
        $this->SetFont('Times', 'BU', 14);
        // $this->SetFillColor(255, 192, 203);
        $this->SetXY($this->GetX(), $this->GetY());
        $this->Cell(0, 7, utf8Decode('FICHA DE REGISTRO'), 0, 1, 'C');
        $this->Ln(4);

        // INFORMACIÓN DE FECHA Y LUGAR
        $this->SetFont('Times', '', 12);
        $interlineado = 12 * 0.5 * 1;
        $this->SetTextColor(0, 0, 0);

        $this->MultiCell(0, $interlineado, utf8Decode($datos['introduccion']), 0, 'J');
        $this->Ln(3);

        // SECCIÓN REQUIERE
        $this->SetFont('Times', 'B', 12);
        $this->SetXY($this->GetX(), $this->GetY());
        $this->Cell(0, 6, 'REQUIERE:', 0, 1);

        $this->SetFont('Times', '', 12);
        $this->MultiCell(
            0,
            $interlineado,
            utf8Decode($datos['requerimiento']),
            0,
            'J'
        );
        $this->Ln(2);

        // INFORMACIÓN DE PERSONA
        $this->SetFont('Times', 'B', 12);
        $this->SetXY($this->GetX() + 15, $this->GetY());
        $this->Cell(5, 5, (chr(149)), 0, 0);
        $this->SetX($this->GetX());
        $this->Cell(0, 5, utf8Decode($datos['persona'] ?? ''), 0, 1);
        $this->Ln();

        // SECCIÓN DESTACADA
        $this->SetFillColor(255, 0, 255);
        $this->SetTextColor(0, 0, 0);
        $this->SetFont('Times', 'BU', 12);
        $this->SetXY($this->GetX(), $this->GetY());
        $this->MultiCell(
            0,
            $interlineado,
            utf8Decode($datos['resultado_busqueda'] ?? ''),
            0,
            'J'
        );
        $this->Ln();

        // NOTAS
        $interlineado = 10 * 0.5 * 1;
        $this->SetFont('Arial', 'BI', 10);
        $this->SetTextColor(0, 0, 0);
        $this->SetXY($this->GetX(), $this->GetY());
        $this->MultiCell(
            0,
            $interlineado,
            utf8Decode($datos['nota_certificacion'] ?? ''),
            0,
            'J'
        );

        $this->Ln();

        $interlineado = 12 * 0.5 * 1;

        $this->SetFont('Times', '', 12);
        $this->SetXY($this->GetX(), $this->GetY());
        $this->MultiCell(
            0,
            $interlineado,
            utf8Decode($datos['nota_general'] ?? ''),
            0,
            'J'
        );

        $this->Ln();
        $this->Cell(0, 5, utf8Decode($datos['fecha_literal'] ?? ''), 0, 1, 'R');

        // FIRMAS
        $this->Ln();
        $y_firma = $this->GetY();

        $this->SetFont('Times', '', 9);
        $this->SetXY($this->GetX(), $y_firma + 20);
        $this->Cell(70, 0, str_repeat('_', 35), 0, 1, 'C');

        $this->SetFont('Times', '', 8);
        $this->SetXY($this->GetX(), $y_firma + 22);
        $this->Cell(70, 4, utf8Decode('Sgto. 2do. María Magdalena Huanca Churqui'), 0, 1, 'C');

        $this->SetFont('Times', 'B', 8);
        $this->SetXY($this->GetX(), $y_firma + 26);
        $this->MultiCell(70, 3, utf8Decode(mb_strtoupper("Personal de Servicio del Departamento \nAnálisis Criminal e Inteligencia\nRegistro fotostatico Somático \"Gria\"")), 0, 'C');

        $this->Ln(5);
        $y_firma = $this->GetY();

        $this->SetXY(110, $y_firma + 20);
        $this->Cell(0, 0, str_repeat('_', 35), 0, 1, 'C');
        $this->SetFont('Times', '', 9);

        $this->SetXY(110, $y_firma + 22);
        $this->Cell(0, 4, utf8Decode('Cap. Alberto Marcio Poma García'), 0, 1, 'C');

        $this->SetFont('Times', 'B', 8);
        $this->SetXY(110, $y_firma + 26);
        $this->MultiCell(0, 3, utf8Decode(mb_strtoupper("Jefe del Departamento de\nAnálisis Criminal e Inteligencia\n\"D.A.C.I.\"")), 0, 'C');

        return $this->Output($destino, 'ficha-registro.pdf');
    }
}
