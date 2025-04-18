<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\InviteRedirectController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/{codigo}', [InviteRedirectController::class, 'handle']);
