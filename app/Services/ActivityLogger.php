<?php

namespace App\Services;

use App\Models\User;
use iamfarhad\LaravelAuditLog\DTOs\AuditLog;
use iamfarhad\LaravelAuditLog\Drivers\MySQLDriver;
use iamfarhad\LaravelAuditLog\Drivers\PostgreSQLDriver;
use Illuminate\Database\Eloquent\Model;

class ActivityLogger
{
    public function record(
        ?User $actor,
        Model $subject,
        string $action,
        string $description,
        array $metadata = [],
    ): void {
        if (! config('audit-logger.enabled', true)) {
            return;
        }

        $driverName = config('audit-logger.default', 'mysql');
        $connection = config("audit-logger.drivers.{$driverName}.connection") ?? config('database.default');
        if ($connection !== config('database.default')) {
            throw new \LogicException('Application audits must use the application database connection for atomic writes.');
        }

        $driver = $driverName === 'postgresql'
            ? new PostgreSQLDriver($connection)
            : new MySQLDriver($connection);
        $name = $actor?->name ?? ($metadata['system'] ?? false ? 'System' : 'An unauthenticated visitor');

        // Application audits are synchronous so they commit or roll back with the change.
        $driver->store(new AuditLog(
            entityType: $subject::class,
            entityId: $subject->getKey(),
            action: $action,
            oldValues: [],
            newValues: [],
            causerType: $actor?->getMorphClass(),
            causerId: $actor?->getKey(),
            metadata: array_merge($metadata, [
                'actor_name' => $name,
                'description' => $description,
                'message' => "{$name} has {$description}.",
                'ip_address' => app()->runningInConsole() && ! app()->runningUnitTests() ? null : request()->ip(),
            ]),
            createdAt: now(),
            source: request()->route()?->getActionName() ?? 'console',
        ));
    }
}
