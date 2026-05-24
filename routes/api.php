<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\InvoiceController;

/*
|--------------------------------------------------------------------------
| Rutas públicas
|--------------------------------------------------------------------------
*/
Route::prefix('v1')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
});

/*
|--------------------------------------------------------------------------
| Rutas protegidas
|--------------------------------------------------------------------------
*/
Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me',      [AuthController::class, 'me']);

     // Productos — low-stock debe ir ANTES de {id}
    Route::get('/products/low-stock',         [ProductController::class, 'lowStock']);
    Route::get('/products',                   [ProductController::class, 'index']);
    Route::post('/products',                  [ProductController::class, 'store']);
    Route::get('/products/{id}',              [ProductController::class, 'show']);
    Route::put('/products/{id}',              [ProductController::class, 'update']);
    Route::patch('/products/{id}/status',     [ProductController::class, 'changeStatus']);

    // Clientes
    Route::get('/clients',                  [ClientController::class, 'index']);
    Route::post('/clients',                 [ClientController::class, 'store']);
    Route::get('/clients/{id}',             [ClientController::class, 'show']);
    Route::put('/clients/{id}',             [ClientController::class, 'update']);
    Route::patch('/clients/{id}/status',    [ClientController::class, 'changeStatus']);

    // Facturas
    Route::get('/invoices',                   [InvoiceController::class, 'index']);
    Route::post('/invoices',                  [InvoiceController::class, 'store']);
    Route::get('/invoices/{id}',              [InvoiceController::class, 'show']);
    Route::get('/invoices/{id}/audit',        [InvoiceController::class, 'audit']);
    Route::patch('/invoices/{id}/confirm',    [InvoiceController::class, 'confirm']);
    Route::patch('/invoices/{id}/cancel',     [InvoiceController::class, 'cancel']);
});