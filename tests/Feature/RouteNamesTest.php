<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * A same-named web and API route is a silent trap: route($name) resolves to whichever was
 * registered last, so a Blade view can end up posting a browser form to a Sanctum-protected
 * JSON endpoint instead of the page it meant to link to (exactly what happened with
 * servers.index / servers.destroy pointing at /api/servers instead of /servers).
 */
class RouteNamesTest extends TestCase
{
    public function test_no_two_routes_share_a_name_but_resolve_to_different_uris(): void
    {
        $byName = [];
        foreach (Route::getRoutes() as $route) {
            if ($route->getName()) {
                $byName[$route->getName()][] = $route->uri();
            }
        }

        $collisions = [];
        foreach ($byName as $name => $uris) {
            if (count(array_unique($uris)) > 1) {
                $collisions[$name] = array_unique($uris);
            }
        }

        $this->assertSame([], $collisions, 'Route name(s) resolve to more than one URI: '.json_encode($collisions));
    }
}
