<?php
namespace App\Providers;

use App\Api\InventoryAdjustmentController;
use App\Api\InventoryReturnController;
use App\Api\InventoryTransferController;
use App\Api\PaymentWebhookController;
use App\Api\Team3IntegrationController;
use App\Http\Middleware\Team4ServiceSignature;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class Team3ApiServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // === API de SALIDA (Equipo 3 -> Equipo 4) ===
        Route::prefix('api/equipo4/integration')
            ->middleware([Team4ServiceSignature::class, 'throttle:120,1'])
            ->group(function () {
                Route::post('/availability', [Team3IntegrationController::class, 'availability']);
                Route::post('/reservations', [Team3IntegrationController::class, 'reserve']);
                Route::post('/reservations/{id}/confirm', [Team3IntegrationController::class, 'confirm']);
                Route::post('/reservations/{id}/release', [Team3IntegrationController::class, 'release']);
                Route::get('/reservations/{id}', [Team3IntegrationController::class, 'show']);

                Route::post('/returns',     [InventoryReturnController::class, 'receive']);
                Route::post('/transfers',   [InventoryTransferController::class, 'receive']);
                Route::post('/adjustments', [InventoryAdjustmentController::class, 'receive']);
            });

        // === API de ENTRADA (Equipo 2 -> Equipo 4) ===
        Route::prefix('api/equipo4/webhooks')
            ->middleware([Team4ServiceSignature::class, 'throttle:240,1'])
            ->group(function () {
                Route::post('/payment-status', [PaymentWebhookController::class, 'receive']);
            });
    }
}