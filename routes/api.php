<?php

use App\Http\Controllers\ContactController;
use Illuminate\Support\Facades\Route;



Route::post('/contacts/import', [ContactController::class, 'import']);
Route::get('/contacts', [ContactController::class, 'index']);
