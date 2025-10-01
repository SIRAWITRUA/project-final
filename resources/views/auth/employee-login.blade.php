@extends('layouts.guest')

@section('content')
    <div class="container my-5">
        <div class="row justify-content-center">
            <div class="col-lg-6 col-md-8 col-12">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <div class="text-center mb-3">
                            <div class="login-icon mx-auto mb-4">
                                <i class="ti ti-steering-wheel"></i>
                            </div>
                            <h5 class="card-title mb-1">เข้าสู่ระบบพนักงานขับรถ</h5>
                           
                        </div>

                        @if (session('error'))
                            <div class="alert alert-danger">{{ session('error') }}</div>
                        @endif

                        <form method="POST" action="{{ route('auth.employee-login.post') }}">
                            @csrf
                            <div class="mb-3">
                                <label for="email" class="form-label">อีเมล</label>
                                <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email') }}" required autofocus>
                                @error('email')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="password" class="form-label">รหัสผ่าน</label>
                                <input type="password" class="form-control @error('password') is-invalid @enderror" id="password" name="password" required>
                                @error('password')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <button type="submit" class="btn btn-primary w-100">เข้าสู่ระบบ</button>
                        </form>
                        <style>
                            .login-icon{
                                width:56px; height:56px; border-radius:50%;
                                display:flex; align-items:center; justify-content:center;
                                background: rgba(183, 21, 64, .08);
                                color: var(--primary-600); font-size: 28px;
                            }
                        </style>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
