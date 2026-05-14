<?php

namespace Tests\Feature;

use Tests\TestCase;

class TestingDatabaseIsolationTest extends TestCase
{
    public function test_tests_run_against_the_testing_database(): void
    {
        $this->assertTrue(app()->environment('testing'));
        $this->assertSame('lineup_testing', config('database.connections.mysql.database'));
    }
}
