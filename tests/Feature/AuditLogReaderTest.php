<?php

namespace Tests\Feature;

use App\Services\AuditLogReader;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuditLogReaderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.connections.audit_reader_test' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
            ],
            'audit-logger.default' => 'mysql',
            'audit-logger.drivers.mysql.connection' => 'audit_reader_test',
            'audit-logger.drivers.mysql.table_prefix' => 'audit_',
            'audit-logger.drivers.mysql.table_suffix' => '_logs',
        ]);
    }

    public function test_it_combines_tables_and_paginates_in_date_order(): void
    {
        $connection = DB::connection('audit_reader_test');

        foreach (['audit_passwords_logs', 'audit_leaves_logs', 'unrelated'] as $table) {
            $connection->getSchemaBuilder()->create($table, function (Blueprint $table) {
                $table->id();
                $table->string('action');
                $table->json('metadata')->nullable();
                $table->timestamp('created_at');
            });
        }

        $connection->table('audit_passwords_logs')->insert([
            'action' => 'password_created',
            'metadata' => json_encode(['message' => 'Lewis has created password for Acme.']),
            'created_at' => '2026-11-24 09:00:00',
        ]);
        $connection->table('audit_leaves_logs')->insert([
            'action' => 'annual_leave_submitted',
            'metadata' => json_encode(['message' => 'Lewis has submitted annual leave.']),
            'created_at' => '2026-11-24 10:00:00',
        ]);
        $connection->table('unrelated')->insert([
            'action' => 'ignore',
            'created_at' => '2026-11-24 11:00:00',
        ]);

        $logs = app(AuditLogReader::class)->paginate(1);

        $this->assertSame(2, $logs->total());
        $this->assertCount(1, $logs->items());
        $this->assertSame('annual_leave_submitted', $logs->items()[0]->action);
        $this->assertSame('Lewis has submitted annual leave.', $logs->items()[0]->metadata['message']);

        $this->app['request']->query->set('page', 2);
        $logs = app(AuditLogReader::class)->paginate(1);

        $this->assertSame('password_created', $logs->items()[0]->action);
    }

    public function test_it_returns_an_empty_page_before_any_audit_tables_exist(): void
    {
        $logs = app(AuditLogReader::class)->paginate();

        $this->assertSame(0, $logs->total());
        $this->assertEmpty($logs->items());
    }
}
