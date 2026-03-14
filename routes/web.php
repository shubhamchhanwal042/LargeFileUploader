<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\VideoUploadController;
use App\Http\Controllers\S3UploadController;


Route::get('/upload', [VideoUploadController::class, 'index']);
Route::post('/upload', [VideoUploadController::class, 'upload']);

Route::get('/', function () {
    return view('welcome');
});
Route::get('/test-s3', function () {
    Storage::disk('s3')->put('test.txt', 'Hello S3');
});
Route::get('/upload-status',[VideoUploadController::class,'status']);



Route::post('/upload/init', [VideoUploadController::class, 'init']);
Route::post('/upload/complete', [VideoUploadController::class, 'complete']);
Route::post('/s3-presigned-url', [VideoUploadController::class, 'presigned']);

Route::post('/upload/init', [S3UploadController::class,'initUpload']);

Route::post('/s3-presigned-url', [S3UploadController::class,'getPresignedUrl']);

Route::post('/upload/complete', [S3UploadController::class,'completeUpload']);