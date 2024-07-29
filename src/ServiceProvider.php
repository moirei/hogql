<?php

namespace MOIREI\HogQl;

use Illuminate\Support\Facades\DB;
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
            'driver' => 'mysql',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'strict' => true,
            'engine' => null,
        ];

        config(['database.connections.hogql' => $config]);

        DB::extend('hogql', function ($connection, $database) use ($config) {
            return new Connection($connection, $database, '', $config);
        });

        $this->mergeConfigFrom(__DIR__.'/../config/hogql.php', 'hogql');

        $this->app->singleton('hogql', HogQl::class);
    }

    public function provides(): array
    {
        return ['hogql'];
    }
}
