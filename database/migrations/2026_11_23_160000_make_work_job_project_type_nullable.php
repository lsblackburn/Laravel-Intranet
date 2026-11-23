<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_jobs', function (Blueprint $table) {
            $table->string('project_type')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('work_jobs')->whereNull('project_type')->update(['project_type' => '']);
        Schema::table('work_jobs', function (Blueprint $table) {
            $table->string('project_type')->nullable(false)->change();
        });
    }
};
