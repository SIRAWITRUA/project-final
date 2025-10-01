<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mp_reservations', function (Blueprint $table) {
            $table->id('reservation_id'); // PK

            // FK หลัก
            $table->unsignedBigInteger('trip_id');   // FK -> mp_trips.trip_id
            $table->unsignedBigInteger('user_id');   // FK -> mp_users.user_id

            // จำนวนที่นั่งที่จอง
            $table->integer('seats_reserved')->default(1);

            // สถานะการจอง
            $table->string('status', 20)->default('active');
            // active | cancelled | completed

            // QR code path หรือ token
            $table->string('qr_code', 255)->nullable();

            // หมายเหตุ (optional)
            $table->string('notes', 500)->nullable();

            $table->timestamps();

            // ดัชนี
            $table->index(['trip_id', 'user_id'], 'ix_resv_trip_user');

            // Unique: จำกัด 1 คนจองได้ไม่เกิน 1 record ต่อ trip
            $table->unique(['trip_id', 'user_id'], 'uk_resv_trip_user');

            // FK
            $table->foreign('trip_id')->references('trip_id')->on('mp_trips')->onDelete('cascade');
            $table->foreign('user_id')->references('user_id')->on('mp_users')->onDelete('cascade');
        });

        // CHECK constraint
        DB::statement("
            ALTER TABLE mp_reservations
            ADD CONSTRAINT chk_resv_status
            CHECK (status IN ('active','cancelled','completed'))
        ");
    }

    public function down(): void
    {
        try {
            DB::statement("ALTER TABLE mp_reservations DROP CONSTRAINT chk_resv_status");
        } catch (\Throwable $e) {
        }

        Schema::dropIfExists('mp_reservations');
    }
};
