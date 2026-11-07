<?php

use App\Http\Controllers\NameUpdaterController;
use App\Http\Controllers\NumberUpdaterController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/number/update/{number}', [NumberUpdaterController::class, 'update']);

Route::get('/name/update/{name}', [NameUpdaterController::class, 'update']);
