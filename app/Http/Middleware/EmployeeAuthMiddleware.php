<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EmployeeAuthMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $guard = Auth::guard('employee');

        if (!$guard->check()) {
            return redirect()->route('auth.employee-login')->with('error', 'กรุณาเข้าสู่ระบบพนักงานก่อน');
        }

        $employee = $guard->user();

        $positionName = trim(strtolower(optional($employee->position)->name ?? ''));
        if (!$employee || !$employee->position || !in_array($positionName, ['driver', 'คนขับ'])) {
            $guard->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect()->route('auth.employee-login')
                ->with('error', 'บัญชีนี้ไม่ใช่พนักงานขับรถ (Driver)');
        }

        return $next($request);
    }
}
