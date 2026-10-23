<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;
use Illuminate\Translation\PotentiallyTranslatedString;

class RequiredIfEjecutado implements ValidationRule
{
    protected $estado;

    public function __construct($estado)
    {
        $this->estado = $estado;
    }

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        //

        $estEjecutado = Str::contains(mb_strtoupper($this->estado), 'EJECUTADO');
        if ($estEjecutado && ! $value) {
            $fail('El campo :attribute es requerido cuando el estado es EJECUTADO.');
        }
    }
}
