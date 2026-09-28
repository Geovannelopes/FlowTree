<?php

use Illuminate\Support\Facades\Route;

// Swagger UI (CDN) lendo docs/swagger/api.yaml.
Route::get('/docs', fn () => view('swagger'));

Route::get('/docs/api.yaml', function () {
    $path = base_path('docs/swagger/api.yaml');

    abort_unless(file_exists($path), 404);

    return response()->file($path, ['Content-Type' => 'application/yaml; charset=utf-8']);
});
