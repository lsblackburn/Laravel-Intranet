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
        Schema::create('work_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('title')->required();
            $table->string('project_type')->required();
            $table->text('notes')->nullable()->default(null);
            $table->unsignedBigInteger('client_id');
            $table->foreign('client_id')->references('id')->on('clients');
            $table->boolean('active')->default(1);
            $table->boolean('completed')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_jobs');
    }
};
