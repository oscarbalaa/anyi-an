<?php

use Illuminate\Support\Facades\Route;

// VPS con CloudPanel: Nginx reenvía a sí mismo, así que sin proxy confiable todos llegarían como 127.0.0.1
// y compartirían los límites de intentos por IP

beforeEach(fn () => Route::get('/api/prueba-ip', fn () => response()->json(['ip' => request()->ip()])));

it('sin proxies confiables ignora X-Forwarded-For (no se puede falsear la IP)', function () {
    config(['ayni.proxies_confiables' => '']);

    $this->getJson('/api/prueba-ip', ['X-Forwarded-For' => '203.0.113.5'])
        ->assertJson(['ip' => '127.0.0.1']);
});

it('detrás del Nginx propio usa la IP real del visitante', function () {
    config(['ayni.proxies_confiables' => '127.0.0.1']);

    $this->getJson('/api/prueba-ip', ['X-Forwarded-For' => '203.0.113.5'])
        ->assertJson(['ip' => '203.0.113.5']);

    // Un visitante que manda su propio X-Forwarded-For no puede hacerse pasar por otro: cuenta la que añade Nginx
    $this->getJson('/api/prueba-ip', ['X-Forwarded-For' => '198.51.100.9, 203.0.113.5'])
        ->assertJson(['ip' => '203.0.113.5']);
});
