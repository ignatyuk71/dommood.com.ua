<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Admin\SyncAdminRolesAndPermissions;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\AdminActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $user = $request->user();
        $user->forceFill(['last_login_at' => now()])->save();

        app(SyncAdminRolesAndPermissions::class)->assignLegacyRole($user);

        if ($user->hasAnyRole(['admin', 'manager']) || in_array($user->role, ['admin', 'manager'], true)) {
            app(AdminActivityLogger::class)->log(
                $request,
                'admin.login',
                metadata: [
                    'role' => $user->role,
                    'email' => $user->email,
                ],
                description: 'Користувач увійшов в адмінку',
            );
        }

        $fallbackRoute = $user->hasAnyRole(['admin', 'manager'])
            ? route('dashboard', absolute: false)
            : route('account.dashboard', absolute: false);

        return redirect()->intended($fallbackRoute);
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse|SymfonyResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        if ($request->header('X-Inertia')) {
            return Inertia::location(url('/'));
        }

        return redirect('/');
    }
}
