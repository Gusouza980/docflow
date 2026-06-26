<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $connection = (string) config('database.default');
        $database = (string) config("database.connections.{$connection}.database");

        if ($database !== 'testing') {
            throw new RuntimeException(sprintf(
                'Testes bloqueados: conexão "%s" aponta para o banco "%s". Use apenas o banco isolado "testing".',
                $connection,
                $database,
            ));
        }
    }
}
