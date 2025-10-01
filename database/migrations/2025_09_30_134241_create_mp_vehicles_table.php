<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('mp_vehicles', function (Blueprint $table) {
            $table->id('vehicle_id');

            $table->unsignedBigInteger('vehicle_type_id');
            $table->foreign('vehicle_type_id')->references('vehicle_type_id')->on('mp_vehicle_types')->onDelete('cascade');
            $table->integer('capacity'); // จำนวนที่นั่งมาตรฐาน

            $table->string('license_plate', 50)->unique();
            $table->string('description')->nullable();

            $table->enum('status', ['active', 'maintenance', 'retired'])->default('active');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mp_vehicles');
    }
};
