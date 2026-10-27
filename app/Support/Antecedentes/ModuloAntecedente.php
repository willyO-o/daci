<?php

namespace App\Support\Antecedentes;

use App\Models\FaltaContravencion;
use App\Models\FichaFaltaContravencion;
use App\Models\FichaFaltaContravencionPlantilla;
use App\Models\FotoFaltaContravencion;
use App\Models\RegistroAntecedente;

/**
 * Módulos de antecedentes que funcionan igual que Registro Criminal sobre SUS PROPIAS tablas (mismos campos). Hoy es
 * uno: «Faltas y Contravenciones» (`falta_contravencion`, `foto_falta_contravencion`, `ficha_falta_contravencion`,
 * `ficha_falta_contravencion_plantilla`). Todo lo que identifica al módulo (modelos, tablas, rutas, permisos, carpeta
 * de fotos) sale de aquí; el código común está en `AntecedenteController` y `RegistroAntecedente`.
 */
enum ModuloAntecedente: string
{
    /** El valor es el prefijo de los permisos (`faltas_contravenciones.listar`), como `registro_criminal.*`. */
    case FaltaContravencion = 'faltas_contravenciones';

    /**
     * @return class-string<RegistroAntecedente>
     */
    public function modelo(): string
    {
        return match ($this) {
            self::FaltaContravencion => FaltaContravencion::class,
        };
    }

    public function modeloFoto(): string
    {
        return match ($this) {
            self::FaltaContravencion => FotoFaltaContravencion::class,
        };
    }

    public function modeloFicha(): string
    {
        return match ($this) {
            self::FaltaContravencion => FichaFaltaContravencion::class,
        };
    }

    public function modeloPlantilla(): string
    {
        return match ($this) {
            self::FaltaContravencion => FichaFaltaContravencionPlantilla::class,
        };
    }

    /** Tabla principal. */
    public function tabla(): string
    {
        return match ($this) {
            self::FaltaContravencion => 'falta_contravencion',
        };
    }

    /** Columna de la foto que apunta al registro (`id_falta_contravencion`), como `fotos_registro.id_registro_criminal`. */
    public function claveFoto(): string
    {
        return 'id_'.$this->tabla();
    }

    /** Columna de la ficha que apunta al registro (`falta_contravencion_id`), como `ficha_registro.registro_criminal_id`. */
    public function claveFicha(): string
    {
        return $this->tabla().'_id';
    }

    /** Nombre del módulo (menú, títulos, formulario). */
    public function titulo(): string
    {
        return match ($this) {
            self::FaltaContravencion => 'Faltas y Contravenciones',
        };
    }

    /** Prefijo de URL y de nombres de ruta (`faltas-contravenciones.index`), como `registro-criminal.*`. */
    public function prefijo(): string
    {
        return str_replace('_', '-', $this->value);
    }

    /** Carpeta de las fotos en el disco `public`. */
    public function carpetaFotos(): string
    {
        return $this->prefijo();
    }

    public function permiso(string $accion): string
    {
        return "{$this->value}.{$accion}";
    }

    public function permisoConsulta(): string
    {
        return "consultas.{$this->value}";
    }

    /** Nombre del módulo en la Auditoría de consultas. */
    public function moduloAuditoria(): string
    {
        return "consulta_{$this->value}";
    }

    public function ruta(string $nombre): string
    {
        return "{$this->prefijo()}.{$nombre}";
    }

    public function rutaConsulta(): string
    {
        return "consultas.{$this->prefijo()}";
    }

    /**
     * Lo que las pantallas necesitan para armar títulos, enlaces y botones.
     *
     * @return array<string, mixed>
     */
    public function paraVista(): array
    {
        $acciones = ['listar', 'ver', 'crear', 'editar', 'eliminar', 'ficha'];
        $rutas = ['index', 'create', 'store', 'show', 'edit', 'update', 'destroy', 'showByCodigo', 'vista-previa', 'ficha.store'];

        return [
            'clave' => $this->value,
            'titulo' => $this->titulo(),
            'permisos' => array_combine($acciones, array_map($this->permiso(...), $acciones)),
            'rutas' => [...array_combine($rutas, array_map($this->ruta(...), $rutas)), 'consulta' => $this->rutaConsulta()],
        ];
    }
}
