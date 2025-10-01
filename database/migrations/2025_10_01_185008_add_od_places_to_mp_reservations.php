<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mp_reservations', function (Blueprint $table) {
            // เพิ่มคอลัมน์จุดขึ้น-ลง
            $table->unsignedBigInteger('origin_place_id')->nullable()->after('user_id');
            $table->unsignedBigInteger('destination_place_id')->nullable()->after('origin_place_id');

            // ดัชนี (ชื่อสั้น เพราะ Oracle จำกัด 30 ตัว)
            $table->index('origin_place_id', 'ix_resv_org');
            $table->index('destination_place_id', 'ix_resv_dst');

            // Foreign Keys (ไม่ใส่ RESTRICT — Oracle default คือ restrict อยู่แล้ว)
            $table->foreign('origin_place_id', 'fk_resv_org')
                ->references('place_id')->on('mp_places');
            $table->foreign('destination_place_id', 'fk_resv_dst')
                ->references('place_id')->on('mp_places');
        });
    }

    public function down(): void
    {
        Schema::table('mp_reservations', function (Blueprint $table) {
            // ลบ FK ก่อน
            try {
                $table->dropForeign('fk_resv_org');
            } catch (\Throwable $e) {
            }
            try {
                $table->dropForeign('fk_resv_dst');
            } catch (\Throwable $e) {
            }

            // ลบ Index
            try {
                $table->dropIndex('ix_resv_org');
            } catch (\Throwable $e) {
            }
            try {
                $table->dropIndex('ix_resv_dst');
            } catch (\Throwable $e) {
            }

            // ลบคอลัมน์
            try {
                $table->dropColumn(['origin_place_id', 'destination_place_id']);
            } catch (\Throwable $e) {
            }
        });
    }
};
