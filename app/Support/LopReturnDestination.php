<?php

namespace App\Support;

use App\Enums\ProgramType;
use App\Enums\UserRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\HttpException;

class LopReturnDestination
{
    public static function resolve(Request $request, string $fallback): string
    {
        foreach ([$request->input('return_url'), $request->headers->get('referer')] as $candidate) {
            $url = self::safe($request, $candidate);
            if ($url !== null) {
                return $url;
            }
        }

        return $fallback;
    }

    private static function safe(Request $request, mixed $candidate): ?string
    {
        if (! is_string($candidate) || strlen($candidate) > 4096 || preg_match('/[\\\\\x00-\x20]/', $candidate)) {
            return null;
        }
        $parts = parse_url($candidate);
        if ($parts === false || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])) {
            return null;
        }
        if (isset($parts['host'])) {
            $origin = parse_url($request->getSchemeAndHttpHost());
            if (strtolower($parts['host']) !== strtolower($origin['host'])
                || ($parts['scheme'] ?? '') !== $origin['scheme']
                || ($parts['port'] ?? null) !== ($origin['port'] ?? null)) {
                return null;
            }
        } elseif (isset($parts['scheme']) || ! str_starts_with($candidate, '/') || str_starts_with($candidate, '//')) {
            return null;
        }

        $path = $parts['path'] ?? '/';
        $base = $request->getBaseUrl();
        $matchPath = $base !== '' && str_starts_with($path, $base.'/') ? substr($path, strlen($base)) : $path;
        try {
            $route = Route::getRoutes()->match(Request::create($request->getSchemeAndHttpHost().$matchPath, 'GET'));
        } catch (HttpException) {
            return null;
        }

        if (! in_array($route->getName(), [
            'dashboard', 'lop.index', 'lop.history', 'lop.create', 'lop.import.form',
            'data-lops.index', 'data-boqs.index', 'program.show',
            'bulk-import.lop.index', 'bulk-import.boq.index', 'evidence-approval.index',
        ], true)) {
            return null;
        }
        if ($route->getName() === 'program.show' && ProgramType::tryFrom((string) $route->parameter('program')) === null) {
            return null;
        }

        $query = isset($parts['query']) ? '?'.$parts['query'] : '';
        if ($request->user()?->hasRole(UserRole::SUPER_ADMIN) && in_array($route->getName(), ['lop.index', 'lop.history'], true)) {
            return route('data-lops.index', $route->getName() === 'lop.history' ? ['status' => 'completed'] : []);
        }

        return $request->getSchemeAndHttpHost().$path.$query;
    }
}
