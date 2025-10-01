<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mp_route_places', function (Blueprint $table) {
            $table->increments('route_place_id');
            $table->unsignedInteger('route_id');
            $table->unsignedInteger('place_id');
            $table->integer('sequence_no');
            $table->integer('duration_min');

            // ⛔ ห้ามมี index ซ้ำกับอันนี้อีก (อันนี้จะมี unique index อัตโนมัติ)
            $table->unique(['route_id', 'sequence_no'], 'mp_uniq_route_sequence');

            $table->foreign('route_id')->references('route_id')->on('mp_routes');
            $table->foreign('place_id')->references('place_id')->on('mp_places');

        });

       
        DB::statement("
            ALTER TABLE mp_route_places
            ADD CONSTRAINT mp_chk_route_places_duration
            CHECK (
                (sequence_no = 1 AND duration_min = 0) OR
                (sequence_no > 1 AND duration_min > 0)
            )
        ");
    }

    public function down(): void
    {
        // ลบ CHECK constraint เฉพาะ Oracle (กัน error เวลา refresh/rollback)
        try {
            DB::statement('ALTER TABLE mp_route_places DROP CONSTRAINT mp_chk_route_places_duration');
        } catch (\Throwable $e) {
        }

        // ลบ unique/index ที่ตั้งชื่อเอง ก่อน drop table (กันบางเคสของ Oracle)
        Schema::table('mp_route_places', function (Blueprint $table) {
            try {
                $table->dropUnique('mp_uniq_route_sequence');
            } catch (\Throwable $e) {
            }
            try {
                $table->dropIndex('mp_idx_route');
            } catch (\Throwable $e) {
            }
            try {
                $table->dropIndex('mp_idx_place');
            } catch (\Throwable $e) {
            }
        });

        Schema::dropIfExists('mp_route_places');
    }
};
