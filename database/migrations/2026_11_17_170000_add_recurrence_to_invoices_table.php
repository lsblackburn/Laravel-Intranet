<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->index('client_id', 'invoices_client_recurrence_index');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique(['client_id', 'year', 'month']);
            $table->unique(['work_job_id', 'year', 'month']);
            $table->enum('frequency', ['yearly', 'monthly'])->default('yearly');
            $table->boolean('recurring')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique(['work_job_id', 'year', 'month']);
            $table->unique(['client_id', 'year', 'month']);
            $table->dropColumn(['frequency', 'recurring']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex('invoices_client_recurrence_index');
        });
    }
};
