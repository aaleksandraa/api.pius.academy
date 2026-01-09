<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'name' => 'PMU Academy API',
        'version' => '1.0.0',
    ]);
});
