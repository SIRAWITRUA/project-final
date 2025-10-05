<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // หมายเหตุ
        // เดิมโค้ดบังคับ https เสมอเมื่อเป็น local ทำให้ php artisan serve (ที่รันเฉพาะ http)
        // redirect ไปเป็น https://localhost:8000 แล้วโหลดไม่ได้ (เพราะ server ไม่ได้เปิด TLS)
        // แก้ให้ควบคุมด้วย ENV ชื่อ FORCE_HTTPS (ค่าเริ่มต้น false) เพื่อใช้เฉพาะตอน production / reverse proxy เท่านั้น
        //if (env('FORCE_HTTPS', false)) {
        //    URL::forceScheme('https');
        //}
    }
}
