<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Rfq\Http\Controllers\RfqController;

/*
|--------------------------------------------------------------------------
| ROUTE MODUL Rfq (konvensi core: api/v1 + auth:sanctum)
|--------------------------------------------------------------------------
|
|   /api/v1/rfqs    dokumen RFQ (customer -> surveyor)
|
|     GET    /                    rfq:view|rfq:view_own
|     POST   /                    rfq:create
|     GET    /{id}                rfq:view|rfq:view_own
|     PUT    /{id}                rfq:edit
|     DELETE /{id}                rfq:delete
|     POST   /{id}/transition     rfq:mark_as
|     GET    /{id}/activity-logs  rfq:view|rfq:view_own
*/

Route::prefix('api/v1')->middleware('auth:sanctum')->group(function () {
    Route::prefix('rfqs')->group(function () {
        Route::get('/', [RfqController::class, 'index'])->middleware('permission:rfq:view|rfq:view_own');
        Route::post('/', [RfqController::class, 'store'])->middleware('permission:rfq:create');
        Route::get('/{id}', [RfqController::class, 'show'])->whereNumber('id')->middleware('permission:rfq:view|rfq:view_own');
        Route::put('/{id}', [RfqController::class, 'update'])->whereNumber('id')->middleware('permission:rfq:edit|rfq:edit_own');
        Route::post('/{id}/transition', [RfqController::class, 'transition'])->whereNumber('id')->middleware('permission:rfq:mark_as');
        Route::get('/{id}/equipment', [RfqController::class, 'equipment'])->whereNumber('id')->middleware('permission:rfq:view|rfq:view_own');
        Route::get('/{id}/activity-logs', [RfqController::class, 'activityLogs'])->whereNumber('id')->middleware('permission:rfq:view|rfq:view_own');
        Route::delete('/{id}', [RfqController::class, 'destroy'])->whereNumber('id')->middleware('permission:rfq:delete');
    });
});
