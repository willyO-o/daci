<?php

namespace Database\Factories;

use App\Models\Persona;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Persona>
 */
class PersonaFactory extends Factory
{
    protected $model = Persona::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombres' => mb_strtoupper(fake()->firstName()),
            'apellidos' => mb_strtoupper(fake()->lastName().' '.fake()->lastName()),
            'ci' => (string) fake()->unique()->numberBetween(1000000, 9999999),
            'genero' => fake()->randomElement(['MASCULINO', 'FEMENINO']),
            'estado_civil' => 'SOLTERO',
            'telefono' => '7'.fake()->numerify('#######'),
        ];
    }
}
