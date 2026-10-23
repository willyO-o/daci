<?php

use Illuminate\Support\Carbon;

if (! function_exists('eliminarEspaciosMultiples')) {
    function eliminarEspaciosMultiples($cadena)
    {

        if (! $cadena) {
            return $cadena;
        }

        $cadena = trim(preg_replace('/\s+/', ' ', $cadena));

        return $cadena;
    }
}

if (! function_exists('convertirMayusculas')) {
    function convertirMayusculas($cadena)
    {

        if (! $cadena) {
            return $cadena;
        }

        return mb_strtoupper($cadena, 'UTF-8');
    }
}

if (! function_exists('convertirMinusculas')) {
    function convertirMinusculas($cadena)
    {

        if (! $cadena) {
            return $cadena;
        }

        return mb_strtolower($cadena, 'UTF-8');
    }
}

if (! function_exists('nuloSiVacio')) {
    function nuloSiVacio($cadena)
    {

        if (empty(trim($cadena))) {
            return null;
        }

        return $cadena;
    }
}

if (! function_exists('campoDB')) {
    function campoDB($cadena)
    {

        if (! $cadena) {
            return nuloSiVacio($cadena);
        }
        $cadena = eliminarEspaciosMultiples($cadena);
        $cadena = convertirMayusculas($cadena);

        return $cadena;
    }
}
if (! function_exists('campoDBNumero')) {
    function campoDBNumero($cadena)
    {

        if (! $cadena) {
            return nuloSiVacio($cadena);
        }
        $cadena = eliminarEspaciosMultiples($cadena);
        $cadena = str_replace(',', '.', $cadena);
        $cadena = preg_replace('/[^0-9.]/', '', $cadena);
        // eliminar todos los puntos excepto el último
        $cadena = preg_replace('/\.(?=.*\.)/', '', $cadena);

        return $cadena;
    }
}
if (! function_exists('campoDBFechaHora')) {
    function campoDBFechaHora($cadena)
    {

        if (! $cadena) {
            return nuloSiVacio($cadena);
        }

        $cadena = eliminarEspaciosMultiples($cadena);

        $cadena = str_replace(['/', '.'], '-', $cadena);

        try {
            $fecha = Carbon::parse($cadena);

            return $fecha->toDateTimeString();
        } catch (Exception $e) {
            return nuloSiVacio($cadena);
        }
    }
}

if (! function_exists('limpiarCI')) {
    function limpiarCI($cadena)
    {

        if (! $cadena) {
            return nuloSiVacio($cadena);
        }
        $cadena = convertirMayusculas($cadena);
        $cadena = preg_replace('/[^A-Z0-9]/', '', $cadena);

        return $cadena;
    }
}

if (! function_exists('utf8Decode')) {
    function utf8Decode($cadena)
    {

        if (! $cadena) {
            return nuloSiVacio($cadena);
        }

        return iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $cadena);
    }
}
