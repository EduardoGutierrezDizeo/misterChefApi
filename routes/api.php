<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\RouteController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\ChatbotController;

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
    Route::post('/logout',          [AuthController::class, 'logout']);
    Route::get('/me',               [AuthController::class, 'me']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);

    // Empleados
    Route::get('/employees',               [EmployeeController::class, 'index']);
    Route::post('/employees',              [EmployeeController::class, 'store']);
    Route::get('/employees/{id}',          [EmployeeController::class, 'show']);
    Route::put('/employees/{id}',          [EmployeeController::class, 'update']);
    Route::patch('/employees/{id}/status', [EmployeeController::class, 'changeStatus']);

    // Productos — low-stock ANTES de {id}
    Route::get('/products/low-stock',     [ProductController::class, 'lowStock']);
    Route::get('/products',               [ProductController::class, 'index']);
    Route::post('/products',              [ProductController::class, 'store']);
    Route::get('/products/{id}',          [ProductController::class, 'show']);
    Route::put('/products/{id}',          [ProductController::class, 'update']);
    Route::patch('/products/{id}/status', [ProductController::class, 'changeStatus']);

    // Clientes
    Route::get('/clients',                [ClientController::class, 'index']);
    Route::post('/clients',               [ClientController::class, 'store']);
    Route::get('/clients/{id}',           [ClientController::class, 'show']);
    Route::put('/clients/{id}',           [ClientController::class, 'update']);
    Route::patch('/clients/{id}/status',  [ClientController::class, 'changeStatus']);

    // Facturas — stats ANTES de {id}
    Route::get('/invoices/stats',          [InvoiceController::class, 'stats']);
    Route::get('/invoices',                [InvoiceController::class, 'index']);
    Route::post('/invoices',               [InvoiceController::class, 'store']);
    Route::get('/invoices/{id}',           [InvoiceController::class, 'show']);
    Route::get('/invoices/{id}/audit',     [InvoiceController::class, 'audit']);
    Route::patch('/invoices/{id}/confirm', [InvoiceController::class, 'confirm']);
    Route::patch('/invoices/{id}/cancel',  [InvoiceController::class, 'cancel']);

    // Geolocalización
    Route::post('/location',             [LocationController::class, 'update']);
    Route::get('/location',              [LocationController::class, 'index']);
    Route::patch('/location/deactivate', [LocationController::class, 'deactivate']);

    // Departamentos y ciudades
    Route::get('/departments', [LocationController::class, 'departments']);
    Route::get('/cities',      [LocationController::class, 'cities']);

    // Rutas de domiciliarios — rutas fijas ANTES de las dinámicas
    Route::get('/routes/navigate/{clientId}', [RouteController::class, 'navigate']);
    Route::post('/routes/distribute',         [RouteController::class, 'distribute']);
    Route::get('/routes',                     [RouteController::class, 'index']);
    Route::delete('/routes/{id}',             [RouteController::class, 'destroy']);

    // Sugerencias de rutas
    Route::get('/route-suggestions',                   [RouteController::class, 'suggestions']);
    Route::patch('/route-suggestions/{id}/approve',    [RouteController::class, 'approveSuggestion']);
    Route::patch('/route-suggestions/{id}/reject',     [RouteController::class, 'rejectSuggestion']);

    // Chatbot
    Route::post('/chatbot', [ChatbotController::class, 'handle']);

    //produtos
    Route::patch('/products/{id}/stock',  [ProductController::class, 'updateStock']);
    Route::get('/product-types',          [ProductController::class, 'types']);

});