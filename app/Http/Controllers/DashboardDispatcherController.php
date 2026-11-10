<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DashboardDispatcherController extends Controller
{
    /**
     * Mengarahkan pengguna dari rute pusat /dashboard ke dashboard peran masing-masing.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $role = $request->user()?->role;

        if (! $role) {
            abort(403, 'Role pengguna tidak valid.');
        }

        return redirect()->route($role->dashboardRoute());
    }
}
