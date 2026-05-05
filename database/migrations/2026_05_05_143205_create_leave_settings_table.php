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
        Schema::create('leave_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('base_allowance')->default(20);
            $table->unsignedInteger('increase_after_years')->default(2);
            $table->decimal('increase_by_days', 5, 2)->default(1);
            $table->decimal('maximum_allowance', 5, 2)->default(30);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leave_settings');
    }
};
