<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return $request->expectsJson()
                ? abort(401, 'Unauthenticated.')
                : redirect()->guest(route('login'));
        }

        // Tangani parameter role tunggal maupun multi-role yang dipisahkan koma (misal: "karyawan,owner")
        $allowedRoles = [];
        foreach ($roles as $roleItem) {
            foreach (explode(',', $roleItem) as $splitRole) {
                $trimmed = trim($splitRole);
                if ($trimmed !== '') {
                    $allowedRoles[] = $trimmed;
                }
            }
        }

        if (! $user->hasRole(...$allowedRoles)) {
            abort(403, 'Akses ditolak. Anda tidak memiliki izin untuk mengakses halaman ini.');
        }

        return $next($request);
    }
}
