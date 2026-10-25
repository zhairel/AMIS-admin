<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminOnly
{
    public function handle(Request $request, Closure $next)
    {
        if (! Auth::check()) {
            return redirect()->route('admin.login');
        }

        $user = Auth::user();

        if (! $user->hasAdminPortalAccess()) {
            abort(403);
        }

        if (($user->account_status ?? 'verified') !== 'verified') {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('admin.login')
                ->withErrors(['email' => 'Account access is disabled. Please contact the system administrator.']);
        }

        if (
            $user->active_admin_session_id
            && $user->active_admin_session_id !== $request->session()->getId()
        ) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('admin.login')
                ->withErrors(['email' => 'This account was signed in from another device. Please log in again.']);
        }

        if ($user->isViewOnlyAccess() && ! $request->isMethodSafe()) {
            abort(403, 'This account is view-only.');
        }

        if ($request->filled('school_year') && is_string($request->query('school_year'))) {
            $sy = trim((string) $request->query('school_year'));
            if (preg_match('/^\d{4}-\d{4}$/', $sy)) {
                session([
                    'admin_school_year' => $sy,
                    'academic_school_year' => $sy,
                ]);
            }
        }

        $routeName = $request->route()?->getName();

        if (
            config('services.school.academic_maintenance', false)
            && ! $user->hasRole(['super_admin', 'admin'])
            && str_starts_with((string) $routeName, 'admin.academic.')
        ) {
            return redirect()->route('admin.dashboard')
                ->with('error', 'The Academic Module is currently under maintenance. Only Administrators can access it at this time.');
        }

        if ($routeName === 'admin.dashboard' && $user->isTeacherAdminViewer()) {
            return redirect()->route($user->adminHomeRouteName());
        }

        if (! $user->canAccessAdminRoute($routeName)) {
            abort(403);
        }

        return $next($request);
    }
}
