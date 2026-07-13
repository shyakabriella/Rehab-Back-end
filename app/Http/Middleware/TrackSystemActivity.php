<?php

namespace App\Http\Middleware;

use App\Models\SystemActivityLog;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackSystemActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->isMethod('OPTIONS') || $request->is('sanctum/csrf-cookie')) {
            return $response;
        }

        try {
            $user = $request->user();
            $path = ltrim($request->path(), '/');
            $routeName = $request->route()?->getName();
            $action = $this->resolveAction($path, $request->method(), $routeName);

            if (!$user && $action === 'login' && $response->getStatusCode() < 300) {
                $email = strtolower(trim((string) $request->input('email')));
                $user = $email !== '' ? User::where('email', $email)->first() : null;
            }

            SystemActivityLog::create([
                'user_id' => $user?->id,
                'action' => $action,
                'module' => $this->resolveModule($path),
                'route_name' => $routeName,
                'method' => strtoupper($request->method()),
                'path' => $path,
                'status_code' => $response->getStatusCode(),
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 2000),
                'metadata' => [
                    'successful' => $response->getStatusCode() < 400,
                ],
                'occurred_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            report($exception);
        }

        return $response;
    }

    private function resolveAction(string $path, string $method, ?string $routeName): string
    {
        $normalized = strtolower($path . ' ' . ($routeName ?? ''));

        if (str_contains($normalized, 'login')) {
            return 'login';
        }

        if (str_contains($normalized, 'logout')) {
            return 'logout';
        }

        return match (strtoupper($method)) {
            'POST' => 'create',
            'PUT', 'PATCH' => 'update',
            'DELETE' => 'delete',
            default => 'view',
        };
    }

    private function resolveModule(string $path): string
    {
        $segments = array_values(array_filter(explode('/', strtolower($path))));

        if (($segments[0] ?? null) === 'api') {
            array_shift($segments);
        }

        return match ($segments[0] ?? 'system') {
            'mood-logs' => 'mood_logs',
            'trigger-logs' => 'trigger_logs',
            'sobriety-milestones' => 'sobriety',
            'recovery-goals' => 'recovery_goals',
            'ai-assistant' => 'ai_assistant',
            'community' => 'community',
            'awareness' => 'awareness',
            'reports' => 'reports',
            'patient-conditions' => 'patient_conditions',
            'login', 'logout' => 'authentication',
            default => $segments[0] ?? 'system',
        };
    }
}
