<?php

namespace App\Console\Commands;

use App\Services\Team1Client;
use Illuminate\Console\Command;

class Team1Probe extends Command
{
    protected $signature = 'team1:probe {user_id? : User ID a probar}';
    protected $description = 'Prueba la conexion con la API del Eq. 1 (HMAC)';

    public function handle(): int
    {
        $client = Team1Client::make();

        if (!$client->isConfigured()) {
            $this->error('TEAM1_API_URL o TEAM1_SERVICE_SECRET no configurados.');
            $this->line('  Verifica el .env y corre: php artisan config:clear');
            return self::FAILURE;
        }

        $this->info('Cliente configurado. Probando endpoints...');

        $userId = $this->argument('user_id') ?: (string) config('team1.default_user_id');

        $this->line("  -> GET /users/{$userId}");
        $user = $client->getUser($userId);
        $user ? $this->info('    OK') : $this->error('    FALLO');
        if ($user) $this->line('    ' . json_encode($user, JSON_PRETTY_PRINT));

        $this->line("  -> GET /users/{$userId}/role");
        $role = $client->getUserRole($userId);
        $role ? $this->info('    OK') : $this->error('    FALLO');
        if ($role) $this->line('    ' . json_encode($role, JSON_PRETTY_PRINT));

        $this->line("  -> GET /roles");
        $roles = $client->listRoles();
        $roles ? $this->info('    OK') : $this->error('    FALLO');

        return self::SUCCESS;
    }
}