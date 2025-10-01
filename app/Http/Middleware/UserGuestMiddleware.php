<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class UserGuestMiddleware
{
    /**
     * Redirect authenticated web users away from guest-only pages.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::guard('web')->check()) {
            // ผู้ใช้ล็อกอินอยู่แล้ว ไม่ต้องเข้าหน้าเข้าสู่ระบบ
            return redirect()->route('reservation.search-trip-list')
                ->with('info', 'คุณได้เข้าสู่ระบบแล้ว');
        }

        return $next($request);
    }
}
