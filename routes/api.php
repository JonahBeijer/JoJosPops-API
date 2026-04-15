<?php

use App\Http\Controllers\Api\EventController;
use Illuminate\Support\Facades\Route;

Route::get('/pops', [EventController::class, 'index']);
Route::post('/pops', [EventController::class, 'store']);
Route::get('/pops/{id}', [EventController::class, 'show']);
