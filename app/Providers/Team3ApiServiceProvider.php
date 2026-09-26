<?php

namespace App\Providers;

use App\Api\Team3IntegrationController;
use App\Http\Middleware\Team4ServiceSignature;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class Team3ApiServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::prefix('api/equipo4/integration')
            ->middleware([Team4ServiceSignature::class, 'throttle:120,1'])
            ->group(function () {
                Route::post('/availability', [Team3IntegrationController::class, 'availability']);
                Route::post('/reservations', [Team3IntegrationController::class, 'reserve']);
                Route::post('/reservations/{id}/confirm', [Team3IntegrationController::class, 'confirm']);
                Route::post('/reservations/{id}/release', [Team3IntegrationController::class, 'release']);
            });
    }
}
