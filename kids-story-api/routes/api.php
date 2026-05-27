<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\StoryController;
use App\Http\Controllers\Api\StoryImportController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/user', function (Request $request) {
  return 'ssssssssss';
});

Route::get('/stories', [StoryController::class, 'index']);

Route::get('/stories/{id}', [StoryController::class, 'show']);

Route::post('/stories/import', [StoryImportController::class, 'import']);