<?php

namespace Fieldstone\Couchbase\Test;

use ErrorException;
use Exception;
use Fieldstone\Couchbase\CouchbaseServiceProvider;
use Fieldstone\Couchbase\Events\QueryFired;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

class TestCase extends \Orchestra\Testbench\TestCase
{

    /**
     * Get package providers.
     *
     * @param  Application $app
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return [
            CouchbaseServiceProvider::class,
        ];
    }

    /**
     * Define environment setup.
     *
     * @param  Application $app
     * @return void
     */
    protected function getEnvironmentSetUp($app)
    {
        // reset base path to point to our package's src directory
        //$app['path.base'] = __DIR__ . '/../src';
        $config = require 'config/database.php';

        $app['config']['couchbase'] = require __DIR__.'/../config/couchbase.php';

        $app['config']->set('app.key', 'ZsZewWyUJ5FsKp9lMwv4tYbNlegQilM7');

        // clear any existing connections
        $app['config']->offsetUnset('database.connections');

        $app['config']->set('database.default', 'couchbase-default');
        $app['config']->set('database.connections.mysql', $config['connections']['mysql']);
        $app['config']->set('database.connections.couchbase-default', $config['connections']['couchbase-default']);
        $app['config']->set('database.connections.couchbase-not-default', $config['connections']['couchbase-not-default']);

        $app['config']->set('auth.model', 'User');
        $app['config']->set('auth.providers.users.model', 'User');
        $app['config']->set('cache.driver', 'array');

        DB::listen(function (QueryExecuted $sql) use (&$fh) {
            file_put_contents(__DIR__ . '/../sql-log.sql', $sql->sql . ";\n", FILE_APPEND);
            file_put_contents(__DIR__ . '/../sql-log.sql', '-- ' . json_encode($sql->bindings) . "\n", FILE_APPEND);
            $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 12);
            $backtrace = array_slice($backtrace, 5);
            file_put_contents(__DIR__ . '/../sql-log.sql', '-- ' . implode("\n-- ", array_map(function ($trace) {
                    return ($trace['class'] ?? '') . ($trace['type'] ?? '') . ($trace['function'] ?? '') . '() called at [' . ($trace['file'] ?? '') . ':' . ($trace['line'] ?? '') . ']';
                }, $backtrace)) . "\n\n", FILE_APPEND);
        });

    }

    protected function assertEventListenFirst($event, $callback)
    {
        $fired = false;
        $firedEvent = null;

        Event::listen($event, function ($event) use ($callback, &$fired, &$firedEvent) {
            if ($fired) {
                return;
            }

            $firedEvent = $event;

            $fired = true;
        });

        $callback();

        $this->assertTrue($fired);

        return $firedEvent;
    }

    protected function assertQueryFiredEquals($n1ql, $bindings, $callback)
    {
        /** @var QueryFired $event */
        $event = $this->assertEventListenFirst(QueryFired::class, $callback);

        $this->assertEquals($n1ql, $event->getQuery());
        if ($bindings !== null) {
            $this->assertEquals($bindings, $event->getPositionalParams());
        }
    }

    protected function assertSelectSqlEquals($queryBuilder, $n1ql, $bindings = null)
    {
        $this->assertQueryFiredEquals($n1ql, $bindings, function () use ($queryBuilder) {
            $queryBuilder->get();
        });
    }

    /**
     * @param callable $callback
     * @param string $expectedExceptionClass
     */
    public function assertException($callback, $expectedExceptionClass)
    {
        $thrownExceptionClass = null;
        try {
            $callback();
        } catch (Exception $e) {
            $thrownExceptionClass = get_class($e);
        }
        $this->assertEquals($expectedExceptionClass, $thrownExceptionClass,
            'Failed to assert that ' . json_encode($thrownExceptionClass) . ' matches expected exception ' . json_encode($expectedExceptionClass) . '.');
    }

    /**
     * @param callable $callback
     * @param int $severity
     * @param null $messageRegex
     */
    public function assertErrorException($callback, $severity, $messageRegex = null)
    {
        $thrownExceptionClass = null;
        try {
            $callback();
        } catch (Exception $e) {
            $thrownExceptionClass = get_class($e);
        }
        $this->assertEquals(ErrorException::class, $thrownExceptionClass,
            'Failed to assert that ' . json_encode($thrownExceptionClass) . ' is a ErrorException.');
        /** @var ErrorException $e */
        if ($messageRegex !== null) {
            $this->assertTrue(preg_match($messageRegex, $e->getMessage()) !== false,
                'Failed to assert that message ' . json_encode($e->getMessage()) . ' matches regex ' . json_encode($messageRegex) . '.');
        }
        $this->assertEquals($severity, $e->getSeverity(), 'Failed to assert that severity matches.');
    }
}
