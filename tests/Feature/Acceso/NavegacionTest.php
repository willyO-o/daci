<?php

namespace Tests\Feature\Acceso;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Cada ítem del menú lateral (`resources/js/Navegacion/navegacion.js`) apunta a una ruta que existe y pide el mismo
 * permiso que esa ruta exige en el servidor: un ítem visible nunca termina en 403.
 */
class NavegacionTest extends TestCase
{
    /**
     * @return list<array{ruta: string, permiso: ?string}>
     */
    private function itemsDelMenu(): array
    {
        $fuente = file_get_contents(resource_path('js/Navegacion/navegacion.js'));
        preg_match_all("/ruta:\s*'([^']+)'(?:[^}]*?permiso:\s*'([^']+)')?/s", $fuente, $coincidencias, PREG_SET_ORDER);

        return array_map(fn (array $c) => ['ruta' => $c[1], 'permiso' => $c[2] ?? null], $coincidencias);
    }

    public function test_cada_item_del_menu_existe_y_pide_el_permiso_de_su_ruta(): void
    {
        $items = $this->itemsDelMenu();
        $this->assertNotEmpty($items);

        foreach ($items as $item) {
            $ruta = Route::getRoutes()->getByName($item['ruta']);
            $this->assertNotNull($ruta, "La ruta «{$item['ruta']}» del menú no existe.");

            $permisosRuta = collect($ruta->gatherMiddleware())
                ->filter(fn ($m) => is_string($m) && str_starts_with($m, 'permission:'))
                ->flatMap(fn (string $m) => explode('|', Str::after($m, 'permission:')))
                ->all();

            if ($item['permiso'] !== null) {
                $this->assertContains($item['permiso'], $permisosRuta, "El ítem «{$item['ruta']}» pide «{$item['permiso']}», pero la ruta exige: ".implode(', ', $permisosRuta));
            }
        }
    }
}
