<?php

use App\Http\Controllers\Api\PublicMapApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/map/objects', [PublicMapApiController::class, 'objects']);
    Route::get('/map/objects/{kind}/{id}', [PublicMapApiController::class, 'object'])->whereNumber('id');
    Route::get('/map/layers', [PublicMapApiController::class, 'layers']);
    Route::get('/map/layers/{slug}/features', [PublicMapApiController::class, 'features']);
    Route::get('/map/features/{id}', [PublicMapApiController::class, 'show'])->whereNumber('id');
    Route::get('/map/search', [PublicMapApiController::class, 'search']);
});
