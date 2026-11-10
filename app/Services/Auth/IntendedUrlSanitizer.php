<?php

namespace App\Services\Auth;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class IntendedUrlSanitizer
{
    /**
     * Sanitasi session intended URL untuk mencegah Open Redirect,
     * manipulasi skema/host, dan akses silang rute antar role.
     */
    public static function sanitize(Request $request, User $user): void
    {
        $session = $request->hasSession() ? $request->session() : session();
        $intendedUrl = $session->get('url.intended');

        if (! is_string($intendedUrl) || trim($intendedUrl) === '') {
            return;
        }

        // 1. Tolak URL ambigu yang memuat backslash
        if (str_contains($intendedUrl, '\\')) {
            $session->forget('url.intended');

            return;
        }

        // 2. Tolak protocol-relative URL
        if (str_starts_with($intendedUrl, '//')) {
            $session->forget('url.intended');

            return;
        }

        // 3. Parse URL dan tangani jika bernilai false (malformed)
        $parsed = parse_url($intendedUrl);
        if ($parsed === false) {
            $session->forget('url.intended');

            return;
        }

        // 4. Tolak URL yang memuat userinfo (username/password pada URL)
        if (! empty($parsed['user']) || ! empty($parsed['pass'])) {
            $session->forget('url.intended');

            return;
        }

        // 5. Validasi skema dan pastikan URL ber-skema memiliki host
        if (isset($parsed['scheme'])) {
            $scheme = strtolower($parsed['scheme']);
            if (! in_array($scheme, ['http', 'https'], true)) {
                $session->forget('url.intended');

                return;
            }

            // Tolak URL ber-skema HTTP/HTTPS tetapi tanpa host
            if (empty($parsed['host'])) {
                $session->forget('url.intended');

                return;
            }
        }

        // 6. Tolak tujuan eksternal (host atau port tidak cocok dengan request saat ini)
        if (isset($parsed['host'])) {
            if (strtolower($parsed['host']) !== strtolower($request->getHost())) {
                $session->forget('url.intended');

                return;
            }

            if (isset($parsed['port']) && (int) $parsed['port'] !== (int) $request->getPort()) {
                $session->forget('url.intended');

                return;
            }
        }

        // 7. Cocokkan path ke route GET yang terdaftar
        $path = $parsed['path'] ?? '/';
        $queryString = isset($parsed['query']) ? '?'.$parsed['query'] : '';

        try {
            $syntheticRequest = Request::create($path.$queryString, 'GET');
            $route = app('router')->getRoutes()->match($syntheticRequest);
        } catch (NotFoundHttpException|MethodNotAllowedHttpException) {
            $session->forget('url.intended');

            return;
        }

        // 8. Periksa apakah nama route ada dalam allowlist resmi role pengguna
        $routeName = $route->getName();
        $allowedRoutes = match ($user->role) {
            Role::PEMBELI => ['profile', 'pembeli.dashboard'],
            Role::KARYAWAN => ['profile', 'karyawan.dashboard'],
            Role::OWNER => ['profile', 'owner.dashboard'],
        };

        if (! $routeName || ! in_array($routeName, $allowedRoutes, true)) {
            $session->forget('url.intended');
        }
    }
}
