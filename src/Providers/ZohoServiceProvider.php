<?php

namespace Portable\EloquentZoho\Providers;

use Illuminate\Support\ServiceProvider;
use Portable\EloquentZoho\Eloquent\Connection;

class ZohoServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/eloquent-zoho.php',
            'eloquent-zoho'
        );
        // Add database driver.
        $this->app->resolving('db', function ($db) {
            $db->extend('zoho', function ($config, $name) {
                $config['name'] = $name;

                return new Connection($config);
            });
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/eloquent-zoho.php' => config_path('eloquent-zoho.php'),
        ], 'eloquent-zoho');

        $this->app->bind('zoho.builder', function () {
            return $this->app->make('db')->connection('zoho')->getSchemaBuilder();
        });
    }
}
