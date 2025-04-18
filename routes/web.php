<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\InviteRedirectController;

Route::redirect('/', '/admin');

Route::get('/{codigo}', [InviteRedirectController::class, 'handle']);
