<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\StoryController;
use App\Http\Controllers\Api\StoryImportController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Api\ImageUploadController;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;


Route::get('/testcloudinary', function () {
    return Cloudinary::uploadApi()->upload(
        "https://res.cloudinary.com/demo/image/upload/sample.jpg"
    );
});


Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');



Route::get('/stories', [StoryController::class, 'index']);

Route::get('/stories/{id}', [StoryController::class, 'show']);



Route::post(
    '/register',
    [AuthController::class, 'register']
);

Route::post(
    '/login',
    [AuthController::class, 'login']
);

Route::middleware('auth:sanctum')->group(function () {

    Route::post(
        '/logout',
        [AuthController::class, 'logout']
    );

       /*
    |--------------------------------------------------------------------------
    | PAYMENTS
    |--------------------------------------------------------------------------
    */

    Route::post('/payments', [
        PaymentController::class,
        'store'
    ]);

    Route::get('/payments', [
        PaymentController::class,
        'index'
    ]);

    Route::get('/payments/{id}', [
        PaymentController::class,
        'show'
    ]);

    Route::get('/subscription', [
        PaymentController::class,
        'subscription'
    ]);

});



Route::post('/upload-story-images', [ImageUploadController::class, 'upload']);

Route::post('/stories/import', [StoryImportController::class, 'import']);
Route::post('/stories/upload-story', [StoryImportController::class, 'create_story_json']);
Route::post('/stories/generate-story', [StoryImportController::class, 'generateStoryJSON']);    
 

