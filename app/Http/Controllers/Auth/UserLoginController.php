<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserLoginController extends Controller
{
    public function loginPage()
    {
        return view('auth.user-login');
    }

    public function login(Request $request) {
        $messages = [
            'email.required' => 'กรุณากรอกอีเมล',
            'email.email' => 'รูปแบบอีเมลไม่ถูกต้อง',
            'password.required' => 'กรุณากรอกรหัสผ่าน',
        ];

        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ], $messages);

        // remember = false เสมอ
        $remember = false;
        $loggedIn = Auth::guard('web')->attempt([
            'email' => $data['email'],
            'password' => $data['password'],
        ], $remember);

        if ($loggedIn) {
            $request->session()->regenerate();
            return redirect()->intended(route('reservation.search-trip-list'));
        }

        return back()->withInput($request->only('email'))
            ->with('error', 'อีเมลหรือรหัสผ่านไม่ถูกต้อง');
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('auth.user-login');
    }
}
