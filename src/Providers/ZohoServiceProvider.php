<?php

namespace Portable\EloquentZoho\Providers;

use Illuminate\Support\ServiceProvider;
use Portable\EloquentZoho\Eloquent\Connection as ZohoConnection;
use Illuminate\Database\Connection;
use Portable\EloquentZoho\TokenStorage;

class ZohoServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/eloquent-zoho.php',
            'eloquent-zoho'
        );
        // Add database driver.
        Connection::resolverFor('zoho', function ($connection, $database, $prefix, $config) {
            return new ZohoConnection($config);
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/eloquent-zoho.php' => config_path('eloquent-zoho.php'),
        ], 'eloquent-zoho');

        $this->app->bind('zoho.builder', function () {
            return $this->app->make('db')->connection('zoho')->getSchemaBuilder();
        });

        $this->app->singleton('eloquent-zoho.token-storage', TokenStorage::class);
    }
}
