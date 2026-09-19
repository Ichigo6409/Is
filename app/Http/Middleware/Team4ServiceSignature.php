<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class Team4ServiceSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) env('TEAM_SERVICE_SHARED_SECRET');
        if ($secret === '') {
            return response()->json(['message'=>'Integración no configurada.'], 503);
        }

        $timestamp = (string) $request->header('X-Team-Timestamp', '');
        $signature = (string) $request->header('X-Team-Signature', '');
        $bodyHash = hash('sha256', $request->getContent());
        $path = '/'.$request->path();
        $payload = $timestamp.'|'.$request->method().'|'.$path.'|'.$bodyHash;
        $expected = hash_hmac('sha256', $payload, $secret);
        $skew = abs(time() - (int)$timestamp);

        if (!ctype_digit($timestamp) || $skew > (int)env('TEAM_SERVICE_ALLOWED_CLOCK_SKEW', 300) ||
            !hash_equals($expected, $signature)) {
            return response()->json(['message'=>'Firma de integración inválida.'], 401);
        }

        return $next($request);
    }
}
