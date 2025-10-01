<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mp_trips', function (Blueprint $table) {
            // เพิ่มคอลัมน์รอบรถ
            $table->integer('round_no')->after('depart_time')->nullable();

            // index ช่วย query เร็วขึ้นเวลา filter
            $table->index(['service_date', 'round_no'], 'ix_trips_date_round');
        });

        // constraint ให้ round_no ต้องเป็นเลขบวก
        DB::statement("
            ALTER TABLE mp_trips
            ADD CONSTRAINT chk_trips_round_no
            CHECK (round_no >= 1)
        ");
    }

    public function down(): void
    {
        try {
            DB::statement("ALTER TABLE mp_trips DROP CONSTRAINT chk_trips_round_no");
        } catch (\Throwable $e) {
        }

        Schema::table('mp_trips', function (Blueprint $table) {
            $table->dropIndex('ix_trips_date_round');
            $table->dropColumn('round_no');
        });
    }
};
