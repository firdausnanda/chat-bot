<?php

use App\Http\Controllers\ChatController;
use App\Http\Controllers\BookController;
use App\Http\Controllers\DocumentController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Chat endpoints
Route::post('/chat', [ChatController::class, 'stream']);
Route::post('/chat/ask', [ChatController::class, 'ask']);

// Book endpoints
Route::get('/books', [BookController::class, 'index']);
Route::get('/books/{id}', [BookController::class, 'show']);
Route::post('/books/{id}/ingest', [BookController::class, 'ingest']);

// Document (PDF) endpoints
Route::post('/documents/upload', [DocumentController::class, 'upload']);
Route::post('/documents/{id}/process', [DocumentController::class, 'ingest']);
Route::get('/documents', [DocumentController::class, 'index']);
Route::delete('/documents/{id}', [DocumentController::class, 'destroy']);
