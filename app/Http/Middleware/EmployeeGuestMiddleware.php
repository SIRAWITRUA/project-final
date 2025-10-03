<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EmployeeGuestMiddleware
{
    /**
     * Redirect authenticated employees away from guest-only pages.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $guard = Auth::guard('employee');
        if ($guard->check()) {
            return redirect()->route('driver.schedule')
                ->with('info', 'คุณได้เข้าสู่ระบบแล้ว');
        }

        return $next($request);
    }
}
