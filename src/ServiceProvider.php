<?php

namespace MOIREI\HogQl;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;

class ServiceProvider extends BaseServiceProvider
{
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/hogql.php' => config_path('hogql.php'),
            ], 'hogql-config');
        }
    }

    public function register()
    {
        $config = [
            'driver' => 'hogql',
            'strict' => true,
            'engine' => null,
        ];

        config(['database.connections.hogql' => $config]);

        $this->app->resolving('db', function ($db) {
            $db->extend('hogql', function ($config, $name) {
                $config['name'] = $name;
                return new Connection($config);
            });
        });

        $this->mergeConfigFrom(__DIR__.'/../config/hogql.php', 'hogql');

        $this->app->singleton('hogql', HogQl::class);
    }

    public function provides(): array
    {
        return ['hogql'];
    }
}
