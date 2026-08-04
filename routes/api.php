<?php

use App\Services\Arkfleet\EquipmentCache;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/equipment', function (EquipmentCache $cache) {
        return response()->json($cache->list(request()->only(['project_code', 'search', 'plant_type', 'is_active'])));
    })->name('api.equipment.index');

    Route::get('/projects', function () {
        return response()->json(\App\Models\ProjectCache::orderBy('project_name')->get());
    })->name('api.projects.index');
});
