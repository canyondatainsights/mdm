<?php

use App\Http\Controllers\MediaController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Public wiki media (diagrams embedded in wiki pages). Served same-origin in the admin and
// cross-origin from the web app; see config/cors.php ('media/*').
Route::get('/media/wiki/{path}', [MediaController::class, 'wiki'])->where('path', '.*');
