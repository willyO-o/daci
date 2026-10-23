<?php

namespace Tests\Feature\Importar;

use App\Models\Cargio;
use App\Models\Mandamiento;
use App\Models\Persona;
use App\Models\Telefono;
use App\Models\Vehiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ImportacionesTest extends TestCase
{
    use RefreshDatabase;

    private function csv(string $nombre, string $contenido): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($nombre, $contenido);
    }

    public function test_importa_mandamientos_de_un_csv_con_punto_y_coma(): void
    {
        $this->actingAs($this->usuarioConPermisos(['importar.mandamientos']));
        $archivo = $this->csv('mandamientos.csv', "HOJA DE RUTA O MEMORANDUM;NOMBRE;C.I.;DELITO;JUZGADO;TIPO DE MANDAMIENTO;ESTADO\n"
            ."HR-1;JUAN CARLOS PEREZ LOPEZ;1234567;ROBO;JUZGADO 2;ORDEN DE APREHENSION;pendiente\n");

        $this->postJson(route('importar.mandamientos.importar'), ['archivo' => $archivo])
            ->assertOk()
            ->assertJsonPath('importadas', 1);

        $mandamiento = Mandamiento::with(['persona', 'delito'])->firstOrFail();
        $this->assertSame('PENDIENTE', $mandamiento->estado);
        $this->assertSame('ROBO', $mandamiento->delito->nombre_delito);
        // Con más de dos palabras, las dos últimas son apellidos.
        $this->assertSame('JUAN CARLOS', $mandamiento->persona->nombres);
        $this->assertSame('PEREZ LOPEZ', $mandamiento->persona->apellidos);
    }

    public function test_telefonos_sin_la_columna_obligatoria_se_rechaza(): void
    {
        $this->actingAs($this->usuarioConPermisos(['importar.telefonos']));

        $this->postJson(route('telefonos.importar.store'), ['archivo' => $this->csv('t.csv', "EMPRESA,CASO\nENTEL,X\n")])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('archivo');

        $this->assertSame(0, Telefono::count());
    }

    public function test_un_carguio_repetido_no_se_duplica(): void
    {
        $this->actingAs($this->usuarioConPermisos(['importar.vehiculos']));
        $contenido = "PLACA,EESS,NIT ESTACION,PRODUCTO,NIT CONSUMIDOR,FACTURA,CANTIDAD,MONTO BS.,FECHA VENTA\n"
            ."1234-ABC,LA CIMA,1020,GASOLINA,555,77,\"10,5\",\"39,90\",2026/02/25 10:20\n";

        $this->postJson(route('vehiculos.carguios.importar.store'), ['archivo' => $this->csv('c.csv', $contenido)])->assertJsonPath('importadas', 1);
        $this->postJson(route('vehiculos.carguios.importar.store'), ['archivo' => $this->csv('c.csv', $contenido)])->assertJsonPath('importadas', 0);

        $cargio = Cargio::sole();
        $this->assertSame('1234ABC', Vehiculo::find($cargio->vehiculo_id)->placa);
        $this->assertSame('39.90', $cargio->monto);
    }

    public function test_personas_con_el_mismo_ci_se_actualizan(): void
    {
        $this->actingAs($this->usuarioConPermisos(['importar.personas']));
        Persona::factory()->create(['ci' => '7654321', 'estado_investigacion' => null]);

        $this->postJson(route('personas.importar.store'), [
            'archivo' => $this->csv('p.csv', "N REGISTRO;NOMBRE;CI;DATOS_SEGIP;responsable;ESTADO\n1;MARIA LOPEZ QUISPE;7654321;;ENZO;Pendiente\n"),
        ])->assertOk()->assertJsonPath('importadas', 1);

        $this->assertSame(1, Persona::where('ci', '7654321')->count());
        $this->assertSame('Pendiente', Persona::where('ci', '7654321')->first()->estado_investigacion);
    }

    public function test_sin_permiso_no_importa(): void
    {
        $this->actingAs($this->usuarioConPermisos(['importar.mandamientos']));

        $this->postJson(route('vehiculos.importar.store'), ['archivo' => $this->csv('v.csv', "PLACA\nABC\n")])->assertForbidden();
    }
}
