<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\MpEmployee;

class EmployeeLoginController extends Controller
{
    public function loginPage()
    {
        return view('auth.employee-login');
    }

    public function login(Request $request)
    {
        $messages = [
            'email.required' => 'กรุณากรอกอีเมล',
            'email.email' => 'รูปแบบอีเมลไม่ถูกต้อง',
            'password.required' => 'กรุณากรอกรหัสผ่าน',
        ];

        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ], $messages);

        // ค้นหาพนักงานและตำแหน่งงาน
        $employee = MpEmployee::with('position')
            ->where('email', $data['email'])
            ->first();

        // ต้องเป็นพนักงานขับรถเท่านั้น
        $positionName = trim(strtolower($employee->position->name ?? ''));
        if (!$employee || !$employee->position || !in_array($positionName, ['driver', 'คนขับ'])) {
            return back()->withInput($request->only('email'))
                ->with('error', 'บัญชีนี้ไม่ใช่พนักงานขับรถ (Driver)');
        }

        // พยายามเข้าสู่ระบบ (remember = false เสมอ)
        $remember = false;
        $password = $data['password'];
        $loggedIn = false;

        // หากเป็น bcrypt ให้ใช้ attempt ตามปกติ
        if (is_string($employee->password_hash) && str_starts_with($employee->password_hash, '$2y$')) {
            $loggedIn = Auth::guard('employee')->attempt([
                'email' => $data['email'],
                'password' => $password,
            ], $remember);
        } else {
            // รองรับรูปแบบ legacy/dev: hash อื่นหรือ plain text
            if (is_string($employee->password_hash) && (Hash::check($password, $employee->password_hash) || $employee->password_hash === $password)) {
                Auth::guard('employee')->login($employee, $remember);
                $loggedIn = true;
            }
        }

        if ($loggedIn) {
            $request->session()->regenerate();
            $request->session()->forget('url.intended');
            return redirect()->route('driver.trip-list');
        }

        return back()->withInput($request->only('email'))
            ->with('error', 'อีเมลหรือรหัสผ่านไม่ถูกต้อง');
    }

    public function logout(Request $request)
    {
        Auth::guard('employee')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('auth.employee-login');
    }
}
