<?php

use Illuminate\Support\Facades\Route;
use Libinkk\Permission\Frontend\AuthorizationApiController;

Route::get('authorization', [AuthorizationApiController::class, 'authorization'])
    ->name('libinkk.permission.authorization');

Route::get('users/{user}/access', [AuthorizationApiController::class, 'access'])
    ->name('libinkk.permission.user-access');

Route::get('permissions/matrix', [AuthorizationApiController::class, 'matrix'])
    ->name('libinkk.permission.matrix');
