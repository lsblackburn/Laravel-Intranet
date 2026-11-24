<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The package keeps table existence across applications, while test databases reset.
        \iamfarhad\LaravelAuditLog\Drivers\MySQLDriver::clearCache();
    }
}
