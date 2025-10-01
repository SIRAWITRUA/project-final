<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mp_trips', function (Blueprint $table) {
            $table->id('trip_id'); // PK (bigint)

            // FK หลัก
            $table->unsignedBigInteger('route_id');    // FK -> mp_routes.route_id
            $table->unsignedBigInteger('vehicle_id');  // FK -> mp_vehicles.vehicle_id
            $table->unsignedBigInteger('driver_id');   // FK -> mp_employees.employee_id

            // วันที่/เวลาออกเดินทาง
            $table->date('service_date');          // วันให้บริการ (YYYY-MM-DD)
            $table->string('depart_time', 5);      // HH:MM (24h) — เก็บเป็น string เพื่อรองรับ Oracle 10g

            // ข้อมูลเสริมของรอบ
            $table->integer('estimated_minutes')->nullable(); // เวลารวมตามเส้นทาง (จาก route_places)
            $table->integer('capacity');          // ความจุที่นั่งของ "รอบนี้" (snapshot จากรถ/override ได้)
            $table->integer('reserved_seats')->default(0); // จองแล้ว

            // สถานะรอบ
            $table->string('status', 20)->default('scheduled'); // scheduled|ongoing|completed|cancelled

            $table->string('notes', 500)->nullable();

            $table->timestamps();

            // ดัชนี
            $table->index(['route_id', 'service_date'], 'ix_trips_route_date');

            // กันซ้อน: คนขับ/รถ ห้ามมีงานชนกันในวันเวลาเดียวกัน
            $table->unique(['service_date', 'depart_time', 'driver_id'],  'uk_trips_date_time_driver');
            $table->unique(['service_date', 'depart_time', 'vehicle_id'], 'uk_trips_date_time_vehicle');

            // FK constraints
            $table->foreign('route_id')->references('route_id')->on('mp_routes')->onDelete('cascade');
            $table->foreign('vehicle_id')->references('vehicle_id')->on('mp_vehicles')->onDelete('cascade');
            $table->foreign('driver_id')->references('employee_id')->on('mp_employees')->onDelete('cascade');
        });

        // --- CHECK constraints สำหรับ Oracle ---
        // สถานะ
        DB::statement("
            ALTER TABLE mp_trips
            ADD CONSTRAINT chk_trips_status
            CHECK (status IN ('scheduled','ongoing','completed','cancelled'))
        ");

        // รูปแบบเวลา HH:MM (00-23:00-59)
        DB::statement("
            ALTER TABLE mp_trips
            ADD CONSTRAINT chk_trips_depart_time
            CHECK (REGEXP_LIKE(depart_time, '^(0[0-9]|1[0-9]|2[0-3]):[0-5][0-9]$'))
        ");
    }

    public function down(): void
    {
        // ลบข้อจำกัดก่อน (กันบาง DB ติดค้าง)
        try {
            DB::statement("ALTER TABLE mp_trips DROP CONSTRAINT chk_trips_status");
        } catch (\Throwable $e) {
        }
        try {
            DB::statement("ALTER TABLE mp_trips DROP CONSTRAINT chk_trips_depart_time");
        } catch (\Throwable $e) {
        }

        Schema::dropIfExists('mp_trips');
    }
};
