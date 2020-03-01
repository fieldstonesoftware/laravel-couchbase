<?php declare(strict_types=1);

namespace Fieldstone\Couchbase;

use Illuminate\Support\ServiceProvider;
use Fieldstone\Couchbase\Eloquent\Model;

class CouchbaseServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application events.
     */
    public function boot()
    {

        // TODO: Call publishes and/or mergeConfigFrom here for package configuration
        /*
         *
            $this->publishes([
                __DIR__.'/path/to/config/courier.php' => config_path('courier.php'),
            ]);

            // merges with application's config file
            // only works with top level
            $this->mergeConfigFrom(
                __DIR__.'/path/to/config/courier.php', 'courier'
            );
         *
         */
        // TODO: Commands if needed
        /*
         *
            public function boot()
            {
                if ($this->app->runningInConsole()) {
                    $this->commands([
                        FooCommand::class,
                        BarCommand::class,
                    ]);
                }
            }
         *
         */

        Model::setConnectionResolver($this->app['db']);

        Model::setEventDispatcher($this->app['events']);
    }

    /**
     * Register the service provider.
     */
    public function register()
    {
//        $registerSingletonForConnection = function(string $name, array $config = null) {
//            static $registeredConnections;
//            if(!isset($registeredConnections)) {
//                $registeredConnections = [];
//            }
//            if(!isset($registeredConnections[$name])) {
//                $config = $config ?? config('database.connections.' . $name);
//
//                if (isset($config['driver']) && $config['driver'] === 'couchbase') {
//                    $config['name'] = $name;
//                    $config['database'] = $config['bucket'];
//                    $this->app->singleton('couchbase.connection.' . $name, function ($app) use ($config) {
//                        return new Connection($config);
//                    });
//                }
//
//                $registeredConnections[$name] = true;
//            }
//        };

        $this->app->resolving('couchbase.connection', function()use(&$registerSingletonForConnection) {
            $name = (string) config('database.default');
            $registerSingletonForConnection($name);
            return app('database.connection.'.$name);
        });

        $this->app->resolving('db', function ($db) use(&$registerSingletonForConnection) {
            $db->extend('couchbase', function ($config, $name) use(&$registerSingletonForConnection) {
                $registerSingletonForConnection($name, $config);
                return app('couchbase.connection.'.$name);
            });
        });
    }
}
