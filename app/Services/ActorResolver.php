<?php

namespace App\Services;

class ActorResolver
{
    public static function resolve(string $fallback = 'SYSTEM'): string
    {
        if (app()->runningInConsole()) return $fallback;
        try {
            if (session()->has('team4_impersonate_user_id')) {
                $u = session('team4_impersonate_user_id');
                if (is_string($u) && $u !== '') return $u;
            }
            if (auth()->check()) return (string) auth()->id();
        } catch (\Throwable $e) {}
        return (string) config('team1.default_user_id', $fallback);
    }
}
