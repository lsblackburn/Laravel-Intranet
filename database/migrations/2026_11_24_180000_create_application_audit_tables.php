<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $driver = config('audit-logger.default', 'mysql');
        $settings = config("audit-logger.drivers.{$driver}", []);
        $schema = Schema::connection($settings['connection'] ?? config('database.default'));

        foreach (['AuthenticationAttempt', 'Client', 'Invoice', 'Leave', 'LeaveSetting', 'NonWorkDay', 'Password', 'ProjectType', 'User', 'UserDepartment', 'WorkDay', 'WorkJob'] as $entity) {
            $tableName = ($settings['table_prefix'] ?? 'audit_').Str::plural(Str::snake($entity)).($settings['table_suffix'] ?? '_logs');
            if ($schema->hasTable($tableName)) {
                continue;
            }

            $schema->create($tableName, function (Blueprint $table) {
                $table->id();
                $table->string('entity_id')->index();
                $table->string('action')->index();
                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();
                $table->string('causer_type')->nullable();
                $table->string('causer_id')->nullable()->index();
                $table->json('metadata')->nullable();
                $table->timestamp('created_at')->index();
                $table->string('source')->nullable();
                $table->timestamp('anonymized_at')->nullable()->index();
                $table->index(['entity_id', 'action']);
                $table->index(['entity_id', 'created_at']);
                $table->index(['causer_id', 'action']);
                $table->index(['action', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        // Preserve audit history, including tables that predate this migration.
    }
};
