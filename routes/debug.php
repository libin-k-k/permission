<?php

use Illuminate\Support\Facades\Route;
use Libinkk\Permission\Debug\AuthorizationDebugController;

Route::get('authorization/explain', [AuthorizationDebugController::class, 'explain'])
    ->name('libinkk.permission.explain');
