<?php

namespace App\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class AuditLogReader
{
    public function paginate(int $perPage = 30): LengthAwarePaginator
    {
        $driver = config('audit-logger.default', 'mysql');
        $settings = config("audit-logger.drivers.{$driver}", []);
        $connection = DB::connection($settings['connection'] ?? config('database.default'));
        $prefix = $settings['table_prefix'] ?? 'audit_';
        $suffix = $settings['table_suffix'] ?? '_logs';
        $query = null;

        foreach ($connection->getSchemaBuilder()->getTableListing(schemaQualified: false) as $table) {
            if (! str_starts_with($table, $prefix) || ! str_ends_with($table, $suffix)) {
                continue;
            }

            $entries = $connection->table($table)
                ->select('id', 'action', 'metadata', 'created_at')
                ->selectRaw('? as audit_table', [$table]);

            if ($query === null) {
                $query = $entries;
            } else {
                $query->unionAll($entries);
            }
        }

        if ($query === null) {
            return new LengthAwarePaginator([], 0, $perPage, LengthAwarePaginator::resolveCurrentPage(), [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
            ]);
        }

        $logs = $connection->query()->fromSub($query, 'audit_entries')
            ->orderByDesc('created_at')
            ->orderBy('audit_table')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        $logs->through(function (object $log): object {
            $log->metadata = json_decode($log->metadata ?? '{}', true, 512, JSON_THROW_ON_ERROR);

            return $log;
        });

        return $logs;
    }
}
